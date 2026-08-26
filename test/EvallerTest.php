<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Collection;
use MadLisp\IrCompiler;
use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Symbol;
use MadLisp\Tokenizer;
use MadLisp\UserFunc;
use MadLisp\Vector;
use MadLisp\Lib\Collections;
use MadLisp\Lib\Compare;
use MadLisp\Lib\Core;
use MadLisp\Lib\Math;
use MadLisp\Lib\Strings;
use MadLisp\Lib\Types;

class EvallerTest extends TestCase
{
    public function testEvalAtom()
    {
        // Test values that are not evaluated (they are returned unchanged)

        list($env, $evaller) = $this->getEnvAndEvaller();

        $this->assertSame(true, $evaller->eval(true, $env));
        $this->assertSame(false, $evaller->eval(false, $env));
        $this->assertSame(null, $evaller->eval(null, $env));
        $this->assertSame(123, $evaller->eval(123, $env));
        $this->assertSame(4.56, $evaller->eval(4.56, $env));
        $this->assertSame('abc', $evaller->eval('abc', $env));

        $obj = new \stdClass();
        $this->assertSame($obj, $evaller->eval($obj, $env));

        $fn = fn ($a) => $a;
        $this->assertSame($fn, $evaller->eval($fn, $env));

        $fn = $env->get('+');
        $this->assertSame($fn, $evaller->eval($fn, $env));
    }

    public function testEvalSymbol()
    {
        // Evaluating a symbol is a lookup from env

        list($env, $evaller) = $this->getEnvAndEvaller();

        $env->set('abc', 123);
        $env->set('efg', new MList([1, 2, 3]));

        $result = $evaller->eval(new Symbol('abc'), $env);
        $this->assertSame(123, $result);

        $result = $evaller->eval(new Symbol('efg'), $env);
        $this->assertInstanceOf(MList::class, $result);
        $this->assertSame([1, 2, 3], $result->getData());
    }

    public function testEvalSymbolNotFound()
    {
        // Evaluating a symbol that is not defined will throw an exception

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol abc not defined in env');

        list($env, $evaller) = $this->getEnvAndEvaller();
        $evaller->eval(new Symbol('abc'), $env);
    }

    public function testEvalEmptyList()
    {
        // Empty list is not changed

        list($env, $evaller) = $this->getEnvAndEvaller();
        $result = $evaller->eval(new MList(), $env);

        $this->assertInstanceOf(MList::class, $result);
        $this->assertCount(0, $result->getData());
    }

    public function testEvalVector()
    {
        // Evaluating a vector returns new vector where each element is evaluated

        list($env, $evaller) = $this->getEnvAndEvaller();

        // [1 2 (+ 1 2) [4 5 (+ 2 4)]]
        $input = new Vector([
            1,
            2,
            new MList([new Symbol('+'), 1, 2]),
            new Vector([
                4,
                5,
                new MList([new Symbol('+'), 2, 4])
            ])
        ]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Vector::class, $result);
        $data = $result->getData();
        $this->assertCount(4, $data);
        $this->assertSame(1, $data[0]);
        $this->assertSame(2, $data[1]);
        $this->assertSame(3, $data[2]);

        $this->assertInstanceOf(Vector::class, $data[3]);
        $data2 = $data[3]->getData();
        $this->assertCount(3, $data2);
        $this->assertSame(4, $data2[0]);
        $this->assertSame(5, $data2[1]);
        $this->assertSame(6, $data2[2]);
    }

    public function testEvalVectorSymbolLookup()
    {
        // Test symbol lookup works inside a vector

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['def', 'foo', 10]);
        $evaller->eval($input, $env);

