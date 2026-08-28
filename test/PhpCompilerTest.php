<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\PhpCompiledProgram;
use MadLisp\PhpCompiler;
use MadLisp\Symbol;
use MadLisp\Vector;

class PhpCompilerTest extends TestCase
{
    public function testCompilesQuotedSymbolWithoutEnvironmentLookup(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([new Symbol('quote'), new Symbol('+')]);

        $program = $compiler->compile($ast);
        $value = $program->execute($env);

        $this->assertInstanceOf(Symbol::class, $value);
        $this->assertSame('+', $value->getName());
    }

    public function testCompilesQuotedNestedListAsData(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('quote'),
            new MList([
                new Symbol('a'),
                new MList([new Symbol('+'), 1, new Symbol('b')]),
                new Vector([new Symbol('c'), 2]),
                new Hash(['key' => new Symbol('value')]),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $value = $program->execute($env);

        $this->assertInstanceOf(MList::class, $value);
        $this->assertInstanceOf(Symbol::class, $value->getData()[0]);
        $this->assertSame('a', $value->getData()[0]->getName());
        $this->assertInstanceOf(MList::class, $value->getData()[1]);
        $this->assertSame('+', $value->getData()[1]->getData()[0]->getName());
        $this->assertInstanceOf(Vector::class, $value->getData()[2]);
        $this->assertInstanceOf(Hash::class, $value->getData()[3]);
    }

    public function testQuotedValueCanBeReturnedFromFunction(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new MList([
                new Symbol('fn'),
                new MList([]),
                new MList([new Symbol('quote'), new MList([new Symbol('value')])]),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $value = $program->execute($env);

        $this->assertInstanceOf(MList::class, $value);
        $this->assertSame('value', $value->getData()[0]->getName());
    }

    public function testDefStoresValueInExecutionEnvironment(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $ast = new MList([
            new Symbol('do'),
            new MList([new Symbol('def'), new Symbol('answer'), 42]),
            new Symbol('answer'),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
        $this->assertSame(42, $env->get('answer'));
    }

    public function testDefEvaluatesInitializerBeforeAssignment(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('value', 10);
        $ast = new MList([
            new Symbol('def'),
            new Symbol('value'),
            new MList([new Symbol('+'), new Symbol('value'), 5]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(15, $program->execute($env));
        $this->assertSame(15, $env->get('value'));
    }

    public function testDefRejectsReservedNamesAndCoreOperators(): void
    {
        foreach (['__FILE__', '__DIR__', '+'] as $name) {
            $compiler = new PhpCompiler();
            $ast = new MList([new Symbol('def'), new Symbol($name), 1]);

            $this->expectException(MadLispException::class);
            $compiler->compile($ast);
        }
    }

    public function testQuoteAndDefValidateTheirArguments(): void
    {
        $compiler = new PhpCompiler();

        $this->expectExceptionMessage('quote requires exactly 1 argument');
        $compiler->compile(new MList([new Symbol('quote')]));
    }

    public function testDefRequiresSymbolName(): void
    {
        $compiler = new PhpCompiler();

        $this->expectExceptionMessage('first argument to def is not symbol');
        $compiler->compile(new MList([new Symbol('def'), 1, 2]));
    }

    public function testMapsAndReducesUsingGeneratedFunctionAndCoreFunctions(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('map', new CoreFunc(
            'map',
            'doc',
            2,
            2,
            fn (callable $function, Vector $values): Vector => new Vector(
                array_map($function, $values->getData())
            )
        ));
        $env->set('reduce', new CoreFunc(
            'reduce',
            'doc',
            2,
            3,
            fn (callable $function, Vector $values, $initial = null) => array_reduce(
                $values->getData(),
                $function,
                $initial
            )
        ));
        $env->set('+', new CoreFunc('+', 'doc', 1, -1, fn (...$values) => array_sum($values)));

        $ast = new MList([
            new Symbol('let'),
            new MList([
                new Symbol('double'),
                new MList([
                    new Symbol('fn'),
                    new MList([new Symbol('value')]),
                    new MList([new Symbol('*'), new Symbol('value'), 2]),
                ]),
            ]),
            new MList([
                new Symbol('reduce'),
                new Symbol('+'),
                new MList([
                    new Symbol('map'),
                    new Symbol('double'),
                    new MList([
                        new Symbol('quote'),
                        new Vector([1, 2, 3, 4]),
                    ]),
                ]),
                0,
            ]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(20, $program->execute($env));
    }

    public function testCallsCoreFuncFromEnvironment(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('triple', new CoreFunc('triple', 'doc', 1, 1, fn ($value) => $value * 3));
        $ast = new MList([new Symbol('triple'), 14]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
    }

    public function testCallsGeneratedClosureFromEnvironment(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('double', fn ($value) => $value * 2);
        $ast = new MList([new Symbol('double'), 21]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
    }

    public function testDynamicCallEvaluatesArgumentsInOrder(): void
    {
        $compiler = new PhpCompiler();
        $env = new Env('root');
        $env->set('collect', new CoreFunc('collect', 'doc', 0, -1, fn (...$args) => $args));
        $ast = new MList([
            new Symbol('collect'),
            new MList([new Symbol('def'), new Symbol('first'), 1]),
            new MList([new Symbol('def'), new Symbol('second'), 2]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame([1, 2], $program->execute($env));
        $this->assertSame(2, $env->get('second'));
    }

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

