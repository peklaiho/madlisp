<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\Symbol;
use MadLisp\Util;
use MadLisp\Vector;

class UtilTest extends TestCase
{
    public function testBindArguments()
    {
        $env = new Env('env');

        Util::bindArguments($env, [
            new Symbol('a'),
            new Symbol('b')
        ], [
            1,
            2
        ]);

        $this->assertSame(['a' => 1, 'b' => 2], $env->getData());
    }

    public function testBindArgumentsVariable()
    {
        $env = new Env('env');

        Util::bindArguments($env, [
            new Symbol('a'),
            new Symbol('b'),
            new Symbol('&'),
            new Symbol('c')
        ], [
            1,
            2,
            3,
            4
        ]);

        $data = $env->getData();
        $this->assertCount(3, $data);
        $this->assertSame(1, $data['a']);
        $this->assertSame(2, $data['b']);
        $vec = $data['c'];
        $this->assertInstanceOf(Vector::class, $vec);
        $this->assertSame([3, 4], $vec->getData());
    }

    public function testBindArgumentsVariableInvalid()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('no binding after &');

        $env = new Env('env');

        Util::bindArguments($env, [
            new Symbol('a'),
            new Symbol('b'),
            new Symbol('&')
        ], [
            1,
            2,
            3,
            4
        ]);
    }

    public function testMakeHash()
    {
        $hash = Util::makeHash(['a', 1, 'b', 2]);

        $this->assertInstanceOf(Hash::class, $hash);
        $this->assertSame(['a' => 1, 'b' => 2], $hash->getData());
    }

    public function testMakeHashUnevenArgs()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('uneven number of arguments for hash');

        Util::makeHash(['a', 1, 'b', 2, 'c']);
    }

    public function testMakeHashInvalidKey()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('invalid key for hash (not string)');

        Util::makeHash([1, 2]);
    }
}
