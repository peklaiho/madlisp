<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\MadLispException;
use MadLisp\MList;

class MListTest extends TestCase
{
    public function testGet()
    {
        $list = new MList([1, 2, 3]);
        $this->assertSame(2, $list->get(1));
    }

    public function testNotFound()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('list does not contain index 3');

        $list = new MList([1, 2, 3]);
        $list->get(3);
    }
}
