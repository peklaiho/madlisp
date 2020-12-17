<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\MList;
use MadLisp\UserFunc;

class FuncTest extends TestCase
{
    public function testFunc()
    {
        $closure = fn ($a, $b) => $a + $b;
        $ast = new MList();
        $env = new Env("env");
        $bindings = new MList();

        $fn = new UserFunc($closure, $ast, $env, $bindings, false);

        $this->assertSame($closure, $fn->getClosure());

        // test docstrings
        $this->assertNull($fn->getDoc());
        $fn->setDoc('docstring');
        $this->assertSame('docstring', $fn->getDoc());

        // test isMacro
        $this->assertFalse($fn->isMacro());
        $fn = new UserFunc($closure, $ast, $env, $bindings, true);
        $this->assertTrue($fn->isMacro());
    }
}
