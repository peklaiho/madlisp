<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\MList;
use MadLisp\PhpCompiledProgram;
use MadLisp\PhpCompiler;
use MadLisp\Symbol;

class PhpCompilerTest extends TestCase
{
    public function testCompilesAndExecutesArithmetic(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('+'),
            2,
            new MList([new Symbol('*'), 3, 4]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertSame(14, $program->execute($env));
    }

    public function testCompilesAndExecutesLetWithVariables(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([
                new Symbol('x'), 10,
                new Symbol('y'), new MList([new Symbol('+'), new Symbol('x'), 2]),
            ]),
            new MList([new Symbol('*'), new Symbol('y'), 3]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertSame(36, $program->execute($env));
    }

    public function testCompilesAndExecutesLetWithShadowing(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('x'), 10]),
            new MList([
                new Symbol('let'),
                new MList([new Symbol('x'), 20]),
                new Symbol('x'),
            ]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertSame(20, $program->execute($env));
    }

    public function testCompilesAndExecutesDoWithMultipleExpressions(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('do'),
            1,
            2,
            new MList([new Symbol('+'), 3, 4]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertSame(7, $program->execute($env));
    }

    public function testCompilesAndExecutesEmptyDoAsNull(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');

        $program = $compiler->compile(new MList([new Symbol('do')]));

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertNull($program->execute($env));
    }
}
