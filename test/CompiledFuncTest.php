<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CompiledFunc;
use MadLisp\CompiledProgram;
use MadLisp\Env;
use MadLisp\MadLispException;

class CompiledFuncTest extends TestCase
{
    public function testExposesCompiledFunctionState(): void
    {
        $program = new CompiledProgram([], [], 0);
        $env = new Env('root');
        $captures = ['value'];
        $function = new CompiledFunc($program, $env, 2, $captures);

        $this->assertSame($program, $function->getProgram());
        $this->assertSame($env, $function->getEnv());
        $this->assertSame(2, $function->getArity());
        $this->assertSame($captures, $function->getCaptures());
    }

    public function testCannotBeCalledDirectly(): void
    {
        $function = new CompiledFunc(new CompiledProgram([], [], 0), new Env('root'), 0);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('compiled function must be invoked by executor');

        $function->call([]);
    }
}
