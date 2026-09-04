<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\MList;
use MadLisp\Symbol;
use MadLisp\Vector;
use MadLisp\Lib\Compare;

class CompareTest extends TestCase
{
    public static function equalProvider(): array
    {
        return [
            [1, 1, true],
            [1, 2, false],

            // Type conversion
            [1, true, true],
            [null, false, true],
            [0, false, true],

            // String and number
            [1, '1', true],
            ['0', 0, true],

            // Strings
            ['hello', 'hello', true],
            ['hello', 'goodbye', false],

            // Lists
            [new MList([1, 2]), new Vector([1, 2]), true],
            [new MList([1, 2]), new Vector([1, 3]), false],

            // Other
            [new Symbol('abc'), new Symbol('abc'), true],
            [new Symbol('abc'), new Symbol('abd'), false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('equalProvider')]
    public function testEqual($a, $b, bool $expected): void
    {
        // test = and != together because they are opposite

        $this->assertSame($expected, $this->getEnv()->get('=')->call([$a, $b]));
        $this->assertSame(!$expected, $this->getEnv()->get('!=')->call([$a, $b]));
    }

    public static function strictEqualProvider(): array
    {
        return [
            [1, 1, true],
            [1, 2, false],

            // Type conversion
            [1, true, false],
            [null, false, false],
            [0, false, false],

            // String and number
            [1, '1', false],
            ['0', 0, false],

            // Strings
            ['hello', 'hello', true],
            ['hello', 'goodbye', false],

            // Lists
            [new MList([1, 2]), new Vector([1, 2]), true],
            [new MList([1, 2]), new Vector([1, 3]), false],

            // Other
            [new Symbol('abc'), new Symbol('abc'), true],
            [new Symbol('abc'), new Symbol('abd'), false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('strictEqualProvider')]
    public function testStrictEqual($a, $b, bool $expected): void
    {
        // test == and !== together because they are opposite

        $this->assertSame($expected, $this->getEnv()->get('==')->call([$a, $b]));
        $this->assertSame(!$expected, $this->getEnv()->get('!==')->call([$a, $b]));
    }

    public static function lessMoreProvider(): array
    {
        return [
            //         <      <=     >      =>
            [1, 2,     true,  true,  false, false],
            [2, 1,     false, false, true,  true],
            [2, 2,     false, true,  false, true],
            [-1, 0,    true,  true,  false, false],
            [-1, -1,   false, true,  false, true],
            ['a', 'b', true,  true,  false, false],
            ['b', 'a', false, false, true,  true],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('lessMoreProvider')]
    public function testLessMore($a, $b, bool $e1, bool $e2, bool $e3, bool $e4): void
    {
        $this->assertSame($e1, $this->getEnv()->get('<')->call([$a, $b]));
        $this->assertSame($e2, $this->getEnv()->get('<=')->call([$a, $b]));
        $this->assertSame($e3, $this->getEnv()->get('>')->call([$a, $b]));
        $this->assertSame($e4, $this->getEnv()->get('>=')->call([$a, $b]));
    }

    private function getEnv(): Env
    {
        $env = new Env('test');
        (new Compare())->register($env);

        return $env;
    }
}
