<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Compiler;
use MadLisp\Env;
use MadLisp\MList;
use MadLisp\OpCode;
use MadLisp\Symbol;

class CompilerTest extends TestCase
{
    public function testCompilesLiteral(): void
    {
        $compiler = new Compiler();
        $env = new Env('root');

        $program = $compiler->compile(42, $env);

        $this->assertNotNull($program);
        $this->assertSame([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([42], $program->getConstants());
        $this->assertSame(0, $program->getLocalCount());
    }

    public function testCompilesGlobalSymbol(): void
    {
        $compiler = new Compiler();
        $env = new Env('root');

        $program = $compiler->compile(new Symbol('value'), $env);

        $this->assertNotNull($program);
        $this->assertSame([
            OpCode::LOAD_GLOBAL, 0,
            OpCode::RETURN,
        ], $program->getCode());
        $this->assertSame(['value'], $program->getConstants());
    }

    public function testCompilesIf(): void
    {
        $compiler = new Compiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('if'),
            true,
            1,
            2,
        ]);

        $program = $compiler->compile($ast, $env);

        $this->assertNotNull($program);
        $this->assertSame([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::JUMP_IF_FALSE, 8,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::JUMP, 10,
            OpCode::LOAD_CONSTANT, 2,
            OpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([true, 1, 2], $program->getConstants());
    }

    public function testCompilesIfWithoutElse(): void
    {
        $compiler = new Compiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('if'),
            false,
            1,
        ]);

        $program = $compiler->compile($ast, $env);

        $this->assertNotNull($program);
        $this->assertSame([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::JUMP_IF_FALSE, 8,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::JUMP, 10,
            OpCode::LOAD_CONSTANT, 2,
            OpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([false, 1, null], $program->getConstants());
    }

    public function testCompilesNestedIf(): void
    {
        $compiler = new Compiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('if'),
            true,
            new MList([
                new Symbol('if'),
                false,
                1,
                2,
            ]),
            3,
        ]);

        $program = $compiler->compile($ast, $env);

        $this->assertNotNull($program);
        $this->assertSame([
            OpCode::LOAD_CONSTANT, 0,
            OpCode::JUMP_IF_FALSE, 16,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::JUMP_IF_FALSE, 12,
            OpCode::LOAD_CONSTANT, 2,
            OpCode::JUMP, 14,
            OpCode::LOAD_CONSTANT, 3,
            OpCode::JUMP, 18,
            OpCode::LOAD_CONSTANT, 4,
            OpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([true, false, 1, 2, 3], $program->getConstants());
    }

    public function testCompilesCall(): void
    {
        $compiler = new Compiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('+'),
            1,
            2,
        ]);

        $program = $compiler->compile($ast, $env);

        $this->assertNotNull($program);
        $this->assertSame([
            OpCode::LOAD_GLOBAL, 0,
            OpCode::LOAD_CONSTANT, 1,
            OpCode::LOAD_CONSTANT, 2,
            OpCode::CALL, 2,
            OpCode::RETURN,
        ], $program->getCode());
        $this->assertSame(['+', 1, 2], $program->getConstants());
    }

    public function testUnsupportedSpecialFormReturnsNull(): void
    {
        $compiler = new Compiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('do'),
            1,
            2,
        ]);

        $this->assertNull($compiler->compile($ast, $env));
    }
}
