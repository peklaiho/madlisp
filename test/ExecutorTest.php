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
use MadLisp\Hash;
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

    public function testCallsCollectionCoreFunctionsDirectly(): void
    {
        $executor = new Executor();
        $env = new Env('root');
        $run = function (int $coreFuncId, array $args) use ($executor, $env) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = OpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = OpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = OpCode::RETURN;

            return $executor->execute(new CompiledProgram($code, $args, 0), $env);
        };

        $hash = $run(CoreFuncId::HASH, ['a', 1]);
        $this->assertSame(['a' => 1], $hash->getData());
        $this->assertSame([1, 2], $run(CoreFuncId::LIST, [1, 2])->getData());
        $this->assertSame([1, 2], $run(CoreFuncId::VECTOR, [1, 2])->getData());
        $this->assertSame([0, 1, 2], $run(CoreFuncId::RANGE, [3])->getData());
        $this->assertSame([1, 2], $run(CoreFuncId::LTOV, [new MList([1, 2])])->getData());
        $this->assertSame([1, 2], $run(CoreFuncId::VTOL, [new Vector([1, 2])])->getData());
        $this->assertTrue($run(CoreFuncId::EMPTY, [new Vector()]));
        $this->assertTrue($run(CoreFuncId::CONTAINS, [new Vector([1, 2]), 2]));
        $this->assertSame(2, $run(CoreFuncId::GET, [new Vector([1, 2, 3]), 1]));
        $this->assertSame(3, $run(CoreFuncId::LEN, ['abc']));

        $sequence = new Vector([1, 2, 3]);
        $this->assertSame(1, $run(CoreFuncId::CAR, [$sequence]));
        $this->assertSame(1, $run(CoreFuncId::FIRST, [$sequence]));
        $this->assertSame(3, $run(CoreFuncId::LAST, [$sequence]));
        $this->assertSame([1, 2], $run(CoreFuncId::HEAD, [$sequence])->getData());
        $this->assertSame([2, 3], $run(CoreFuncId::CDR, [$sequence])->getData());
        $this->assertSame([2, 3], $run(CoreFuncId::TAIL, [$sequence])->getData());
        $this->assertSame([2], $run(CoreFuncId::SLICE, [$sequence, 1, 1])->getData());

        $add = new CoreFunc('add', '', 2, 2, fn ($a, $b) => $a + $b);
        $double = new CoreFunc('double', '', 1, 1, fn ($a) => $a * 2);
        $isEven = new CoreFunc('even?', '', 1, 1, fn ($a) => $a % 2 == 0);
        $this->assertSame(3, $run(CoreFuncId::APPLY, [$add, new Vector([1, 2])]));
        $this->assertSame([[1, 2], [3, 4]], array_map(
            fn ($chunk) => $chunk->getData(),
            $run(CoreFuncId::CHUNK, [new Vector([1, 2, 3, 4]), 2])->getData()
        ));
        $this->assertSame([1, 2, 3, 4], $run(CoreFuncId::CONCAT, [new MList([1, 2]), new MList([3, 4])])->getData());
        $this->assertSame([1, 2, 3], $run(CoreFuncId::PUSH, [new Vector([1]), 2, 3])->getData());
        $this->assertSame([1, 2, 3], $run(CoreFuncId::CONS, [1, 2, new Vector([3])])->getData());
        $this->assertSame([2, 4], $run(CoreFuncId::MAP, [$double, new Vector([1, 2])])->getData());
        $this->assertSame([3, 5], $run(CoreFuncId::MAP2, [$add, new Vector([1, 2]), new Vector([2, 3])])->getData());
        $this->assertSame(6, $run(CoreFuncId::REDUCE, [$add, new Vector([1, 2, 3])]));
        $this->assertSame([2, 4], $run(CoreFuncId::FILTER, [$isEven, new Vector([1, 2, 3, 4])])->getData());

        $isValueOne = new CoreFunc('value-one?', '', 2, 2, fn ($value, $key) => $value == 1);
        $filteredHash = $run(CoreFuncId::FILTERH, [$isValueOne, $hash]);
        $this->assertSame(['a' => 1], $filteredHash->getData());
        $this->assertSame([3, 2, 1], $run(CoreFuncId::REVERSE, [$sequence])->getData());
        $this->assertTrue($run(CoreFuncId::KEY, [$hash, 'a']));

        $updated = $run(CoreFuncId::SET, [$hash, 'b', 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $updated->getData());
        $this->assertSame(3, $run(CoreFuncId::SET_MUTATE, [$hash, 'c', 3]));
        $this->assertSame(3, $hash->get('c'));
        $withoutB = $run(CoreFuncId::UNSET, [$updated, 'b']);
        $this->assertSame(['a' => 1], $withoutB->getData());
        $this->assertSame(3, $run(CoreFuncId::UNSET_MUTATE, [$hash, 'c']));
        $this->assertSame(['a' => 1], $hash->getData());
        $this->assertSame(['a'], $run(CoreFuncId::KEYS, [$hash])->getData());
        $this->assertSame([1], $run(CoreFuncId::VALUES, [$hash])->getData());
        $this->assertSame(['a' => 1, 'b' => 2], $run(CoreFuncId::ZIP, [new Vector(['a', 'b']), new Vector([1, 2])])->getData());
        $this->assertSame([1, 2, 3], $run(CoreFuncId::SORT, [new Vector([3, 1, 2])])->getData());
    }

    public function testCallsTypeCoreFunctionsDirectly(): void
    {
        $executor = new Executor();
        $env = new Env('root');
        $run = function (int $coreFuncId, array $args) use ($executor, $env) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = OpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = OpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = OpCode::RETURN;

            return $executor->execute(new CompiledProgram($code, $args, 0), $env);
        };

        $function = new CoreFunc('function', '', 0, 0, fn () => null);
        $list = new MList([1]);
        $vector = new Vector([1]);
        $hash = new Hash(['a' => 1]);
        $symbol = new Symbol('value');

        $this->assertTrue($run(CoreFuncId::BOOL, [1]));
        $this->assertSame(1.5, $run(CoreFuncId::FLOAT, ['1.5']));
        $this->assertSame(3, $run(CoreFuncId::INT, ['3']));
        $this->assertSame('value12', $run(CoreFuncId::STR, [$symbol, 1, 2]));
        $this->assertSame('value', $run(CoreFuncId::SYMBOL, ['value'])->getName());
        $this->assertTrue($run(CoreFuncId::NOT, [false]));
        $this->assertSame('function', $run(CoreFuncId::TYPE, [$function]));
        $this->assertTrue($run(CoreFuncId::FUNCTION, [$function]));
        $this->assertFalse($run(CoreFuncId::MACRO, [$function]));
        $this->assertTrue($run(CoreFuncId::LIST_TYPE, [$list]));
        $this->assertTrue($run(CoreFuncId::VECTOR_TYPE, [$vector]));
        $this->assertTrue($run(CoreFuncId::SEQ_TYPE, [$vector]));
        $this->assertTrue($run(CoreFuncId::HASH_TYPE, [$hash]));
        $this->assertTrue($run(CoreFuncId::SYMBOL_TYPE, [$symbol]));
        $this->assertTrue($run(CoreFuncId::OBJECT_TYPE, [new \stdClass()]));

        $resource = fopen('php://memory', 'r');
        $this->assertTrue($run(CoreFuncId::RESOURCE_TYPE, [$resource]));
        fclose($resource);

        $this->assertTrue($run(CoreFuncId::BOOL_TYPE, [false]));
        $this->assertTrue($run(CoreFuncId::TRUE, [1]));
        $this->assertTrue($run(CoreFuncId::FALSE, [null]));
        $this->assertTrue($run(CoreFuncId::NULL_TYPE, [null]));
        $this->assertTrue($run(CoreFuncId::INT_TYPE, [1]));
        $this->assertTrue($run(CoreFuncId::FLOAT_TYPE, [1.0]));
        $this->assertTrue($run(CoreFuncId::STR_TYPE, ['value']));
        $this->assertTrue($run(CoreFuncId::ZERO, [0]));
        $this->assertTrue($run(CoreFuncId::ONE, [1]));
        $this->assertTrue($run(CoreFuncId::EVEN, [4]));
        $this->assertTrue($run(CoreFuncId::ODD, [3]));
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
