<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CompiledProgram;
use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Executor;
use MadLisp\MadLispException;
use MadLisp\OpCode;

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
