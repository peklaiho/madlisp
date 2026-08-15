<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\Vector;
use MadLisp\Lib\Math;

class MathTest extends TestCase
{
    public function testPI(): void
    {
        $this->assertSame(\M_PI, $this->getEnv()->get('PI'));
    }

    public function testAdd(): void
    {
        $this->assertSame(6, $this->getEnv()->get('+')->call([1, 2, 3]));
    }

    public function testSubtract(): void
    {
        $this->assertSame(5, $this->getEnv()->get('-')->call([10, 3, 2]));
    }

    public function testMultiply(): void
    {
        $this->assertSame(24, $this->getEnv()->get('*')->call([2, 3, 4]));
    }

    public function testDivide(): void
    {
        $this->assertSame(2.5, $this->getEnv()->get('/')->call([10, 2, 2]));
    }

    public function testIntegerDivide(): void
    {
        $this->assertSame(2, $this->getEnv()->get('//')->call([10, 2, 2]));
    }

    public function testModulo(): void
    {
        $this->assertSame(1, $this->getEnv()->get('%')->call([10, 3, 2]));
    }

    public function testInc(): void
    {
        $this->assertSame(4, $this->getEnv()->get('inc')->call([3]));
    }

    public function testDec(): void
    {
        $this->assertSame(2, $this->getEnv()->get('dec')->call([3]));
    }

    public function testSin(): void
    {
        $this->assertEqualsWithDelta(1, $this->getEnv()->get('sin')->call([\M_PI / 2]), 0.000001);
    }

    public function testCos(): void
    {
        $this->assertEqualsWithDelta(1, $this->getEnv()->get('cos')->call([0]), 0.000001);
    }

    public function testTan(): void
    {
        $this->assertEqualsWithDelta(1, $this->getEnv()->get('tan')->call([\M_PI / 4]), 0.000001);
    }

    public function testAbs(): void
    {
        $this->assertSame(5, $this->getEnv()->get('abs')->call([-5]));
    }

    public function testFloor(): void
    {
        $this->assertSame(2, $this->getEnv()->get('floor')->call([2.9]));
    }

    public function testCeil(): void
    {
        $this->assertSame(3, $this->getEnv()->get('ceil')->call([2.1]));
    }

    public function testMax(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $this->assertSame(5, $this->getEnv()->get('max')->call([new $type([2, 5, 1])]));
        }
    }

    public function testMaxException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('single argument to max is not sequence');

        $this->getEnv()->get('max')->call([1]);
    }

    public function testMin(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $this->assertSame(1, $this->getEnv()->get('min')->call([new $type([2, 5, 1])]));
        }
    }

    public function testMinException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('single argument to min is not sequence');

        $this->getEnv()->get('min')->call([1]);
    }

    public function testPow(): void
    {
        $this->assertSame(8, $this->getEnv()->get('pow')->call([2, 3]));
    }

    public function testSqrt(): void
    {
        $this->assertSame(3.0, $this->getEnv()->get('sqrt')->call([9]));
    }

    public function testRand(): void
    {
        $result = $this->getEnv()->get('rand')->call([1, 10]);

        $this->assertGreaterThanOrEqual(1, $result);
        $this->assertLessThanOrEqual(10, $result);
    }

    public function testRandf(): void
    {
        $result = $this->getEnv()->get('randf')->call([]);

        $this->assertGreaterThanOrEqual(0, $result);
        $this->assertLessThan(1, $result);
    }

    public function testRandSeed(): void
    {
        $function = $this->getEnv()->get('rand-seed');

        $this->assertSame(42, $function->call([42]));
    }

    public function testRandBytes(): void
    {
        $result = $this->getEnv()->get('rand-bytes')->call([8]);

        $this->assertSame(8, strlen($result));
    }

    private function getEnv(): Env
    {
        $env = new Env('test');
        (new Math())->register($env);

        return $env;
    }
}
