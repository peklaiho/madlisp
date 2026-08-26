<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\IrCompiler;
use MadLisp\IrCompiledFuncTemplate;
use MadLisp\CoreFunc;
use MadLisp\IrCoreFuncId;
use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\IrExecutor;
use MadLisp\MList;
use MadLisp\MadLispException;
use MadLisp\IrOpCode;
use MadLisp\Symbol;
use MadLisp\Vector;

class IrCompilerTest extends TestCase
{
    public function testCompilesVectorLiteral(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));

        $program = $compiler->compile(new Vector([
            1,
            new MList([new Symbol('+'), 2, 3]),
        ]));

        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
            IrOpCode::BUILD_VECTOR, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertEquals(new Vector([1, 5]), $executor->execute($program, $env));
    }

    public function testCompilesHashLiteral(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('value', 42);

        $program = $compiler->compile(new Hash([
            'first' => 1,
            'second' => new Symbol('value'),
        ]));

        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::LOAD_GLOBAL, 1,
            IrOpCode::BUILD_HASH, 2, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame(['first', 'second'], $program->getConstants()[2]);
        $this->assertEquals(new Hash(['first' => 1, 'second' => 42]), $executor->execute($program, $env));
    }

    public function testCompilesEmptyVectorAndHashLiterals(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $vector = $compiler->compile(new Vector());
        $hash = $compiler->compile(new Hash());

        $this->assertEquals(new Vector(), $executor->execute($vector, $env));
        $this->assertEquals(new Hash(), $executor->execute($hash, $env));
    }

    public function testCompilesLiteral(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');

        $program = $compiler->compile(42);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([42], $program->getConstants());
        $this->assertSame(0, $program->getLocalCount());
    }

    public function testCompilesGlobalSymbol(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');

        $program = $compiler->compile(new Symbol('value'));

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_GLOBAL, 0,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame(['value'], $program->getConstants());
    }

    public function testCompilesIf(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('if'),
            true,
            1,
            2,
        ]);

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::JUMP_IF_FALSE, 8,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::JUMP, 10,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([true, 1, 2], $program->getConstants());
    }

    public function testCompilesIfWithoutElse(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('if'),
            false,
            1,
        ]);

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::JUMP_IF_FALSE, 8,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::JUMP, 10,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([false, 1, null], $program->getConstants());
    }

    public function testCompilesNestedIf(): void
    {
        $compiler = new IrCompiler();
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

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::JUMP_IF_FALSE, 16,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::JUMP_IF_FALSE, 12,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::JUMP, 14,
            IrOpCode::LOAD_CONSTANT, 3,
            IrOpCode::JUMP, 18,
            IrOpCode::LOAD_CONSTANT, 4,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([true, false, 1, 2, 3], $program->getConstants());
    }

    public function testCompilesCondBranchesAndReturnsLastClauseValue(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([
            new Symbol('cond'),
            new MList([false, 1]),
            new MList([true, 2, 3]),
            new MList([new Symbol('else'), 4]),
        ]));

        $this->assertSame(3, $executor->execute($program, $env));
    }

    public function testCompilesCondWithNoMatchingClauseAsNull(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([
            new Symbol('cond'),
            new MList([false, 1]),
            new MList([false, 2]),
        ]));

        $this->assertNull($executor->execute($program, $env));
    }

    public function testCompilesCaseAndCaseStrict(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $case = $compiler->compile(new MList([
            new Symbol('case'),
            1,
            new MList([2, 'two']),
            new MList([1, 'one']),
            new MList([new Symbol('else'), 'other']),
        ]));
        $caseStrict = $compiler->compile(new MList([
            new Symbol('case-strict'),
            1,
            new MList(['1', 'string']),
            new MList([1, 'integer']),
        ]));

        $this->assertSame('one', $executor->execute($case, $env));
        $this->assertSame('integer', $executor->execute($caseStrict, $env));
    }

    public function testCaseReturnsNullWhenNothingMatches(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([
            new Symbol('case'),
            3,
            new MList([1, 'one']),
            new MList([2, 'two']),
        ]));

        $this->assertNull($executor->execute($program, $env));
    }

    public function testCompilesDef(): void
    {
        $compiler = new IrCompiler();

        $ast = new MList([
            new Symbol('def'),
            new Symbol('value'),
            new MList([new Symbol('+'), 1, 2]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
            IrOpCode::STORE_GLOBAL, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([1, 2, 'value'], $program->getConstants());
    }

    public function testExecutesDefInRuntimeEnvironment(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([
            new Symbol('def'),
            new Symbol('value'),
            42,
        ]));

        $this->assertSame(42, $executor->execute($program, $env));
        $this->assertSame(42, $env->get('value'));
    }

    public function testCompilesAndExecutesDeepTailRecursiveFunction(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([
            new Symbol('do'),
            new MList([
                new Symbol('def'),
                new Symbol('countdown'),
                new MList([
                    new Symbol('fn'),
                    new MList([new Symbol('n')]),
                    new MList([
                        new Symbol('if'),
                        new Symbol('n'),
                        new MList([new Symbol('countdown'), new MList([new Symbol('dec'), new Symbol('n')])]),
                        0,
                    ]),
                ]),
            ]),
            new MList([new Symbol('countdown'), 10000]),
        ]));

        $templates = array_values(array_filter(
            $program->getConstants(),
            fn ($constant) => $constant instanceof IrCompiledFuncTemplate
        ));

        $this->assertCount(1, $templates);
        $this->assertContains(IrOpCode::TAIL_CALL, $templates[0]->program->getCode());
        $this->assertSame(0, $executor->execute($program, $env));
    }

    public function testCompilesQuote(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');
        $quoted = new MList([new Symbol('value'), 1]);

        $program = $compiler->compile(new MList([
            new Symbol('quote'),
            $quoted,
        ]));

        $this->assertSame($quoted, $executor->execute($program, $env));
    }

    public function testCompilesWhileWithMultipleBodyExpressions(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('running', true);

        $program = $compiler->compile(new MList([
            new Symbol('while'),
            new Symbol('running'),
            new MList([new Symbol('def'), new Symbol('running'), false]),
            42,
        ]));

        $this->assertSame(42, $executor->execute($program, $env));
        $this->assertSame(false, $env->get('running'));
    }

    public function testWhileReturnsNullWhenItDoesNotRun(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([
            new Symbol('while'),
            false,
            42,
        ]));

        $this->assertNull($executor->execute($program, $env));
    }

    public function testCompilesAndAndOrWithShortCircuiting(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $and = $compiler->compile(new MList([
            new Symbol('and'),
            false,
            new Symbol('missing'),
        ]));
        $or = $compiler->compile(new MList([
            new Symbol('or'),
            true,
            new Symbol('missing'),
        ]));

        $this->assertSame(false, $executor->execute($and, $env));
        $this->assertSame(true, $executor->execute($or, $env));
    }

    public function testCompilesEmptyAndAndOr(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $and = $compiler->compile(new MList([new Symbol('and')]));
        $or = $compiler->compile(new MList([new Symbol('or')]));

        $this->assertTrue($executor->execute($and, $env));
        $this->assertFalse($executor->execute($or, $env));
    }

    public function testAndAndOrReturnOperandValues(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $and = $compiler->compile(new MList([
            new Symbol('and'),
            1,
            2,
        ]));
        $or = $compiler->compile(new MList([
            new Symbol('or'),
            false,
            7,
        ]));

        $this->assertSame(2, $executor->execute($and, $env));
        $this->assertSame(7, $executor->execute($or, $env));
    }

    public function testCompilesDoInOrderAndReturnsLastValue(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([
            new Symbol('do'),
            new MList([new Symbol('def'), new Symbol('value'), 1]),
            new MList([new Symbol('def'), new Symbol('value'), 2]),
            new Symbol('value'),
        ]));

        $this->assertSame(2, $executor->execute($program, $env));
        $this->assertSame(2, $env->get('value'));
    }

    public function testCompilesEmptyDoAsNull(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([new Symbol('do')]));

        $this->assertNull($executor->execute($program, $env));
    }

    public function testCompilesCall(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('+'),
            1,
            2,
        ]);

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([1, 2], $program->getConstants());
    }

    public function testCompilesExecuteAndLoad(): void
    {
        $compiler = new IrCompiler();

        foreach (['execute' => IrOpCode::EXECUTE_PROGRAM, 'load' => IrOpCode::LOAD_FILE] as $name => $opcode) {
            $program = $compiler->compile(new MList([new Symbol($name), 42]));

            $this->assertSame([
                IrOpCode::LOAD_CONSTANT, 0,
                $opcode,
                IrOpCode::RETURN,
            ], $program->getCode());
            $this->assertSame([42], $program->getConstants());
        }
    }

    public function testCompilesSupportedCoreCall(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));

        $ast = new MList([
            new Symbol('+'),
            1,
            2,
        ]);

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([1, 2], $program->getConstants());
    }

    public function testRejectsCoreCallBelowMinimumArity(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');
        $env->set('*', new CoreFunc('*', '', 2, -1, fn (...$args) => $args[0] * $args[1]));

        $ast = new MList([
            new Symbol('*'),
            2,
        ]);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('* requires at least 2 arguments');

        $compiler->compile($ast);
    }

    public function testCompilesSequentialLetBindings(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));

        $ast = new MList([
            new Symbol('let'),
            new MList([
                new Symbol('x'), 2,
                new Symbol('y'), new MList([new Symbol('+'), new Symbol('x'), 1]),
            ]),
            new MList([new Symbol('+'), new Symbol('y'), 3]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::STORE_LOCAL, 0,
            IrOpCode::LOAD_LOCAL, 0,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
            IrOpCode::STORE_LOCAL, 1,
            IrOpCode::LOAD_LOCAL, 1,
            IrOpCode::LOAD_CONSTANT, 2,
            IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
            IrOpCode::RETURN,
        ], $program->getCode());
        $this->assertSame([2, 1, 3], $program->getConstants());
        $this->assertSame(2, $program->getLocalCount());
    }

    public function testCompilesLetBodyExpressionsWithPop(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('x'), 2]),
            new Symbol('x'),
            3,
        ]);

        $program = $compiler->compile($ast);

        $this->assertNotNull($program);
        $this->assertSame([
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::STORE_LOCAL, 0,
            IrOpCode::LOAD_LOCAL, 0,
            IrOpCode::POP,
            IrOpCode::LOAD_CONSTANT, 1,
            IrOpCode::RETURN,
        ], $program->getCode());
    }

    public function testRejectsMalformedLet(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');

        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('x')]),
            1,
        ]);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('uneven number of bindings for let');

        $compiler->compile($ast);
    }

    public function testCompilesNonCapturingFn(): void
    {
        $compiler = new IrCompiler();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));

        $ast = new MList([
            new Symbol('fn'),
            new MList([new Symbol('x')]),
            new MList([new Symbol('+'), new Symbol('x'), 1]),
        ]);

        $program = $compiler->compile($ast);
        $template = $program->getConstants()[0];

        $this->assertNotNull($program);
        $this->assertSame([IrOpCode::MAKE_FUNCTION, 0, IrOpCode::RETURN], $program->getCode());
        $this->assertInstanceOf(IrCompiledFuncTemplate::class, $template);
        $this->assertSame([
            IrOpCode::LOAD_LOCAL, 0,
            IrOpCode::LOAD_CONSTANT, 0,
            IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
            IrOpCode::RETURN,
        ], $template->program->getCode());
        $this->assertSame(1, $template->program->getLocalCount());
    }

    public function testExecutesCompiledFnCall(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));

        $ast = new MList([
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('x')]),
                new MList([new Symbol('+'), new Symbol('x'), 1]),
            ]),
            4,
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(5, $executor->execute($program, $env));
    }

    public function testExecutesClosureWithCopiedCapture(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('+', new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args)));

        $ast = new MList([
            new Symbol('let'),
            new MList([new Symbol('x'), 10]),
            new MList([
                new MList([
                    new Symbol('fn'),
                    new MList([new Symbol('y')]),
                    new MList([new Symbol('+'), new Symbol('x'), new Symbol('y')]),
                ]),
                5,
            ]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(15, $executor->execute($program, $env));
    }

    public function testCompilesEnv(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');

        $program = $compiler->compile(new MList([new Symbol('env')]));

        $this->assertNotNull($program);
        $this->assertSame([IrOpCode::LOAD_ENV, IrOpCode::RETURN], $program->getCode());
        $this->assertSame($env, $executor->execute($program, $env));
    }

    public function testCompilesUndef(): void
    {
        $compiler = new IrCompiler();
        $executor = new IrExecutor();
        $env = new Env('root');
        $env->set('value', 42);

        $program = $compiler->compile(new MList([
            new Symbol('undef'),
            new Symbol('value'),
        ]));

        $this->assertNotNull($program);
        $this->assertSame([IrOpCode::UNDEF, 0, IrOpCode::RETURN], $program->getCode());
        $this->assertSame(['value'], $program->getConstants());
        $this->assertSame(42, $executor->execute($program, $env));
        $this->assertNull($env->get('value', false));
    }

    public function testRejectsMalformedEnvAndUndef(): void
    {
        $compiler = new IrCompiler();

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('env does not take arguments');
        $compiler->compile(new MList([new Symbol('env'), 1]));
    }
}