        $input = $this->buildForm(['foo', ['+', 'foo', 1]], Vector::class);
        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Vector::class, $result);
        $data = $result->getData();
        $this->assertCount(2, $data);
        $this->assertSame(10, $data[0]);
        $this->assertSame(11, $data[1]);
    }

    public function testEvalHash()
    {
        // Evaluating a hash-map returns a new hash-map where the values are evaluated

        list($env, $evaller) = $this->getEnvAndEvaller();

        // { "aa": (+ 1 2) "bb": { "cc": (+ 3 4) } }
        $input = new Hash([
            'aa' => new MList([new Symbol('+'), 1, 2]),
            'bb' => new Hash([
                'cc' => new MList([new Symbol('+'), 3, 4])
            ])
        ]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Hash::class, $result);
        $data = $result->getData();
        $this->assertCount(2, $data);
        $this->assertSame(3, $data['aa']);

        $this->assertInstanceOf(Hash::class, $data['bb']);
        $data2 = $data['bb']->getData();
        $this->assertCount(1, $data2);
        $this->assertSame(7, $data2['cc']);
    }

    public function testEvalHashSymbolLookup()
    {
        // Test symbol lookup works inside a hash

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['def', 'foo', 10]);
        $evaller->eval($input, $env);

        $input = new Hash([
            'foo' => new Symbol('foo'),
            'nested' => new Vector([
                new Symbol('foo')
            ])
        ]);
        $result = $evaller->eval($input, $env);

        // Also tests that the hash key 'foo' was not changed

        $this->assertInstanceOf(Hash::class, $result);
        $data = $result->getData();
        $this->assertCount(2, $data);
        $this->assertSame(10, $data['foo']);
        $nested = $data['nested'];
        $this->assertInstanceOf(Vector::class, $nested);
        $data = $nested->getData();
        $this->assertCount(1, $data);
        $this->assertSame(10, $data[0]);
    }

    public function testEvalList()
    {
        // Test simple list evaluation

        list($env, $evaller) = $this->getEnvAndEvaller();

        // (+ 1 2 (* 4 5))
        $input = new MList([
            new Symbol('+'),
            1,
            2,
            new MList([
                new Symbol('*'),
                4,
                5
            ])
        ]);

        $result = $evaller->eval($input, $env);

        $this->assertSame(23, $result);
    }

    public function testEvalListNotFunc()
    {
        // Test that exception is thrown when evaluating a list
        // where the first item is not a function.

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('eval: first item of list is not function');

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([1, 2, 3]);

        $evaller->eval($input, $env);
    }

    public function testDebug()
    {
        list(, $evaller) = $this->getEnvAndEvaller();

        $this->assertFalse($evaller->getDebug());
        $evaller->setDebug(true);
        $this->assertTrue($evaller->getDebug());
    }

    // ---
    // Special form: and
    // ---

    public function andProvider(): array
    {
        return [
            [[], true],
            [[1, 2, 0, 3], 0],
            [[1, 2, 3], 3]
        ];
    }

    /**
     * @dataProvider andProvider
     */
    public function testAnd(array $args, $expected)
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList(array_merge([new Symbol('and')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    // Test that if first arg is falsy, second arg is never evaluated
    public function testAndShortCircuit()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['and', false, ['throw', '"error"']]);

        $this->assertFalse($evaller->eval($input, $env));
    }

    // ---
    // Special forms: case, case-strict
    // ---

    public function caseProvider(): array
    {
        return [
            // basic match
            [
                [
                    new MList([new Symbol('+'), 1, 2]),
                    new MList([2, 'two']),
                    new MList([3, 'three']),
                    new MList([4, 'four'])
                ],
                'three'
            ],
            // non-strict case matches number as a string
            [
                [
                    new MList([new Symbol('+'), 1, 2]),
                    new MList(['2', 'two']),
                    new MList(['3', 'three']),
                    new MList(['4', 'four'])
                ],
                'three'
            ],
            // test else
            [
                [
                    new MList([new Symbol('+'), 2, 3]),
                    new MList([2, 'two']),
                    new MList([3, 'three']),
                    new MList([4, 'four']),
                    new MList([new Symbol('else'), 'other'])
                ],
                'other'
            ]
        ];
    }

    /**
     * @dataProvider caseProvider
     */
    public function testCase(array $args, $expected)
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList(array_merge([new Symbol('case')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function caseStrictProvider(): array
    {
        return [
            // basic match
            [
                [
                    new MList([new Symbol('+'), 1, 2]),
                    new MList([2, 'two']),
                    new MList([3, 'three']),
                    new MList([4, 'four'])
                ],
                'three'
            ],
            // strict case does NOT match number as a string
            // check that the non-matching case returns null
            [
                [
                    new MList([new Symbol('+'), 1, 2]),
                    new MList(['2', 'two']),
                    new MList(['3', 'three']),
                    new MList(['4', 'four'])
                ],
                null
            ],
            // test else
            [
                [
                    new MList([new Symbol('+'), 2, 3]),
                    new MList([2, 'two']),
                    new MList([3, 'three']),
                    new MList([4, 'four']),
                    new MList([new Symbol('else'), 'other'])
                ],
                'other'
            ]
        ];
    }

    /**
     * @dataProvider caseStrictProvider
     */
    public function testCaseStrict(array $args, $expected)
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList(array_merge([new Symbol('case-strict')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function testCaseEvaluatesOnlySelectedClause()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // The bodies of non-matching clauses must not be evaluated.
        // The selected clause may contain multiple expressions.

        foreach (['case', 'case-strict'] as $type) {
            $input = $this->buildForm([
                $type, ['//', 4, 2],
                [1, ['throw', '"earlier clause was evaluated"']],
                [2, ['def', 'value', 10], ['+', 'value', 5]],
                [3, ['throw', '"later clause was evaluated"']],
            ]);

            $this->assertSame(15, $evaller->eval($input, $env));
        }
    }

    public function caseErrorProvider(): array
    {
        $tests = [];

        // Test both case and case-strict
        foreach (['case', 'case-strict'] as $type) {
            $tests[] = [
                [$type],
                "$type requires at least 2 arguments"
            ];
            $tests[] = [
                [$type, 1],
                "$type requires at least 2 arguments"
            ];
            $tests[] = [
                [$type, 1, 2],
                "argument to $type is not seq"
            ];
            $tests[] = [
                [$type, 1, [2]],
                "clause for $type requires at least 2 arguments"
            ];
        }

        return $tests;
    }

    /**
     * @dataProvider caseErrorProvider
     */
    public function testCaseExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: cond
    // ---

    public function condProvider(): array
    {
        return [
            [
                [
                    $this->buildForm([['=', 'n', 2], '"two"']),
                    $this->buildForm([['=', 'n', 4], '"four"']),
                    $this->buildForm([['=', 'n', 6], '"six"']),
                ],
                'four'
            ],
            [
                [
                    $this->buildForm([['=', 'n', 1], '"one"']),
                    $this->buildForm([['=', 'n', 3], '"three"']),
                    $this->buildForm([['=', 'n', 5], '"five"']),
                    $this->buildForm(['else', '"other"']),
                ],
                'other'
            ]
        ];
    }

    /**
     * @dataProvider condProvider
     */
    public function testCond(array $args, $expected)
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $env->set('n', 4);

        $input = new MList(array_merge([new Symbol('cond')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function testCondEvaluatesOnlySelectedClause()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // The bodies of non-matching clauses must not be evaluated.
        // Because cond evalutes the clauses in order, the conditions
        // for later clauses after a match must not be evaluated either.
        // The selected clause may contain multiple expressions.

        $input = $this->buildForm([
            'cond',
            [false, ['throw', '"earlier clause was evaluated"']],
            [['<', 1, 2], ['def', 'value', 10], ['+', 'value', 5]],
            [['throw', '"later condition was evaluated"'], 0],
        ]);

        $this->assertSame(15, $evaller->eval($input, $env));
    }

    public function testCondReturnsNullWithoutMatch()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['cond', [false, 1], [null, 2]]);

        $this->assertNull($evaller->eval($input, $env));
    }

    public function condErrorProvider(): array
    {
        return [
            [
                ['cond'],
                'cond requires at least 1 argument'
            ],
            [
                ['cond', 1],
                'argument to cond is not seq'
            ],
            [
                ['cond', [1]],
                'clause for cond requires at least 2 arguments'
            ],
        ];
    }

    /**
     * @dataProvider condErrorProvider
     */
    public function testCondExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: def
    // ---

    public function testDef()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('def'), new Symbol('abc'), 123]);

        $evaller->eval($input, $env);

        $this->assertSame($env->get('abc'), 123);
    }

    public function defErrorProvider(): array
    {
        return [
            [
                ['def', 1],
                'def requires exactly 2 arguments'
            ],
            [
                ['def', 1, 2, 3],
                'def requires exactly 2 arguments'
            ],
            [
                ['def', 1, 2],
                'first argument to def is not symbol'
            ],
            [
                ['def', '__FILE__', 'abc'],
                'attempt to def reserved symbol __FILE__'
            ],
            [
                ['def', '__DIR__', 'abc'],
                'attempt to def reserved symbol __DIR__'
            ],
        ];
    }

    /**
     * @dataProvider defErrorProvider
     */
    public function testDefExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: do
    // ---

    public function testDo()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Test that func gets called
        $funcCalls = 0;
        $func = new CoreFunc('test', '', 0, 0, function () use (&$funcCalls) {
            $funcCalls++;
        });

        $input = new MList([new Symbol('do'), new MList([$func]), new MList([new Symbol('+'), 3, 4])]);

        $result = $evaller->eval($input, $env);

        $this->assertSame(1, $funcCalls);
        $this->assertSame(7, $result);
    }

    // ---
    // Special form: env
    // ---

    public function testEnv()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('env')]);

        $this->assertSame($env, $evaller->eval($input, $env));
    }

    public function testEnvInsideFn()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Test that env returns the function invocation environment

        // ((fn (x) (env)) 10)
        $input = $this->buildForm([['fn', ['x'], ['env']], 10]);
        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Env::class, $result);
        $this->assertNotSame($env, $result);
        $this->assertSame($env, $result->getParent());
        $this->assertSame(10, $result->get('x'));
    }

    public function testEnvInsideLet()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Test that env returns the environment of let

        // (let (x 10) (env))
        $input = $this->buildForm(['let', ['x', 10], ['env']]);
        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Env::class, $result);
        $this->assertNotSame($env, $result);
        $this->assertSame($env, $result->getParent());
        $this->assertSame(10, $result->get('x'));
    }

    public function testEnvUsesDefiningScope()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Verify that a function uses the environment where it was defined,
        // not the environment of the call site.

        // (let (x 10)
        //   (def get-env (fn () (env)))
        //   (let (x 20)
        //     (get-env)))
        $input = $this->buildForm(
            ['let', ['x', 10],
                ['def', 'get-env', ['fn', [], ['env']]],
                ['let', ['x', 20],
                    ['get-env']]]
        );

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Env::class, $result);
        $this->assertNotSame($env, $result);
        $this->assertSame($env, $result->getParent()->getParent());
        $this->assertSame(10, $result->get('x'));
    }

    public function testEnvWithArg()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('env does not take arguments');

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['env', 1]);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: eval
    // ---

    public function testBasicEval()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (eval (quote (+ 1 2)))
        $input = $this->buildForm(['eval', ['quote', ['+', 1, 2]]]);

        $this->assertSame(3, $evaller->eval($input, $env));
    }

    public function testEvalSeesLocalEnv()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (let (x 10) (eval (quote (+ x 5))))
        $input = $this->buildForm(['let', ['x', 10], ['eval', ['quote', ['+', 'x', 5]]]]);

        $result = $evaller->eval($input, $env);

        $this->assertSame(15, $result);
    }

    public function evalErrorProvider(): array
    {
        return [
            [['eval']],
            [['eval', 1, 2]],
        ];
    }

    /**
     * @dataProvider evalErrorProvider
     */
    public function testEvalExceptions(array $data)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('eval requires exactly 1 argument');

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special forms: fn, macro
    // ---

    public function testCreateFn()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('fn'), new MList([]), new MList([])]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(UserFunc::class, $result);
        $this->assertFalse($result->isMacro());
    }

    public function testCallFn()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // ((fn (a b) (+ a b)) 3 4)
        $input = $this->buildForm([['fn', ['a', 'b'], ['+', 'a', 'b']], 3, 4]);

        $result = $evaller->eval($input, $env);

        $this->assertSame(7, $result);
    }

    public function testFnCapturesValue()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Define a function that captures a value from its defining scope
        // (def quadrupler (let (a 4) (fn (b) (* a b))))
        $input = $this->buildForm(['def', 'quadrupler', ['let', ['a', 4], ['fn', ['b'], ['*', 'a', 'b']]]]);
        $evaller->eval($input, $env);

        // Verify that the function still uses the captured value outside of the let that defined it
        // (quadrupler 3)
        $input = $this->buildForm(['quadrupler', 3]);
        $result = $evaller->eval($input, $env);
        $this->assertSame(12, $result);

        // Verify that a caller's binding with the same name does not
        // override the value captured from the defining scope
        // (let (a 100) (quadrupler 3))
        $input = $this->buildForm(['let', ['a', 100], ['quadrupler', 3]]);
        $result = $evaller->eval($input, $env);
        $this->assertSame(12, $result);
    }

    // Test function that returns a function
    public function testFnReturnsFn()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (def multiplier (fn (a) (fn (b) (* a b))))
        $input = $this->buildForm(['def', 'multiplier', ['fn', ['a'], ['fn', ['b'], ['*', 'a', 'b']]]]);
        $evaller->eval($input, $env);

        // ((multiplier 3) 4)
        $input = $this->buildForm([['multiplier', 3], 4]);
        $result = $evaller->eval($input, $env);
        $this->assertSame(12, $result);

        // ((multiplier 6) 7)
        $input = $this->buildForm([['multiplier', 6], 7]);
        $result = $evaller->eval($input, $env);
        $this->assertSame(42, $result);
    }

    // Test function that takes variable number of arguments
    public function testFnVariableArgs()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (def varargs (fn (a b & c) c))
        $input = $this->buildForm(['def', 'varargs', ['fn', ['a', 'b', '&', 'c'], 'c']]);
        $evaller->eval($input, $env);

        // (varargs 1 2 3 4 5)
        $input = $this->buildForm(['varargs', 1, 2, 3, 4, 5]);
        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame([3, 4, 5], $result->getData());
    }

    public function fibonacciProvider(): array
    {
        return [
            [0, 0],
            [1, 1],
            [2, 1],
            [3, 2],
            [4, 3],
            [5, 5],
            [6, 8],
            [7, 13],
            [8, 21],
            [9, 34],
            [10, 55],
        ];
    }

    /**
     * @dataProvider fibonacciProvider
     */
    public function testRecursiveFn(int $n, int $expected)
    {
        // Test recursive function using simple Fibonacci series
        // This is slow version that does not use tail calls properly

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['def', 'fib', ['fn', ['n'], ['if', ['<', 'n', 2], 'n',
            ['+', ['fib', ['-', 'n', 1]], ['fib', ['-', 'n', 2]]]]]]);
        $evaller->eval($input, $env);

        $input = $this->buildForm(['fib', $n]);
        $result = $evaller->eval($input, $env);

        $this->assertSame($expected, $result);
    }

    // Macro tests

    public function testCreateMacro()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('macro'), new MList([]), new MList([])]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(UserFunc::class, $result);
        $this->assertTrue($result->isMacro());
    }

    public function testBasicMacro()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (def when (macro (test body) (quasiquote (if (unquote test) (unquote body) null))))
        $input = $this->buildForm(['def', 'when', ['macro', ['test', 'body'],
            ['quasiquote', ['if', ['unquote', 'test'], ['unquote', 'body'], null]]]]);

        $evaller->eval($input, $env);

        $input = $this->buildForm(['when', true, ['+', 1, 2]]);
        $result = $evaller->eval($input, $env);
        $this->assertSame(3, $result);

        $input = $this->buildForm(['when', false, ['throw', '"error"']]);
        $result = $evaller->eval($input, $env);
        $this->assertNull($result);
    }

    public function testNestedMacros()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Define a macro that expands its body twice
        // (def twice (macro (body) (quasiquote (do (unquote body) (unquote body)))))
        $input = $this->buildForm(['def', 'twice', ['macro', ['body'],
            ['quasiquote', ['do', ['unquote', 'body'], ['unquote', 'body']]]]]);
        $evaller->eval($input, $env);

        // Define a macro that expands into two nested uses of twice
        // (def four-times (macro (body) (quasiquote (twice (twice (unquote body))))))
        $input = $this->buildForm(['def', 'four-times', ['macro', ['body'],
            ['quasiquote', ['twice', ['twice', ['unquote', 'body']]]]]]);
        $evaller->eval($input, $env);

        // Set count to 0
        $evaller->eval($this->buildForm(['def', 'count', 0]), $env);

        // Increase count 4 times
        $input = $this->buildForm(['four-times', ['def', 'count', ['inc', 'count']]]);
        $result = $evaller->eval($input, $env);

        $this->assertSame(4, $result);
        $this->assertSame(4, $env->get('count'));

        // Test also macroexpand here, though we have separate tests for it
        $input = $this->buildForm(['macroexpand', ['four-times', 'body']]);
        $result = $evaller->eval($input, $env);

        // Note that macroexpand does not recursively expand the nested 'twice'
        // forms. It stops when the outer form has a non-macro head ('do').
        // The nested 'twice' forms are expanded during normal evaluation.
        $expected = $this->buildForm(['do', ['twice', 'body'], ['twice', 'body']]);
        $this->assertSameForm($expected, $result);
    }

    // Errors for fn, macro

    public function fnErrorProvider(): array
    {
        $tests = [];

        // Test both fn and macro
        foreach (['fn', 'macro'] as $type) {
            $tests[] = [
                [$type, 1],
                "$type requires exactly 2 arguments"
            ];
            $tests[] = [
                [$type, 1, 2, 3],
                "$type requires exactly 2 arguments"
            ];
            $tests[] = [
                [$type, 1, 2],
                "first argument to $type is not seq"
            ];
            $tests[] = [
                [$type, [1], 2],
                "binding key for $type is not symbol"
            ];
        }

        return $tests;
    }

    /**
     * @dataProvider fnErrorProvider
     */
    public function testFnExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: if
    // ---

    public function ifProvider(): array
    {
        return [
            [[new MList([new Symbol('<'), 1, 2]), 'yes', 'no'], 'yes'],
            [[new MList([new Symbol('>'), 1, 2]), 'yes', 'no'], 'no'],

            [[new MList([new Symbol('>'), 1, 2]), 'yes'], null],
        ];
    }

    /**
     * @dataProvider ifProvider
     */
    public function testIf(array $args, $expected)
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList(array_merge([new Symbol('if')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    // Test that the non-active branch of if is never evaluated
    public function testIfShortCircuit()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['if', true, 123, ['throw', '"error"']]);
        $this->assertSame(123, $evaller->eval($input, $env));

        $input = $this->buildForm(['if', false, ['throw', '"error"'], 456]);
        $this->assertSame(456, $evaller->eval($input, $env));
    }

    public function ifErrorProvider(): array
    {
        return [
            [
                ['if'],
                ['if', 1],
                // 2 or 3 args is ok
                ['if', 1, 2, 3, 4],
            ],
        ];
    }

    /**
     * @dataProvider ifErrorProvider
     */
    public function testIfExceptions(array $data)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('if requires 2 or 3 arguments');

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: let
    // ---

    public function testLet()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Test that later definition in let can refer to a previous one

        // (let (a (+ 1 2) b (* a 3)) (* b 4))
        $input = $this->buildForm(['let', [
            'a', ['+', 1, 2],
            'b', ['*', 'a', 3],
        ], ['*', 'b', 4]]);

        $this->assertSame(36, $evaller->eval($input, $env));
    }

    public function testLetScope()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // This test verifies that inner let shadows the value of an outer let.
        // Also check that values defined either by let or def do not exist after the block.

        // (let (x 10)
        //   (def y 15)
        //   (let (x 20)
        //     (+ x y)))
        $input = $this->buildForm(['let', ['x', 10], ['def', 'y', 15], ['let', ['x', 20], ['+', 'x', 'y']]]);
        $result = $evaller->eval($input, $env);

        $this->assertSame(35, $result);

        // Verify that environment contains neither x or y
        $this->assertFalse($env->has('x'));
        $this->assertFalse($env->has('y'));
    }

    public function testLetVectorArgs()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Test that let also accepts arguments as a Vector

        // (let [x 2] (+ x 3))
        $input = $this->buildForm(['let', $this->buildForm(['x', 2], Vector::class), ['+', 'x', 3]]);

        $result = $evaller->eval($input, $env);

        $this->assertSame(5, $result);
    }

    public function letErrorProvider(): array
    {
        return [
            [
                ['let', 1],
                'let requires at least 2 arguments'
            ],
            [
                ['let', 1, 2],
                'first argument to let is not seq'
            ],
            [
                ['let', ['a'], 1],
                'uneven number of bindings for let'
            ],
            [
                ['let', [1, 2], 1],
                'binding key for let is not symbol'
            ],
        ];
    }

    /**
     * @dataProvider letErrorProvider
     */
    public function testLetExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: load
    // ---

    public function testLoad()
    {
        // Test also special constants __FILE__ and __DIR__

        $contents = '(def adder (fn (a b) (+ a b))) (vector (let (c 3 d 4) (adder c d)) __FILE__ __DIR__)';

        $filename = tempnam('/tmp', 'madlisp-test');
        file_put_contents($filename, $contents);

        $input = new MList([new Symbol('load'), $filename]);

        list($env, $evaller) = $this->getEnvAndEvaller();

        // Set __FILE__ and __DIR__ to some made-up values
        $env->set('__FILE__', '/path/to/file');
        $env->set('__DIR__', '/path/to');

        $result = $evaller->eval($input, $env);

        // Verify that __FILE__ and __DIR__ are restored correctly
        $this->assertSame('/path/to/file', $env->get('__FILE__'));
        $this->assertSame('/path/to', $env->get('__DIR__'));

        $this->assertInstanceOf(Vector::class, $result);

        $this->assertSame([
            7,
            $filename,
            '/tmp/',
        ], $result->getData());

        // delete the temporary file
        unlink($filename);
    }

    public function testLoadDisabledInSafeMode()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol load not defined in env');

        list($env, $evaller) = $this->getEnvAndEvaller(true);

        $input = $this->buildForm(['load', '"filename"']);

        $evaller->eval($input, $env);
    }

    public function loadErrorProvider(): array
    {
        return [
            [
                ['load'],
                'load requires exactly 1 argument'
            ],
            [
                ['load', 1, 2],
                'load requires exactly 1 argument'
            ],
            [
                ['load', 1],
                'first argument to load is not string'
            ],
            [
                ['load', '"file-that-does-not-exist"'],
                'unable to read file file-that-does-not-exist'
            ],
        ];
    }

    /**
     * @dataProvider loadErrorProvider
     */
    public function testLoadExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: macroexpand
    // ---

    public function testMacroExpand()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (def def-fn (macro (name args body) (quasiquote (def (unquote name) (fn (unquote args) (unquote body))))))
        $input = $this->buildForm(['def', 'def-fn', ['macro', ['name', 'args', 'body'],
            ['quasiquote', ['def', ['unquote', 'name'], ['fn', ['unquote', 'args'], ['unquote', 'body']]]]]]);

        $evaller->eval($input, $env);

        // (macroexpand (def-fn adder (a b) (+ a b)))
        $input = $this->buildForm(['macroexpand', ['def-fn', 'adder', ['a', 'b'], ['+', 'a', 'b']]]);

        $result = $evaller->eval($input, $env);

        // (def adder (fn (a b) (+ a b)))
        $expected = $this->buildForm(['def', 'adder', ['fn', ['a', 'b'], ['+', 'a', 'b']]]);
        $this->assertSameForm($expected, $result);
    }

    public function macroExpandErrorProvider(): array
    {
        return [
            [
                ['macroexpand'],
                'macroexpand requires exactly 1 argument'
            ],
            [
                ['macroexpand', 1, 2],
                'macroexpand requires exactly 1 argument'
            ],
        ];
    }

    /**
     * @dataProvider macroExpandErrorProvider
     */
    public function testMacroExpandExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);

        $evaller->eval($input, $env);
    }

    // ---
    // Special form: meta
    // ---

    public function testMetaForEnv()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['meta', ['env'], '"name"']);
        $result = $evaller->eval($input, $env);
        $this->assertSame('root', $result);

        $input = $this->buildForm(['meta', ['env'], '"parent"']);
        $result = $evaller->eval($input, new Env('child', $env));
        $this->assertSame($env, $result);
    }

    public function testMetaForFunc()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (def adder (fn (a b) (+ a b)))
        $input = $this->buildForm(['def', 'adder', ['fn', ['a', 'b'], ['+', 'a', 'b']]]);
        $evaller->eval($input, $env);

        // Lets eval the func once to verify it works
        $input = $this->buildForm(['adder', 3, 4]);
        $result = $evaller->eval($input, $env);
        $this->assertSame(7, $result);

        // Get args
        $input = $this->buildForm(['meta', 'adder', '"args"']);
        $result = $evaller->eval($input, $env);

        $expected = $this->buildForm(['a', 'b']);
        $this->assertSameForm($expected, $result);

        // Get body
        $input = $this->buildForm(['meta', 'adder', '"body"']);
        $result = $evaller->eval($input, $env);

        $expected = $this->buildForm(['+', 'a', 'b']);
        $this->assertSameForm($expected, $result);

        // Get code
        $input = $this->buildForm(['meta', 'adder', '"code"']);
        $result = $evaller->eval($input, $env);

        $expected = $this->buildForm(['fn', ['a', 'b'], ['+', 'a', 'b']]);
        $this->assertSameForm($expected, $result);

        // Get def
        $input = $this->buildForm(['meta', 'adder', '"def"']);
        $result = $evaller->eval($input, $env);

        $expected = $this->buildForm(['defn', 'adder', ['a', 'b'], ['+', 'a', 'b']]);
        $this->assertSameForm($expected, $result);
    }

    public function metaErrorProvider(): array
    {
        return [
            [
                ['meta'],
                'meta requires exactly 2 arguments'
            ],
            [
                ['meta', 1],
                'meta requires exactly 2 arguments'
            ],
            [
                ['meta', 1, 2, 3],
                'meta requires exactly 2 arguments'
            ],
            [
                ['meta', 1, 2],
                'third argument to meta is not string'
            ],
            [
                ['meta', 1, '"unknown"'],
                'unknown entity for meta'
            ],
            // errors for env type
            [
                ['meta', ['env'], '"unknown"'],
                'unknown attribute for meta'
            ],
            // errors for user funcs
            [
                ['meta', ['fn', [], [1]], '"unknown"'],
                'unknown attribute for meta'
            ],
            [
                ['meta', ['fn', [], [1]], '"def"'],
                'no name for def in meta'
            ],
        ];
    }

    /**
     * @dataProvider metaErrorProvider
     */
    public function testMetaExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);
        $evaller->eval($input, $env);
    }

    // ---
    // Special form: or
    // ---

    public function orProvider(): array
    {
        return [
            [[], false],
            [[0, false, 2, 3], 2],
            [[0, 1], 1]
        ];
    }

    /**
     * @dataProvider orProvider
     */
    public function testOr(array $args, $expected)
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList(array_merge([new Symbol('or')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    // Test that if first arg is truthy, second arg is never evaluated
    public function testOrShortCircuit()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['or', true, ['throw', '"error"']]);

        $this->assertTrue($evaller->eval($input, $env));
    }

    // ---
    // Special forms: quote, quasiquote, quasiquote-expand
    // ---

    public function testQuote()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm(['quote', ['+', 1, 2]]);

        $result = $evaller->eval($input, $env);

        $expected = $this->buildForm(['+', 1, 2]);
        $this->assertSameForm($expected, $result);
    }

    public function testQuasiQuote()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (def a (quote (2 3)))
        $input = $this->buildForm(['def', 'a', ['quote', [2, 3]]]);
        $evaller->eval($input, $env);

        // (quasiquote (1 a 4))
        $input = $this->buildForm(['quasiquote', [1, 'a', 4]]);
        $result = $evaller->eval($input, $env);

        // (1 a 4)
        $expected = $this->buildForm([1, 'a', 4]);
        $this->assertSameForm($expected, $result);

        // (quasiquote (1 (unquote a) 4))
        $input = $this->buildForm(['quasiquote', [1, ['unquote', 'a'], 4]]);
        $result = $evaller->eval($input, $env);

        // (1 (2 3) 4)
        $expected = $this->buildForm([1, [2, 3], 4]);
        $this->assertSameForm($expected, $result);

        // (quasiquote (1 (unquote-splice a) 4))
        $input = $this->buildForm(['quasiquote', [1, ['unquote-splice', 'a'], 4]]);
        $result = $evaller->eval($input, $env);

        // (1 2 3 4)
        $expected = $this->buildForm([1, 2, 3, 4]);
        $this->assertSameForm($expected, $result);
    }

    public function testQuasiQuoteExpand()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (def a (quote (2 3)))
        $input = $this->buildForm(['def', 'a', ['quote', [2, 3]]]);
        $evaller->eval($input, $env);

        // (quasiquote-expand (1 a 4))
        $input = $this->buildForm(['quasiquote-expand', [1, 'a', 4]]);
        $result = $evaller->eval($input, $env);

        // (cons 1 (cons (quote a) (cons 4 ())))
        $expected = $this->buildForm(['cons', 1, ['cons', ['quote', 'a'], ['cons', 4, []]]]);
        $this->assertSameForm($expected, $result);

        // (quasiquote-expand (1 (unquote a) 4))
        $input = $this->buildForm(['quasiquote-expand', [1, ['unquote', 'a'], 4]]);
        $result = $evaller->eval($input, $env);

        // (cons 1 (cons a (cons 4 ())))
        $expected = $this->buildForm(['cons', 1, ['cons', 'a', ['cons', 4, []]]]);
        $this->assertSameForm($expected, $result);

        // (quasiquote-expand (1 (unquote-splice a) 4))
        $input = $this->buildForm(['quasiquote-expand', [1, ['unquote-splice', 'a'], 4]]);
        $result = $evaller->eval($input, $env);

        // (cons 1 (concat a (cons 4 ())))
        $expected = $this->buildForm(['cons', 1, ['concat', 'a', ['cons', 4, []]]]);
        $this->assertSameForm($expected, $result);
    }

    public function quoteErrorProvider(): array
    {
        $tests = [];

        // Test both fn and macro
        foreach (['quote', 'quasiquote', 'quasiquote-expand'] as $type) {
            $tests[] = [
                [$type],
                "$type requires exactly 1 argument"
            ];
            $tests[] = [
                [$type, 1, 2],
                "$type requires exactly 1 argument"
            ];
        }

        $tests[] = [
            ['quasiquote', ['unquote']],
            'unquote requires exactly 1 argument'
        ];
        $tests[] = [
            ['quasiquote', ['unquote', 1, 2]],
            'unquote requires exactly 1 argument'
        ];

        $tests[] = [
            ['quasiquote', [['unquote-splice']]],
            'unquote-splice requires exactly 1 argument'
        ];
        $tests[] = [
            ['quasiquote', [['unquote-splice', 1, 2]]],
            'unquote-splice requires exactly 1 argument'
        ];

        return $tests;
    }

    /**
     * @dataProvider quoteErrorProvider
     */
    public function testQuoteExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);
        $evaller->eval($input, $env);
    }

    // ---
    // Special form: try
    // ---

    public function testTryUserException()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (try (throw "error-message") (catch ex (split "-" ex)))
        $input = $this->buildForm(['try', ['throw', '"error-message"'], ['catch', 'ex', ['split', '"-"', 'ex']]]);

        $result = $evaller->eval($input, $env);

        $expected = $this->buildForm(['"error"', '"message"'], Vector::class);
        $this->assertSameForm($expected, $result);
    }

    public function testTryPhpException()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (try (/ 1 0) (catch ex (get ex "type")))
        $input = $this->buildForm(['try', ['/', 1, 0], ['catch', 'ex', ['get', 'ex', '"type"']]]);

        $result = $evaller->eval($input, $env);

        $this->assertSame('DivisionByZeroError', $result);
    }

    public function testTrySuccess()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Test that successful try returns its normal result

        // (try (+ 4 5) (catch ex (throw "error")))
        $input = $this->buildForm(['try', ['+', 4, 5], ['catch', 'ex', ['throw', '"error"']]]);

        $result = $evaller->eval($input, $env);
        $this->assertSame(9, $result);
    }

    public function tryErrorProvider(): array
    {
        return [
            [
                ['try'],
                'try requires exactly 2 arguments'
            ],
            [
                ['try', 1],
                'try requires exactly 2 arguments'
            ],
            [
                ['try', 1, 2, 3],
                'try requires exactly 2 arguments'
            ],
            [
                ['try', 1, 2],
                'second argument to try is not seq'
            ],
            // invalid forms for catch
            // less than 3 elements
            [
                ['try', 1, [1, 2]],
                'invalid form for catch'
            ],
            // more than 3 elements
            [
                ['try', 1, [1, 2, 3, 4]],
                'invalid form for catch'
            ],
            // first or second arg in catch not a symbol
            [
                ['try', 1, ['a', 0, 1]],
                'invalid form for catch'
            ],
            [
                ['try', 1, [0, 'a', 1]],
                'invalid form for catch'
            ],
            // first arg of catch has wrong name
            [
                ['try', 1, ['cat', 'a', 1]],
                'invalid form for catch'
            ],
        ];
    }

    /**
     * @dataProvider tryErrorProvider
     */
    public function testTryExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);
        $evaller->eval($input, $env);
    }

    // ---
    // Special form: undef
    // ---

    public function testUndef()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $env->set('aa', 12);

        $this->assertTrue($env->has('aa'));

        $input = $this->buildForm(['undef', 'aa']);

        $evaller->eval($input, $env);

        $this->assertFalse($env->has('aa'));
    }

    public function undefErrorProvider(): array
    {
        return [
            [
                ['undef'],
                'undef requires exactly 1 argument'
            ],
            [
                ['undef', 1, 2],
                'undef requires exactly 1 argument'
            ],
            [
                ['undef', 1],
                'first argument to undef is not symbol'
            ],
        ];
    }

    /**
     * @dataProvider undefErrorProvider
     */
    public function testUndefExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);
        $evaller->eval($input, $env);
    }

    // ---
    // Special form: while
    // ---

    public function testWhile()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (let (i 5 x 2) (while (> i 0) (def i (dec i)) (def x (* x 2))))
        $input = $this->buildForm([
            'let', ['i', 5, 'x', 2],
            ['while', ['>', 'i', 0],
                ['def', 'i', ['dec', 'i']],
                ['def', 'x', ['*', 'x', 2]]]
        ]);

        $result = $evaller->eval($input, $env);

        // 2 * 2 = 4
        // 4 * 2 = 8
        // 8 * 2 = 16
        // 16 * 2 = 32
        // 32 * 2 = 64

        $this->assertSame(64, $result);
    }

    public function testWhileWithFalse()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // Test while with initial false value, make sure
        // that the body is not executed and result is null.

        $input = $this->buildForm(['while', false, ['throw', '"error"']]);
        $result = $evaller->eval($input, $env);

        $this->assertNull($result);
    }

    public function whileErrorProvider(): array
    {
        return [
            [
                ['while'],
                'while requires at least 2 arguments'
            ],
            [
                ['while', 1],
                'while requires at least 2 arguments'
            ],
        ];
    }

    /**
     * @dataProvider whileErrorProvider
     */
    public function testWhileExceptions(array $data, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = $this->buildForm($data);
        $evaller->eval($input, $env);
    }

    // -----------------
    // Private functions
    // -----------------

    private function getEnvAndEvaller(bool $safemode = false): array
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $compiler = new IrCompiler();
        $printer = new Printer();

        $evaller = new Evaller(
            $tokenizer,
            $reader,
            $printer,
            $safemode
        );

        $env = new Env('root');

        $env->set('__FILE__', null);
        $env->set('__DIR__', null);

        // Define some functions for testing
        $lib = new Core(
            $tokenizer,
            $reader,
            $compiler,
            $printer,
            $evaller,
            $safemode
        );
        $lib->register($env);
        $lib = new Collections();
        $lib->register($env);
        $lib = new Compare();
        $lib->register($env);
        $lib = new Math();
        $lib->register($env);
        $lib = new Strings();
        $lib->register($env);
        $lib = new Types();
        $lib->register($env);

        return [$env, $evaller];
    }

    // Helper for turning an array into MadLisp datatype
    private function buildForm($shape, $arrayType = MList::class)
    {
        if (is_array($shape)) {
            return new $arrayType(array_map([$this, 'buildForm'], $shape));
        } elseif (is_string($shape)) {
            // literal strings start with "
            if (substr($shape, 0, 1) === '"') {
                return substr($shape, 1, -1);
            }

            // otherwise it is a symbol
            return new Symbol($shape);
        }

        // Anything other than array or string is passed through as-is
        return $shape;
    }

    // Helper for turning MadLisp datatype into array for comparison
    private function normalizeForm($form)
    {
        if ($form instanceof Symbol) {
            return 'Symbol:' . $form->getName();
        } elseif ($form instanceof Collection) {
            return array_map([$this, 'normalizeForm'], $form->getData());
        }

        return $form;
    }

    private function typeToString($value)
    {
        if (is_object($value)) {
            return get_class($value);
        }

        return get_type($value);
    }

    // Assert same type and also same contents when type is a collection
    private function assertSameForm($expected, $result)
    {
        $this->assertSame($this->typeToString($expected), $this->typeToString($result));
        $this->assertSame($this->normalizeForm($expected), $this->normalizeForm($result));
    }
}
