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
use MadLisp\Vector;
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

    private function getEnv(): Env
    {
        $env = new Env('env');

        // Define some math functions for testing
        $lib = new Math();
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
