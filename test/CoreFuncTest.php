<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CoreFunc;
use MadLisp\MadLispException;

class CoreFuncTest extends TestCase
{
    public function invalidArgsProvider(): array
    {
        return [
            [[1], 2, 2, "name requires exactly 2 arguments"],
            [[1, 2, 3], 2, 2, "name requires exactly 2 arguments"],

            [[1], 2, 3, "name requires at least 2 arguments"],
            [[1, 2, 3], 1, 2, "name requires at most 2 arguments"],
        ];
    }

    /**
     * @dataProvider invalidArgsProvider
     */
    public function testInvalidArgs(array $args, int $min, int $max, string $message)
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage($message);

        $closure = fn () => 1;
        $fn = new CoreFunc("name", "doc", $min, $max, $closure);
        $fn->call($args);
    }

    public function testCall()
    {
        $closure = fn ($a, $b) => $a + $b;
        $fn = new CoreFunc("name", "doc", 2, 2, $closure);

        $result = $fn->call([2, 3]);

        $this->assertSame(5, $result);
    }
}
