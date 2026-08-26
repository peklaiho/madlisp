<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\IrCompiledLoader;
use MadLisp\IrCompiledProgram;
use MadLisp\IrCompiler;
use MadLisp\CoreFunc;
use MadLisp\IrCoreFuncId;
use MadLisp\Env;
use MadLisp\IrExecutor;
use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\IrOpCode;
use MadLisp\Reader;
use MadLisp\Symbol;
use MadLisp\Tokenizer;
use MadLisp\Vector;

class IrExecutorTest extends TestCase
{
    public function testLoadsConstant(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::RETURN,
        ], [42], 0);

        $this->assertSame(42, $executor->execute($program, $env));
    }

    public function testExecutesProgramInChildFrame(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');
        $child = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::RETURN,
        ], [42], 0);
        $program = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::EXECUTE_PROGRAM,
            IrOpCode::RETURN,
        ], [$child], 0);

        $this->assertSame(42, $executor->execute($program, $env));
    }

    public function testLoadsFileInChildFrame(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'madlisp-');
        file_put_contents($filename, '42');

        try {
            $compiler = new IrCompiler();
            $loader = new IrCompiledLoader(new Tokenizer(), new Reader(), $compiler);
            $executor = new IrExecutor($loader);
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
        $executor = new IrExecutor();
        $env = new Env('root');

        $env->set('value', 123);

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_GLOBAL, 0,
            IrOpCode::RETURN,
        ], ['value'], 0);

        $this->assertSame(123, $executor->execute($program, $env));
    }

    public function testMissingGlobalThrows(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_GLOBAL, 0,
            IrOpCode::RETURN,
        ], ['missing'], 0);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol missing not defined in env');

        $executor->execute($program, $env);
    }

    public function testJumpsToElseBranchWhenConditionIsFalse(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::JUMP_IF_FALSE, 8,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::JUMP, 10,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::RETURN,
        ], [false, 1, 2], 0);

        $this->assertSame(2, $executor->execute($program, $env));
    }

    public function testJumpsOverElseBranchWhenConditionIsTrue(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::JUMP_IF_FALSE, 8,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::JUMP, 10,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::RETURN,
        ], [true, 1, 2], 0);

        $this->assertSame(1, $executor->execute($program, $env));
    }

    public function testStoresAndLoadsLocal(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::STORE_LOCAL, 0,
            IrOpCode::LOAD_LOCAL, 0,
            IrOpCode::RETURN,
        ], [42], 1);

        $this->assertSame(42, $executor->execute($program, $env));
    }

    public function testPopsIntermediateValue(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::POP,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::RETURN,
        ], [1, 2], 0);

        $this->assertSame(2, $executor->execute($program, $env));
    }

    public function testRejectsInvalidLocalSlot(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_LOCAL, 1,
            IrOpCode::RETURN,
        ], [], 1);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('exec: invalid local slot 1');

        $executor->execute($program, $env);
    }

    public function testCallsAdditionalArithmeticCoreFunctionsDirectly(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $cases = [
            [IrCoreFuncId::ADD, [1, 2], 3],
            [IrCoreFuncId::SUBTRACT, [10, 3, 2], 5],
            [IrCoreFuncId::MULTIPLY, [6, 7], 42],
            [IrCoreFuncId::DIVIDE, [21, 3], 7],
            [IrCoreFuncId::INTDIV, [22, 4], 5],
            [IrCoreFuncId::MODULO, [22, 5], 2],
            [IrCoreFuncId::INC, [4], 5],
            [IrCoreFuncId::DEC, [4], 3],
            [IrCoreFuncId::MAX, [2, 8, 3], 8],
            [IrCoreFuncId::MIN, [2, 8, 3], 2],
        ];

        foreach ($cases as [$coreFuncId, $args, $expected]) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = IrOpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = IrOpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = IrOpCode::RETURN;

            $program = new IrCompiledProgram($code, $args, 0);

            $this->assertSame($expected, $executor->execute($program, $env));
        }
    }

    public function testCallsComparisonCoreFunctionsDirectly(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $cases = [
            [IrCoreFuncId::EQUAL, [1, true], true],
            [IrCoreFuncId::STRICT_EQUAL, [1, true], false],
            [IrCoreFuncId::NOT_EQUAL, [1, true], false],
            [IrCoreFuncId::STRICT_NOT_EQUAL, [1, true], true],
            [IrCoreFuncId::LESS, [1, 2], true],
            [IrCoreFuncId::LESS_EQUAL, [2, 2], true],
            [IrCoreFuncId::GREATER, [2, 1], true],
            [IrCoreFuncId::GREATER_EQUAL, [2, 2], true],
            [IrCoreFuncId::EQUAL, [new MList([1, 2]), new Vector([1, 2])], true],
            [IrCoreFuncId::STRICT_EQUAL, [new MList([1, 2]), new Vector([1, 2])], true],
        ];

        foreach ($cases as [$coreFuncId, $args, $expected]) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = IrOpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = IrOpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = IrOpCode::RETURN;

            $program = new IrCompiledProgram($code, $args, 0);

            $this->assertSame($expected, $executor->execute($program, $env));
        }
    }

    public function testCallsCollectionCoreFunctionsDirectly(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');
        $run = function (int $coreFuncId, array $args) use ($executor, $env) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = IrOpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = IrOpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = IrOpCode::RETURN;

            return $executor->execute(new IrCompiledProgram($code, $args, 0), $env);
        };

        $hash = $run(IrCoreFuncId::HASH, ['a', 1]);
        $this->assertSame(['a' => 1], $hash->getData());
        $this->assertSame([1, 2], $run(IrCoreFuncId::LIST, [1, 2])->getData());
        $this->assertSame([1, 2], $run(IrCoreFuncId::VECTOR, [1, 2])->getData());
        $this->assertSame([0, 1, 2], $run(IrCoreFuncId::RANGE, [3])->getData());
        $this->assertSame([1, 2], $run(IrCoreFuncId::LTOV, [new MList([1, 2])])->getData());
        $this->assertSame([1, 2], $run(IrCoreFuncId::VTOL, [new Vector([1, 2])])->getData());
        $this->assertTrue($run(IrCoreFuncId::EMPTY, [new Vector()]));
        $this->assertTrue($run(IrCoreFuncId::CONTAINS, [new Vector([1, 2]), 2]));
        $this->assertSame(2, $run(IrCoreFuncId::GET, [new Vector([1, 2, 3]), 1]));
        $this->assertSame(3, $run(IrCoreFuncId::LEN, ['abc']));

        $sequence = new Vector([1, 2, 3]);
        $this->assertSame(1, $run(IrCoreFuncId::CAR, [$sequence]));
        $this->assertSame(1, $run(IrCoreFuncId::FIRST, [$sequence]));
        $this->assertSame(3, $run(IrCoreFuncId::LAST, [$sequence]));
        $this->assertSame([1, 2], $run(IrCoreFuncId::HEAD, [$sequence])->getData());
        $this->assertSame([2, 3], $run(IrCoreFuncId::CDR, [$sequence])->getData());
        $this->assertSame([2, 3], $run(IrCoreFuncId::TAIL, [$sequence])->getData());
        $this->assertSame([2], $run(IrCoreFuncId::SLICE, [$sequence, 1, 1])->getData());

        $add = new CoreFunc('add', '', 2, 2, fn ($a, $b) => $a + $b);
        $double = new CoreFunc('double', '', 1, 1, fn ($a) => $a * 2);
        $isEven = new CoreFunc('even?', '', 1, 1, fn ($a) => $a % 2 == 0);
        $this->assertSame(3, $run(IrCoreFuncId::APPLY, [$add, new Vector([1, 2])]));
        $this->assertSame([[1, 2], [3, 4]], array_map(
            fn ($chunk) => $chunk->getData(),
            $run(IrCoreFuncId::CHUNK, [new Vector([1, 2, 3, 4]), 2])->getData()
        ));
        $this->assertSame([1, 2, 3, 4], $run(IrCoreFuncId::CONCAT, [new MList([1, 2]), new MList([3, 4])])->getData());
        $this->assertSame([1, 2, 3], $run(IrCoreFuncId::PUSH, [new Vector([1]), 2, 3])->getData());
        $this->assertSame([1, 2, 3], $run(IrCoreFuncId::CONS, [1, 2, new Vector([3])])->getData());
        $this->assertSame([2, 4], $run(IrCoreFuncId::MAP, [$double, new Vector([1, 2])])->getData());
        $this->assertSame([3, 5], $run(IrCoreFuncId::MAP2, [$add, new Vector([1, 2]), new Vector([2, 3])])->getData());
        $this->assertSame(6, $run(IrCoreFuncId::REDUCE, [$add, new Vector([1, 2, 3])]));
        $this->assertSame([2, 4], $run(IrCoreFuncId::FILTER, [$isEven, new Vector([1, 2, 3, 4])])->getData());

        $isValueOne = new CoreFunc('value-one?', '', 2, 2, fn ($value, $key) => $value == 1);
        $filteredHash = $run(IrCoreFuncId::FILTERH, [$isValueOne, $hash]);
        $this->assertSame(['a' => 1], $filteredHash->getData());
        $this->assertSame([3, 2, 1], $run(IrCoreFuncId::REVERSE, [$sequence])->getData());
        $this->assertTrue($run(IrCoreFuncId::KEY, [$hash, 'a']));

        $updated = $run(IrCoreFuncId::SET, [$hash, 'b', 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $updated->getData());
        $this->assertSame(3, $run(IrCoreFuncId::SET_MUTATE, [$hash, 'c', 3]));
        $this->assertSame(3, $hash->get('c'));
        $withoutB = $run(IrCoreFuncId::UNSET, [$updated, 'b']);
        $this->assertSame(['a' => 1], $withoutB->getData());
        $this->assertSame(3, $run(IrCoreFuncId::UNSET_MUTATE, [$hash, 'c']));
        $this->assertSame(['a' => 1], $hash->getData());
        $this->assertSame(['a'], $run(IrCoreFuncId::KEYS, [$hash])->getData());
        $this->assertSame([1], $run(IrCoreFuncId::VALUES, [$hash])->getData());
        $this->assertSame(['a' => 1, 'b' => 2], $run(IrCoreFuncId::ZIP, [new Vector(['a', 'b']), new Vector([1, 2])])->getData());
        $this->assertSame([1, 2, 3], $run(IrCoreFuncId::SORT, [new Vector([3, 1, 2])])->getData());
    }

    public function testCollectionCoreFunctionsExecuteCompiledCallbacks(): void
    {
        $compiler = new IrCompiler();
        $reader = new Reader();
        $tokenizer = new Tokenizer();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));
        $env->set('*', new CoreFunc('*', '', 2, -1, fn (...$args) => array_product($args)));
        $env->set('>', new CoreFunc('>', '', 2, 2, fn ($a, $b) => $a > $b));

        $run = function (string $source) use ($compiler, $reader, $tokenizer, $executor, $env) {
            $ast = $reader->read($tokenizer->tokenize($source));
            return $executor->execute($compiler->compile($ast), $env);
        };

        $this->assertSame([2, 4, 6], $run('(map (fn (x) (* x 2)) [1 2 3])')->getData());
        $this->assertSame([4, 6], $run('(map2 (fn (a b) (+ a b)) [1 2] [3 4])')->getData());
        $this->assertSame(6, $run('(reduce (fn (a b) (+ a b)) [1 2 3])'));
        $this->assertSame([2, 3], $run('(filter (fn (x) (> x 1)) [1 2 3])')->getData());
        $this->assertSame(['b' => 2], $run('(filterh (fn (value key) (> value 1)) {"a" 1 "b" 2})')->getData());
        $this->assertSame(6, $run('(apply (fn (a b c) (+ a (+ b c))) [1 2 3])'));
    }

    public function testNestedCompiledCollectionOperationsKeepIndependentState(): void
    {
        $compiler = new IrCompiler();
        $reader = new Reader();
        $tokenizer = new Tokenizer();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));
        $env->set('*', new CoreFunc('*', '', 2, -1, fn (...$args) => array_product($args)));

        $run = function (string $source) use ($compiler, $reader, $tokenizer, $executor, $env) {
            $ast = $reader->read($tokenizer->tokenize($source));
            return $executor->execute($compiler->compile($ast), $env);
        };

        $nestedReduce = $run('(map (fn (x) (reduce (fn (a b) (+ a b)) [x 2])) [1 3])');
        $this->assertSame([3, 5], $nestedReduce->getData());

        $nestedMap = $run('(reduce (fn (a b) (first (map (fn (x) (+ x b)) [a]))) [1 2 3])');
        $this->assertSame(6, $nestedMap);
    }

    public function testCallsTypeCoreFunctionsDirectly(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');
        $run = function (int $coreFuncId, array $args) use ($executor, $env) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = IrOpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = IrOpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = IrOpCode::RETURN;

            return $executor->execute(new IrCompiledProgram($code, $args, 0), $env);
        };

        $function = new CoreFunc('function', '', 0, 0, fn () => null);
        $list = new MList([1]);
        $vector = new Vector([1]);
        $hash = new Hash(['a' => 1]);
        $symbol = new Symbol('value');

        $this->assertTrue($run(IrCoreFuncId::BOOL, [1]));
        $this->assertSame(1.5, $run(IrCoreFuncId::FLOAT, ['1.5']));
        $this->assertSame(3, $run(IrCoreFuncId::INT, ['3']));
        $this->assertSame('value12', $run(IrCoreFuncId::STR, [$symbol, 1, 2]));
        $this->assertSame('value', $run(IrCoreFuncId::SYMBOL, ['value'])->getName());
        $this->assertTrue($run(IrCoreFuncId::NOT, [false]));
        $this->assertSame('function', $run(IrCoreFuncId::TYPE, [$function]));
        $this->assertTrue($run(IrCoreFuncId::FUNCTION, [$function]));
        $this->assertFalse($run(IrCoreFuncId::MACRO, [$function]));
        $this->assertTrue($run(IrCoreFuncId::LIST_TYPE, [$list]));
        $this->assertTrue($run(IrCoreFuncId::VECTOR_TYPE, [$vector]));
        $this->assertTrue($run(IrCoreFuncId::SEQ_TYPE, [$vector]));
        $this->assertTrue($run(IrCoreFuncId::HASH_TYPE, [$hash]));
        $this->assertTrue($run(IrCoreFuncId::SYMBOL_TYPE, [$symbol]));
        $this->assertTrue($run(IrCoreFuncId::OBJECT_TYPE, [new \stdClass()]));

        $resource = fopen('php://memory', 'r');
        $this->assertTrue($run(IrCoreFuncId::RESOURCE_TYPE, [$resource]));
        fclose($resource);

        $this->assertTrue($run(IrCoreFuncId::BOOL_TYPE, [false]));
        $this->assertTrue($run(IrCoreFuncId::TRUE, [1]));
        $this->assertTrue($run(IrCoreFuncId::FALSE, [null]));
        $this->assertTrue($run(IrCoreFuncId::NULL_TYPE, [null]));
        $this->assertTrue($run(IrCoreFuncId::INT_TYPE, [1]));
        $this->assertTrue($run(IrCoreFuncId::FLOAT_TYPE, [1.0]));
        $this->assertTrue($run(IrCoreFuncId::STR_TYPE, ['value']));
        $this->assertTrue($run(IrCoreFuncId::ZERO, [0]));
        $this->assertTrue($run(IrCoreFuncId::ONE, [1]));
        $this->assertTrue($run(IrCoreFuncId::EVEN, [4]));
        $this->assertTrue($run(IrCoreFuncId::ODD, [3]));
    }

    public function testCallsStringCoreFunctionsDirectly(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');
        $run = function (int $coreFuncId, array $args) use ($executor, $env) {
            $code = [];
            foreach ($args as $index => $arg) {
                $code[] = IrOpCode::LOAD_CONSTANT;
                $code[] = $index;
            }
            $code[] = IrOpCode::CALL_CORE;
            $code[] = $coreFuncId;
            $code[] = count($args);
            $code[] = IrOpCode::RETURN;

            return $executor->execute(new IrCompiledProgram($code, $args, 0), $env);
        };

        $this->assertSame('hello', $run(IrCoreFuncId::TRIM, ['  hello  ']));
        $this->assertSame('hello  ', $run(IrCoreFuncId::LTRIM, ['  hello  ']));
        $this->assertSame('  hello', $run(IrCoreFuncId::RTRIM, ['  hello  ']));
        $this->assertSame('HELLO', $run(IrCoreFuncId::UPCASE, ['hello']));
        $this->assertSame('hello', $run(IrCoreFuncId::LOWCASE, ['HELLO']));
        $this->assertSame(2, $run(IrCoreFuncId::STRPOS, ['hello', 'l']));
        $this->assertSame(3, $run(IrCoreFuncId::STRIPOS, ['Hello', 'L', 3]));
        $this->assertSame('ell', $run(IrCoreFuncId::SUBSTR, ['hello', 1, 3]));
        $this->assertSame('hello world', $run(IrCoreFuncId::REPLACE, ['hello there', 'there', 'world']));
        $this->assertSame(['a', 'b', 'c'], $run(IrCoreFuncId::SPLIT, [',', 'a,b,c'])->getData());
        $this->assertSame('a, b, c', $run(IrCoreFuncId::JOIN, [', ', 'a', 'b', 'c']));
        $this->assertSame('Hello, World!', $run(IrCoreFuncId::FORMAT, ['%s, %s!', 'Hello', 'World']));
        $this->assertTrue($run(IrCoreFuncId::PREFIX, ['hello', 'he']));
        $this->assertTrue($run(IrCoreFuncId::SUFFIX, ['hello', 'lo']));
        $this->assertSame(-1, $run(IrCoreFuncId::STRCMP, ['a', 'b']));
        $this->assertSame(0, $run(IrCoreFuncId::STRCASECMP, ['Hello', 'hello']));
        $this->assertSame(-1, $run(IrCoreFuncId::STRNATCMP, ['file2', 'file10']));
        $this->assertSame(0, $run(IrCoreFuncId::STRNATCASECMP, ['File2', 'file2']));
    }

    public function testCallsFunction(): void
    {
        $executor = new IrExecutor();
        $env = new Env('root');

        $add = new CoreFunc('+', '', 2, 2, fn (int $a, int $b) => $a + $b);

        $program = new IrCompiledProgram([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::CALL, 2,
            IrOpCode::RETURN,
        ], [$add, 1, 2], 0);

        $this->assertSame(3, $executor->execute($program, $env));
    }
}
