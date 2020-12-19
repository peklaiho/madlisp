<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

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
use MadLisp\Lib\Compare;
use MadLisp\Lib\Math;

class EvallerTest extends TestCase
{
    public function testEvalAtom()
    {
        // Test values that are not evaluated (they are returned unchanged)

        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $this->assertSame(true, $evaller->eval(true, $env));
        $this->assertSame(false, $evaller->eval(false, $env));
        $this->assertSame(null, $evaller->eval(null, $env));
        $this->assertSame(123, $evaller->eval(123, $env));
        $this->assertSame(4.56, $evaller->eval(4.56, $env));
        $this->assertSame("abc", $evaller->eval("abc", $env));

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

        $env = $this->getEnv();
        $env->set('abc', 123);
        $env->set('efg', new MList([1, 2, 3]));

        $evaller = $this->getEvaller();

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

        $env = $this->getEnv();
        $evaller = $this->getEvaller();
        $evaller->eval(new Symbol('abc'), $env);
    }

    public function testEvalEmptyList()
    {
        // Empty list is not changed

        $env = $this->getEnv();
        $evaller = $this->getEvaller();
        $result = $evaller->eval(new MList(), $env);

        $this->assertInstanceOf(MList::class, $result);
        $this->assertCount(0, $result->getData());
    }

    public function testEvalVector()
    {
        // Evaluating a vector returns new vector where each element is evaluated

        $env = $this->getEnv();
        $evaller = $this->getEvaller();

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

    public function testEvalHash()
    {
        // Evaluating a hash-map returns a new hash-map where the values are evaluated

        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new Hash([
            "aa" => new MList([new Symbol('+'), 1, 2]),
            "bb" => new Hash([
                "cc" => new MList([new Symbol('+'), 3, 4])
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

    public function testEvalList()
    {
        // Test simple list evaluation

        $env = $this->getEnv();
        $evaller = $this->getEvaller();

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

        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList([1, 2, 3]);

        $evaller->eval($input, $env);
    }

    public function testDebug()
    {
        $evaller = $this->getEvaller();

        $this->assertFalse($evaller->getDebug());
        $evaller->setDebug(true);
        $this->assertTrue($evaller->getDebug());
    }

    // -------------------
    // Tests special forms
    // -------------------

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
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList(array_merge([new Symbol('and')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function caseProvider(): array
    {
        return [
            [
                [
                    new MList([new Symbol('+'), 1, 2]),
                    new MList([2, "two"]),
                    new MList([3, "three"]),
                    new MList([4, "four"])
                ],
                "three"
            ],
            [
                [
                    new MList([new Symbol('+'), 2, 3]),
                    new MList([2, "two"]),
                    new MList([3, "three"]),
                    new MList([4, "four"]),
                    new MList([new Symbol('else'), 'other'])
                ],
                "other"
            ]
        ];
    }

    /**
     * @dataProvider caseProvider
     */
    public function testCase(array $args, $expected)
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList(array_merge([new Symbol('case')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function condProvider(): array
    {
        return [
            [
                [
                    new MList([new MList([new Symbol('='), new Symbol('n'), 2]), "two"]),
                    new MList([new MList([new Symbol('='), new Symbol('n'), 4]), "four"]),
                    new MList([new MList([new Symbol('='), new Symbol('n'), 6]), "six"]),
                ],
                "four"
            ],
            [
                [
                    new MList([new MList([new Symbol('='), new Symbol('n'), 1]), "one"]),
                    new MList([new MList([new Symbol('='), new Symbol('n'), 3]), "three"]),
                    new MList([new MList([new Symbol('='), new Symbol('n'), 5]), "five"]),
                    new MList([new Symbol('else'), 'other'])
                ],
                "other"
            ]
        ];
    }

    /**
     * @dataProvider condProvider
     */
    public function testCond(array $args, $expected)
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $env->set('n', 4);

        $input = new MList(array_merge([new Symbol('cond')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function testDef()
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList([new Symbol('def'), new Symbol('abc'), 123]);

        $evaller->eval($input, $env);

        $this->assertSame($env->get('abc'), 123);
    }

    public function testEnv()
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList([new Symbol('env')]);

        $this->assertSame($env, $evaller->eval($input, $env));
    }

    public function testEval()
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList([new Symbol('eval'), new MList([new Symbol('quote'), new MList([new Symbol('+'), 1, 2])])]);

        $this->assertSame(3, $evaller->eval($input, $env));
    }

    public function testFn()
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList([new Symbol('fn'), new MList([]), new MList([])]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(UserFunc::class, $result);
    }

    public function ifProvider(): array
    {
        return [
            [[new MList([new Symbol('<'), 1, 2]), "yes", "no"], "yes"],
            [[new MList([new Symbol('>'), 1, 2]), "yes", "no"], "no"],

            [[new MList([new Symbol('>'), 1, 2]), "yes"], null],
        ];
    }

    /**
     * @dataProvider ifProvider
     */
    public function testIf(array $args, $expected)
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList(array_merge([new Symbol('if')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function testLet()
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList([new Symbol('let'), new MList([
                new Symbol('a'), new MList([new Symbol('+'), 1, 2]),
                new Symbol('b'), new MList([new Symbol('*'), new Symbol('a'), 3])
            ]),
            new MList([new Symbol('*'), new Symbol('b'), 4])
        ]);

        $this->assertSame(36, $evaller->eval($input, $env));
    }

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
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList(array_merge([new Symbol('or')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

    public function testQuote()
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $input = new MList([new Symbol('quote'), new MList([new Symbol('+'), 1, 2])]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(MList::class, $result);
        $data = $result->getData();
        $this->assertCount(3, $data);
        $this->assertInstanceOf(Symbol::class, $data[0]);
        $this->assertSame('+', $data[0]->getName());
        $this->assertSame(1, $data[1]);
        $this->assertSame(2, $data[2]);
    }

    public function testUndef()
    {
        $env = $this->getEnv();
        $evaller = $this->getEvaller();

        $env->set('aa', 12);

        $this->assertTrue($env->has('aa'));

        $input = new MList([new Symbol('undef'), new Symbol('aa')]);

        $evaller->eval($input, $env);

        $this->assertFalse($env->has('aa'));
    }

    // -----------------
    // End special forms
    // -----------------

    private function getEnv(): Env
    {
        $env = new Env('env');

        // Define some functions for testing
        $lib = new Math();
        $lib->register($env);
        $lib = new Compare();
        $lib->register($env);

        return $env;
    }

    private function getEvaller(): Evaller
    {
        return new Evaller(
            new Tokenizer(),
            new Reader(),
            new Printer(),
            false
        );
    }
}
