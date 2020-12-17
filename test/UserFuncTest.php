<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\MList;
use MadLisp\Symbol;
use MadLisp\UserFunc;

class UserFuncTest extends TestCase
{
    public function testUserFunc()
    {
        $closure = fn ($a, $b) => $a + $b;
        $ast = new MList();
        $env = new Env("env");
        $bindings = new MList([new Symbol('a'), new Symbol('b')]);

        $fn = new UserFunc($closure, $ast, $env, $bindings, false);

        $this->assertSame($ast, $fn->getAst());
        $this->assertSame($bindings, $fn->getBindings());

        $newEnv = $fn->getEnv([1, 2]);
        $this->assertInstanceOf(Env::class, $newEnv);
        $this->assertSame(['a' => 1, 'b' => 2], $newEnv->getData());

        $result = $fn->call([1, 2]);
        $this->assertSame(3, $result);
    }
}
