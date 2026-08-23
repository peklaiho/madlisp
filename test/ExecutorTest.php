<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CompiledLoader;
use MadLisp\CompiledProgram;
use MadLisp\Compiler;
use MadLisp\CoreFunc;
use MadLisp\CoreFuncId;
use MadLisp\Env;
use MadLisp\Executor;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\OpCode;
use MadLisp\Reader;
use MadLisp\Symbol;
use MadLisp\Tokenizer;
use MadLisp\Vector;

class ExecutorTest extends TestCase
{
    public function testLoadsConstant(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $program = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::RETURN,
        ], [42], 0);

        $this->assertSame(42, $executor->execute($program, $env));
    }

    public function testExecutesProgramInChildFrame(): void
    {
        $executor = new Executor();
        $env = new Env('root');
        $child = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::RETURN,
        ], [42], 0);
        $program = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::EXECUTE_PROGRAM,
            OpCode::RETURN,
        ], [$child], 0);

        $this->assertSame(42, $executor->execute($program, $env));
    }

    public function testLoadsFileInChildFrame(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'madlisp-');
        file_put_contents($filename, '42');

        try {
            $compiler = new Compiler();
            $loader = new CompiledLoader(new Tokenizer(), new Reader(), $compiler);
            $executor = new Executor($loader);
            $env = new Env('root');
            $program = $compiler->compile(new MList([
                new Symbol('load'),
                $filename,
            ]));

            $this->assertSame(42, $executor->execute($program, $env));
        } finally {
            unlink($filename);
        }
    }

    public function testLoadsGlobal(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $env->set('value', 123);

        $program = new CompiledProgram([
            OpCode::LOAD_GLOBAL, 0,
            OpCode::RETURN,
        ], ['value'], 0);

        $this->assertSame(123, $executor->execute($program, $env));
    }

    public function testMissingGlobalThrows(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $program = new CompiledProgram([
            OpCode::LOAD_GLOBAL, 0,
            OpCode::RETURN,
        ], ['missing'], 0);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol missing not defined in env');

        $executor->execute($program, $env);
    }

    public function testJumpsToElseBranchWhenConditionIsFalse(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $program = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::JUMP_IF_FALSE, 8,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::JUMP, 10,
            OpCode::LOAD_CONSTANT, 2,
            OpCode::RETURN,
        ], [false, 1, 2], 0);

        $this->assertSame(2, $executor->execute($program, $env));
    }

    public function testJumpsOverElseBranchWhenConditionIsTrue(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $program = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::JUMP_IF_FALSE, 8,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::JUMP, 10,
            OpCode::LOAD_CONSTANT, 2,
            OpCode::RETURN,
        ], [true, 1, 2], 0);

        $this->assertSame(1, $executor->execute($program, $env));
    }

    public function testStoresAndLoadsLocal(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $program = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::STORE_LOCAL, 0,
            OpCode::LOAD_LOCAL, 0,
            OpCode::RETURN,
        ], [42], 1);

        $this->assertSame(42, $executor->execute($program, $env));
    }

    public function testPopsIntermediateValue(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $program = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::POP,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::RETURN,
        ], [1, 2], 0);

        $this->assertSame(2, $executor->execute($program, $env));
    }

    public function testRejectsInvalidLocalSlot(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $program = new CompiledProgram([
            OpCode::LOAD_LOCAL, 1,
            OpCode::RETURN,
        ], [], 1);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('exec: invalid local slot 1');

        $executor->execute($program, $env);
    }

    public function testCallsAdditionalArithmeticCoreFunctionsDirectly(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $cases = [
            [CoreFuncId::ADD, [1, 2], 3],
            [CoreFuncId::SUBTRACT, [10, 3, 2], 5],
            [CoreFuncId::MULTIPLY, [6, 7], 42],
            [CoreFuncId::DIVIDE, [21, 3], 7],
            [CoreFuncId::INTDIV, [22, 4], 5],
            [CoreFuncId::MODULO, [22, 5], 2],
            [CoreFuncId::INC, [4], 5],
            [CoreFuncId::DEC, [4], 3],
            [CoreFuncId::MAX, [2, 8, 3], 8],
            [CoreFuncId::MIN, [2, 8, 3], 2],
        ];

        foreach ($cases as [$coreFuncId, $args, $expected]) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = OpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = OpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = OpCode::RETURN;

            $program = new CompiledProgram($code, $args, 0);

            $this->assertSame($expected, $executor->execute($program, $env));
        }
    }

    public function testCallsComparisonCoreFunctionsDirectly(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $cases = [
            [CoreFuncId::EQUAL, [1, true], true],
            [CoreFuncId::STRICT_EQUAL, [1, true], false],
            [CoreFuncId::NOT_EQUAL, [1, true], false],
            [CoreFuncId::STRICT_NOT_EQUAL, [1, true], true],
            [CoreFuncId::LESS, [1, 2], true],
            [CoreFuncId::LESS_EQUAL, [2, 2], true],
            [CoreFuncId::GREATER, [2, 1], true],
            [CoreFuncId::GREATER_EQUAL, [2, 2], true],
            [CoreFuncId::EQUAL, [new MList([1, 2]), new Vector([1, 2])], true],
            [CoreFuncId::STRICT_EQUAL, [new MList([1, 2]), new Vector([1, 2])], true],
        ];

        foreach ($cases as [$coreFuncId, $args, $expected]) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = OpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = OpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = OpCode::RETURN;

            $program = new CompiledProgram($code, $args, 0);

            $this->assertSame($expected, $executor->execute($program, $env));
        }
    }

    public function testCallsFunction(): void
    {
        $executor = new Executor();
        $env = new Env('root');

        $add = new CoreFunc('+', '', 2, 2, fn (int $a, int $b) => $a + $b);

        $program = new CompiledProgram([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::LOAD_CONSTANT, 2,
            OpCode::CALL, 2,
            OpCode::RETURN,
        ], [$add, 1, 2], 0);

        $this->assertSame(3, $executor->execute($program, $env));
    }
}
