<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Lisp;
use MadLisp\LispFactory;

class LispFactoryTest extends TestCase
{
    public function testMake()
    {
        $factory = new LispFactory();

        $lisp = $factory->make();

        $this->assertInstanceOf(Lisp::class, $lisp);
    }
}
