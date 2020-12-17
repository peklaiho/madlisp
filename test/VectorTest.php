<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\MadLispException;
use MadLisp\Vector;

class VectorTest extends TestCase
{
    public function testGet()
    {
        $list = new Vector([1, 2, 3]);
        $this->assertSame(2, $list->get(1));
    }

    public function testNotFound()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('vector does not contain index 3');

        $list = new Vector([1, 2, 3]);
        $list->get(3);
    }
}
