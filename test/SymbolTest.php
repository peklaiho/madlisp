<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Symbol;

class SymbolTest extends TestCase
{
    public function testSymbol()
    {
        $symbol = new Symbol('abc');
        $this->assertSame('abc', $symbol->getName());
    }
}
