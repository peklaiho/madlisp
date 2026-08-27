<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\MadLispException;
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

    public function testLetScopeDoesNotLeakIntoLaterDoExpressions(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('value', 99);
        $ast = new MList([
            new Symbol('do'),
            new MList([
                new Symbol('let'),
                new MList([new Symbol('value'), 20]),
                new Symbol('value'),
            ]),
            new Symbol('value'),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(99, $program->execute($env));
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

    public function testCompilesFunctionWithParameters(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('fn'),
            new MList([new Symbol('x')]),
            new MList([new Symbol('+'), new Symbol('x'), 1]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertInstanceOf(\Closure::class, $function);
        $this->assertSame(42, $function(41));
    }

    public function testFunctionParametersShadowEnvironmentBindings(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('x', 100);
        $ast = new MList([
            new Symbol('fn'),
            new MList([new Symbol('x')]),
            new Symbol('x'),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(7, $function(7));
    }

    public function testFunctionCapturesOuterLetBinding(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('offset'), 5]),
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('x')]),
                new MList([new Symbol('+'), new Symbol('x'), new Symbol('offset')]),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(12, $function(7));
    }

    public function testFunctionSupportsNestedLetIfAndDo(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('x')]),
                new MList([
                    new Symbol('let'),
                    new MList([
                        new Symbol('doubled'),
                        new MList([new Symbol('*'), new Symbol('x'), 2]),
                    ]),
                    new MList([
                        new Symbol('if'),
                        new MList([new Symbol('>'), new Symbol('doubled'), 10]),
                        new MList([new Symbol('do'), new Symbol('doubled'), 1]),
                        new Symbol('doubled'),
                    ]),
                ]),
            ]),
            6,
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(1, $program->execute($env));
    }

    public function testCallsFunctionLiteralFromLispCode(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('x')]),
                new MList([new Symbol('+'), new Symbol('x'), 1]),
            ]),
            41,
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
    }

    public function testCallsFunctionStoredInLocalBinding(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([
                new Symbol('double'),
                new MList([
                    new Symbol('fn'),
                    new MList([new Symbol('x')]),
                    new MList([new Symbol('*'), new Symbol('x'), 2]),
                ]),
            ]),
            new MList([new Symbol('double'), 21]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
    }

    public function testFunctionCapturesMultipleOuterBindings(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([
                new Symbol('a'), 10,
                new Symbol('b'), 20,
            ]),
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('x')]),
                new MList([
                    new Symbol('+'),
                    new MList([new Symbol('+'), new Symbol('x'), new Symbol('a')]),
                    new Symbol('b'),
                ]),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(37, $function(7));
        $this->assertStringContainsString('use ($env, $v0, $v1)', $program->getSource());
    }

    public function testInnerLetShadowsCapturedOuterBinding(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('value'), 5]),
            new MList([
                new Symbol('fn'),
                new MList([]),
                new MList([
                    new Symbol('let'),
                    new MList([new Symbol('value'), 12]),
                    new Symbol('value'),
                ]),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(12, $function());
    }

    public function testFunctionParameterShadowsCapturedOuterBinding(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('value'), 5]),
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('value')]),
                new Symbol('value'),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(12, $function(12));
    }

    public function testFunctionReadsGlobalBindingFromParentEnvironment(): void
    {
        $compiler = new PhpCompiler();
        $parent = new Env('parent');
        $env = new Env('child', $parent);
        $parent->set('value', 42);
        $ast = new MList([
            new Symbol('fn'),
            new MList([]),
            new Symbol('value'),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(42, $function());
    }

    public function testCallsFunctionWithTwoArguments(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('a'), new Symbol('b')]),
                new MList([new Symbol('-'), new Symbol('a'), new Symbol('b')]),
            ]),
            10,
            3,
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(7, $program->execute($env));
    }

    public function testFunctionCanReadGlobalEnvironmentBindings(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('offset', 5);
        $ast = new MList([
            new Symbol('fn'),
            new MList([new Symbol('x')]),
            new MList([new Symbol('+'), new Symbol('x'), new Symbol('offset')]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(12, $function(7));
    }

    public function testFunctionRejectsDuplicateParameters(): void
    {
        $compiler = new PhpCompiler();
        $ast = new MList([
            new Symbol('fn'),
            new MList([new Symbol('x'), new Symbol('x')]),
            new Symbol('x'),
        ]);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('duplicate parameter x for fn');
        $compiler->compile($ast);
    }

    public function testCompilesAndExecutesEmptyDoAsNull(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');

        $program = $compiler->compile(new MList([new Symbol('do')]));

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertNull($program->execute($env));
    }

    public function testLooksUpUnboundSymbolInEnvironment(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('value', 42);

        $program = $compiler->compile(new Symbol('value'));

        $this->assertSame(42, $program->execute($env));
    }

    public function testLooksUpUnboundSymbolInParentEnvironment(): void
    {
        $compiler = new PhpCompiler();
        $parent = new Env('parent');
        $env = new Env('child', $parent);
        $parent->set('value', 42);

        $program = $compiler->compile(new Symbol('value'));

        $this->assertSame(42, $program->execute($env));
    }

    public function testThrowsWhenUnboundSymbolIsMissingFromEnvironment(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $program = $compiler->compile(new Symbol('missing'));

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol missing not defined in env');
        $program->execute($env);
    }

    public function testLocalLetBindingShadowsEnvironmentBinding(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('value', 10);
        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('value'), 20]),
            new Symbol('value'),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(20, $program->execute($env));
    }

    public function testLetInitializerCanReadEnvironmentBinding(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('value', 10);
        $ast = new MList([
            new Symbol('let'),
            new MList([
                new Symbol('result'),
                new MList([new Symbol('+'), new Symbol('value'), 5]),
            ]),
            new Symbol('result'),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(15, $program->execute($env));
    }
}

