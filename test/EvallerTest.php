<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Collection;
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
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList(array_merge([new Symbol('and')], $args));

        $this->assertSame($expected, $evaller->eval($input, $env));
    }

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

    public function testDef()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('def'), new Symbol('abc'), 123]);

        $evaller->eval($input, $env);

        $this->assertSame($env->get('abc'), 123);
    }

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

    public function testEnv()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('env')]);

        $this->assertSame($env, $evaller->eval($input, $env));
    }

    public function testEval()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        // (eval (quote (+ 1 2)))
        $input = $this->buildForm(['eval', ['quote', ['+', 1, 2]]]);

        $this->assertSame(3, $evaller->eval($input, $env));
    }

    public function testFn()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('fn'), new MList([]), new MList([])]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(UserFunc::class, $result);
        $this->assertFalse($result->isMacro());
    }

    public function testMacro()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $input = new MList([new Symbol('macro'), new MList([]), new MList([])]);

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(UserFunc::class, $result);
        $this->assertTrue($result->isMacro());
    }

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

    public function testLoad()
    {
        // Test also special constants __FILE__ and __DIR__

        $contents = '(def adder (fn (a b) (+ a b))) (vector (let (c 3 d 4) (adder c d)) __FILE__ __DIR__)';

        $filename = tempnam('/tmp', 'madlisp-test');
        file_put_contents($filename, $contents);

        $input = new MList([new Symbol('load'), $filename]);

        list($env, $evaller) = $this->getEnvAndEvaller();

        $result = $evaller->eval($input, $env);

        $this->assertInstanceOf(Vector::class, $result);

        $this->assertSame([
            7,
            $filename,
            '/tmp/',
        ], $result->getData());
    }

    public function testLoadDisabledInSafeMode()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol load not defined in env');

        list($env, $evaller) = $this->getEnvAndEvaller(true);

        $input = $this->buildForm(['load', '"filename"']);

        $evaller->eval($input, $env);
    }

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

    public function testUndef()
    {
        list($env, $evaller) = $this->getEnvAndEvaller();

        $env->set('aa', 12);

        $this->assertTrue($env->has('aa'));

        $input = $this->buildForm(['undef', 'aa']);

        $evaller->eval($input, $env);

        $this->assertFalse($env->has('aa'));
    }

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

    // -----------------
    // End special forms
    // -----------------

    private function getEnvAndEvaller(bool $safemode = false): array
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
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
