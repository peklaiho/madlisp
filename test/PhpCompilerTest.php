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
use MadLisp\Options;
use MadLisp\Symbol;
use MadLisp\Vector;

class PhpCompilerTest extends TestCase
{
    public function testCompilesQuotedSymbolWithoutEnvironmentLookup(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([new Symbol('quote'), new Symbol('+')]);

        $program = $compiler->compile($ast);
        $value = $program->execute($env);

        $this->assertInstanceOf(Symbol::class, $value);
        $this->assertSame('+', $value->getName());
    }

    public function testCompilesAndEvaluatesVectorLiteral(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new Vector([
            1,
            new MList([new Symbol('+'), 2, 3]),
            new Vector([new Symbol('value')]),
        ]);
        $env->set('value', 4);

        $program = $compiler->compile($ast);
        $value = $program->execute($env);

        $this->assertInstanceOf(Vector::class, $value);
        $this->assertSame(1, $value->getData()[0]);
        $this->assertSame(5, $value->getData()[1]);
        $this->assertInstanceOf(Vector::class, $value->getData()[2]);
        $this->assertSame([4], $value->getData()[2]->getData());
    }

    public function testCompilesAndEvaluatesHashLiteral(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new Hash([
            'answer' => new MList([new Symbol('+'), 40, 2]),
            'value' => new Symbol('value'),
            'nested' => new Vector([1, new Symbol('value')]),
        ]);
        $env->set('value', 7);

        $program = $compiler->compile($ast);
        $value = $program->execute($env);

        $this->assertInstanceOf(Hash::class, $value);
        $this->assertSame(42, $value->get('answer'));
        $this->assertSame(7, $value->get('value'));
        $this->assertSame([1, 7], $value->get('nested')->getData());
    }

    public function testCompilesQuotedNestedListAsData(): void
    {
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
            $compiler = new PhpCompiler(new Options());
            $ast = new MList([new Symbol('def'), new Symbol($name), 1]);

            $this->expectException(MadLispException::class);
            $compiler->compile($ast);
        }
    }

    public function testQuoteAndDefValidateTheirArguments(): void
    {
        $compiler = new PhpCompiler(new Options());

        $this->expectExceptionMessage('quote requires exactly 1 argument');
        $compiler->compile(new MList([new Symbol('quote')]));
    }

    public function testDefRequiresSymbolName(): void
    {
        $compiler = new PhpCompiler(new Options());

        $this->expectExceptionMessage('first argument to def is not symbol');
        $compiler->compile(new MList([new Symbol('def'), 1, 2]));
    }

    public function testMapsAndReducesUsingGeneratedFunctionAndCoreFunctions(): void
    {
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $env->set('triple', new CoreFunc('triple', 'doc', 1, 1, fn ($value) => $value * 3));
        $ast = new MList([new Symbol('triple'), 14]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
    }

    public function testCallsGeneratedClosureFromEnvironment(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $env->set('double', fn ($value) => $value * 2);
        $ast = new MList([new Symbol('double'), 21]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
    }

    public function testDynamicCallEvaluatesArgumentsInOrder(): void
    {
        $compiler = new PhpCompiler(new Options());
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

    public function testAndReturnsFirstFalseyValueAndShortCircuits(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('and'),
            1,
            0,
            new Symbol('missing'),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(0, $program->execute($env));
    }

    public function testOrReturnsFirstTruthyValueAndShortCircuits(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('or'),
            false,
            7,
            new Symbol('missing'),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(7, $program->execute($env));
    }

    public function testAndAndOrReturnTheirLastArgumentWhenNoEarlierMatchExists(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $and = $compiler->compile(new MList([new Symbol('and'), 1, 'result']))->execute($env);
        $or = $compiler->compile(new MList([new Symbol('or'), false, 'result']))->execute($env);

        $this->assertSame('result', $and);
        $this->assertSame('result', $or);
    }

    public function testAndAndOrUseTheirEmptyFormValues(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $and = $compiler->compile(new MList([new Symbol('and')]))->execute($env);
        $or = $compiler->compile(new MList([new Symbol('or')]))->execute($env);

        $this->assertTrue($and);
        $this->assertFalse($or);
    }

    public function testCondEvaluatesMatchingClauseExpressionsInOrder(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('cond'),
            new MList([
                false,
                new MList([new Symbol('def'), new Symbol('value'), 1]),
                10,
            ]),
            new MList([
                true,
                new MList([new Symbol('def'), new Symbol('value'), 2]),
                new Symbol('value'),
            ]),
            new MList([
                new Symbol('else'),
                99,
            ]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(2, $program->execute($env));
        $this->assertSame(2, $env->get('value'));
    }

    public function testCondShortCircuitsLaterClausesAndReturnsNullWithoutMatch(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $ast = new MList([
            new Symbol('cond'),
            new MList([false, new Symbol('missing')]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertNull($program->execute($env));
    }

    public function testCondSupportsElseClause(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('cond'),
            new MList([false, 1]),
            new MList([new Symbol('else'), 'fallback']),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame('fallback', $program->execute($env));
    }

    public function testCondRequiresSequenceClausesWithAtLeastTwoItems(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('argument to cond is not seq');
        $compiler->compile(new MList([new Symbol('cond'), true]));
    }

    public function testSimpleConditionsSkipTemporaryVariables(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $if = $compiler->compile(new MList([
            new Symbol('let'),
            new MList([new Symbol('condition'), true]),
            new MList([new Symbol('if'), new Symbol('condition'), 1, 2]),
        ]));
        $this->assertSame(1, $if->execute($env));
        $this->assertStringContainsString('if ($v0) {', $if->getSource());
        $this->assertStringNotContainsString('$t0 = $v0;', $if->getSource());

        $cond = $compiler->compile(new MList([
            new Symbol('let'),
            new MList([new Symbol('condition'), false]),
            new MList([
                new Symbol('cond'),
                new MList([new Symbol('condition'), 1]),
                new MList([new Symbol('else'), 2]),
            ]),
        ]));
        $this->assertSame(2, $cond->execute($env));
        $this->assertStringContainsString('if ($v0) {', $cond->getSource());
        $this->assertStringNotContainsString('$t0 = $v0;', $cond->getSource());

        $while = $compiler->compile(new MList([
            new Symbol('let'),
            new MList([new Symbol('condition'), false]),
            new MList([new Symbol('while'), new Symbol('condition'), 1]),
        ]));
        $this->assertNull($while->execute($env));
        $this->assertStringContainsString('if (!$v0) {', $while->getSource());
        $this->assertStringNotContainsString('$t0 = $v0;', $while->getSource());
    }

    public function testCaseEvaluatesTestOnceAndReturnsMatchingClauseResult(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('case'),
            new MList([
                new Symbol('do'),
                new MList([new Symbol('def'), new Symbol('value'), 2]),
                new Symbol('value'),
            ]),
            new MList([
                1,
                new MList([new Symbol('def'), new Symbol('matched'), 1]),
                10,
            ]),
            new MList([
                2,
                new MList([new Symbol('def'), new Symbol('matched'), 2]),
                20,
            ]),
            new MList([new Symbol('else'), 30]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(20, $program->execute($env));
        $this->assertSame(2, $env->get('value'));
        $this->assertSame(2, $env->get('matched'));
    }

    public function testCaseEvaluatesIntermediateExpressionsAndReturnsNullWithoutMatch(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('case'),
            3,
            new MList([
                1,
                new MList([new Symbol('def'), new Symbol('visited'), true]),
                10,
            ]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertNull($program->execute($env));
        $this->expectException(MadLispException::class);
        $env->get('visited');
    }

    public function testCaseAndCaseStrictUseDifferentRawComparisons(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $case = $compiler->compile(new MList([
            new Symbol('case'),
            true,
            new MList([1, 'matched']),
        ]))->execute($env);
        $strict = $compiler->compile(new MList([
            new Symbol('case-strict'),
            true,
            new MList([1, 'matched']),
        ]))->execute($env);

        $this->assertSame('matched', $case);
        $this->assertNull($strict);
    }

    public function testCaseRequiresSequenceClausesWithAtLeastTwoItems(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('argument to case is not seq');
        $compiler->compile(new MList([new Symbol('case'), 1, true]));
    }

    public function testEnvReturnsTheExecutionEnvironment(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $program = $compiler->compile(new MList([new Symbol('env')]));

        $this->assertSame($env, $program->execute($env));
    }

    public function testEnvReturnsTheExecutionEnvironmentInsideAFunction(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('fn'),
            new MList([]),
            new MList([new Symbol('env')]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame($env, $function());
    }

    public function testEnvRejectsArguments(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('env does not take arguments');
        $compiler->compile(new MList([new Symbol('env'), 1]));
    }

    public function testUndefRemovesBindingAndReturnsItsPreviousValue(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $env->set('value', 42);
        $ast = new MList([new Symbol('undef'), new Symbol('value')]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
        $this->assertFalse($env->has('value'));
    }

    public function testUndefValidatesItsArgument(): void
    {
        $compiler = new PhpCompiler(new Options());

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('first argument to undef is not symbol');
        $compiler->compile(new MList([new Symbol('undef'), 1]));
    }

    public function testCompilesEmptyAndLenForCollections(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $cases = [
            ['empty?', new Vector([]), true],
            ['empty?', new Hash(['key' => 1]), false],
            ['len', new Vector([1, 2, 3]), 3],
            ['len', new Hash(['key' => 1]), 1],
        ];

        foreach ($cases as [$operator, $argument, $expected]) {
            $program = $compiler->compile(new MList([
                new Symbol($operator),
                $argument,
            ]));

            $this->assertSame($expected, $program->execute($env));
        }
    }

    public function testEmptyAndLenRejectUnsupportedValues(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $this->expectException(Error::class);
        $compiler->compile(new MList([new Symbol('empty?'), 1]))->execute($env);
    }

    public function testEmptyAndLenRequireExactlyOneArgument(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('len requires exactly 1 argument');
        $compiler->compile(new MList([new Symbol('len')]));
    }

    public function testCompilesCarAndMathBuiltinsDirectly(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $cases = [
            ['car', new MList([new Symbol('quote'), new MList([9, 10])]), 9, 'getData()[0]'],
            ['abs', -5, 5, 'abs(-5)'],
            ['floor', 2.9, 2, 'intval(floor(2.9))'],
            ['ceil', 2.1, 3, 'intval(ceil(2.1))'],
            ['pow', 2, null, 'pow(2'],
            ['sqrt', 9, 3.0, 'sqrt(9)'],
        ];

        foreach ($cases as [$operator, $argument, $expected, $source]) {
            $arguments = [$argument];
            if ($operator === 'pow') {
                $arguments[] = 3;
                $expected = 8;
            }

            $program = $compiler->compile(new MList([
                new Symbol($operator),
                ...$arguments,
            ]));

            $this->assertStringContainsString($source, $program->getSource());
            $this->assertSame($expected, $program->execute($env));
        }
    }

    public function testCompilesStringLengthAndLastDirectly(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $strlen = $compiler->compile(new MList([
            new Symbol('strlen'),
            'MadLisp',
        ]));
        $this->assertStringContainsString("strlen('MadLisp')", $strlen->getSource());
        $this->assertSame(7, $strlen->execute($env));

        $last = $compiler->compile(new MList([
            new Symbol('last'),
            new MList([new Symbol('quote'), new MList([1, 2, 3])]),
        ]));
        $this->assertStringContainsString('getData()[', $last->getSource());
        $this->assertStringContainsString('->count() - 1]', $last->getSource());
        $this->assertSame(3, $last->execute($env));
    }

    public function testCompilesConsDirectly(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $list = $compiler->compile(new MList([
            new Symbol('cons'),
            1,
            2,
            new MList([new Symbol('quote'), new MList([3, 4])]),
        ]));

        $this->assertStringContainsString('::new(array_merge([1, 2]', $list->getSource());
        $this->assertSame([1, 2, 3, 4], $list->execute($env)->getData());

        $vector = $compiler->compile(new MList([
            new Symbol('cons'),
            1,
            new Vector([2, 3]),
        ]));

        $this->assertSame(Vector::class, get_class($vector->execute($env)));
        $this->assertSame([1, 2, 3], $vector->execute($env)->getData());
    }

    public function testCompilesCdrDirectly(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $program = $compiler->compile(new MList([
            new Symbol('cdr'),
            new MList([new Symbol('quote'), new MList([1, 2, 3])]),
        ]));

        $this->assertStringContainsString('::new(array_slice(', $program->getSource());
        $this->assertSame([2, 3], $program->execute($env)->getData());
    }

    public function testCompilesGetAndKeyPredicateDirectly(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $get = $compiler->compile(new MList([
            new Symbol('get'),
            new Vector([10, 20, 30]),
            1,
        ]));
        $this->assertStringContainsString('->get(1)', $get->getSource());
        $this->assertSame(20, $get->execute($env));

        $key = $compiler->compile(new MList([
            new Symbol('key?'),
            new Hash(['name' => 'MadLisp']),
            'name',
        ]));
        $this->assertStringContainsString('->has(\'name\')', $key->getSource());
        $this->assertTrue($key->execute($env));
    }

    public function testFastPathBuiltinsValidateArity(): void
    {
        $compiler = new PhpCompiler(new Options());

        foreach ([
            ['car', 0, 'car requires exactly 1 argument'],
            ['cdr', 0, 'cdr requires exactly 1 argument'],
            ['cons', 1, 'cons requires at least 2 arguments'],
            ['last', 0, 'last requires exactly 1 argument'],
            ['get', 1, 'get requires exactly 2 arguments'],
            ['key?', 1, 'key? requires exactly 2 arguments'],
            ['abs', 0, 'abs requires exactly 1 argument'],
            ['floor', 0, 'floor requires exactly 1 argument'],
            ['ceil', 0, 'ceil requires exactly 1 argument'],
            ['pow', 1, 'pow requires exactly 2 arguments'],
            ['sqrt', 0, 'sqrt requires exactly 1 argument'],
            ['strlen', 0, 'strlen requires exactly 1 argument'],
        ] as [$operator, $argumentCount, $message]) {
            $this->expectException(MadLispException::class);
            $this->expectExceptionMessage($message);
            $compiler->compile(new MList(array_merge(
                [new Symbol($operator)],
                array_fill(0, $argumentCount, 1)
            )));
        }
    }

    public function testCompilesNumericPredicates(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $values = [
            'zero?' => [0, true],
            'one?' => [1, true],
            'even?' => [8, true],
            'odd?' => [7, true],
        ];

        foreach ($values as $operator => [$argument, $expected]) {
            $program = $compiler->compile(new MList([
                new Symbol($operator),
                $argument,
            ]));

            $this->assertSame($expected, $program->execute($env));
        }

        $this->assertFalse($compiler->compile(new MList([
            new Symbol('zero?'),
            1,
        ]))->execute($env));
        $this->assertFalse($compiler->compile(new MList([
            new Symbol('one?'),
            0,
        ]))->execute($env));
        $this->assertFalse($compiler->compile(new MList([
            new Symbol('even?'),
            7,
        ]))->execute($env));
        $this->assertFalse($compiler->compile(new MList([
            new Symbol('odd?'),
            8,
        ]))->execute($env));
    }

    public function testNumericPredicatesRequireExactlyOneArgument(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('zero? requires exactly 1 argument');
        $compiler->compile(new MList([new Symbol('zero?')]));
    }

    public function testCompilesModuloWithMultipleArguments(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('%'),
            28,
            10,
            3,
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(2, $program->execute($env));
    }

    public function testModuloRequiresAtLeastTwoArguments(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('% requires at least 2 argument');
        $compiler->compile(new MList([new Symbol('%'), 1]));
    }

    public function testCompilesLooseAndStrictInequality(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $loose = $compiler->compile(new MList([
            new Symbol('!='),
            1,
            true,
        ]))->execute($env);
        $strict = $compiler->compile(new MList([
            new Symbol('!=='),
            1,
            true,
        ]))->execute($env);

        $this->assertFalse($loose);
        $this->assertTrue($strict);
    }

    public function testInequalityFormsRequireExactlyTwoArguments(): void
    {
        $compiler = new PhpCompiler(new Options());

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('!= requires exactly 2 arguments');
        $compiler->compile(new MList([new Symbol('!='), 1]));
    }

    public function testWhileRepeatsUntilItsConditionBecomesFalse(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('do'),
            new MList([new Symbol('def'), new Symbol('counter'), 3]),
            new MList([
                new Symbol('while'),
                new Symbol('counter'),
                new MList([
                    new Symbol('def'),
                    new Symbol('counter'),
                    new MList([new Symbol('dec'), new Symbol('counter')]),
                ]),
                new Symbol('counter'),
            ]),
            new MList([new Symbol('undef'), new Symbol('counter')]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(0, $program->execute($env));
        $this->assertFalse($env->has('counter'));
    }

    public function testWhileReturnsNullWhenItDoesNotRun(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('while'),
            false,
            new MList([new Symbol('def'), new Symbol('visited'), true]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertNull($program->execute($env));
        $this->assertFalse($env->has('visited'));
    }

    public function testWhileRequiresAConditionAndBody(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('while requires at least 2 arguments');
        $compiler->compile(new MList([new Symbol('while'), true]));
    }

    public function testTryCatchesThrowableAndEvaluatesAnArbitraryHandler(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('try'),
            new MList([new Symbol('/'), 1, 0]),
            new MList([
                new Symbol('catch'),
                new Symbol('error'),
                new MList([
                    new Symbol('do'),
                    new MList([new Symbol('def'), new Symbol('handled'), true]),
                    new Symbol('error'),
                ]),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $value = $program->execute($env);

        $this->assertInstanceOf(\Throwable::class, $value);
        $this->assertTrue($env->get('handled'));
    }

    public function testTryReturnsBodyResultWhenNoThrowableIsRaised(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('try'),
            42,
            new MList([new Symbol('catch'), new Symbol('error'), 0]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env));
    }

    public function testTryValidatesItsCatchForm(): void
    {
        $compiler = new PhpCompiler(new Options());
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('invalid form for catch');
        $compiler->compile(new MList([
            new Symbol('try'),
            1,
            new MList([new Symbol('not-catch'), new Symbol('error'), 2]),
        ]));
    }

    public function testCompilesIntegerDivisionWithIntdiv(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([new Symbol('//'), 7, 2]);

        $program = $compiler->compile($ast);

        $this->assertStringContainsString('intdiv(7, 2)', $program->getSource());
        $this->assertSame(3, $program->execute($env));
    }

    public function testIntegerDivisionRequiresExactlyTwoArguments(): void
    {
        $compiler = new PhpCompiler(new Options());

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('// requires exactly 2 arguments');
        $compiler->compile(new MList([new Symbol('//'), 8]));
    }

    public function testCompilesAndExecutesArithmetic(): void
    {
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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

    public function testLetUsesLocalAsDestinationForComplexInitializer(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new MList([
                new Symbol('value'),
                new MList([new Symbol('+'), 1, 2]),
            ]),
            new Symbol('value'),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(3, $program->execute($env));
        $this->assertStringContainsString('$v0 = 1 + 2;', $program->getSource());
        $this->assertStringNotContainsString('$t0 = 1 + 2;', $program->getSource());
    }

    public function testCompilesAndExecutesLetWithShadowing(): void
    {
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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

    public function testDiscardedFunctionExpressionsAreNotEmitted(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $program = $compiler->compile(new MList([
            new Symbol('do'),
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('value')]),
                new Symbol('value'),
            ]),
            4,
        ]));

        $this->assertSame(4, $program->execute($env));
        $this->assertStringNotContainsString('static function ($v0)', $program->getSource());
    }

    public function testDiscardedDoResultsDoNotNeedAssignments(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $observed = [];
        $env->set('observe', function ($value) use (&$observed) {
            $observed[] = $value;
            return 'ignored';
        });
        $ast = new MList([
            new Symbol('do'),
            1,
            new MList([new Symbol('observe'), 7]),
            new MList([new Symbol('+'), 1, 2]),
            4,
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(4, $program->execute($env));
        $this->assertSame([7], $observed);
        $this->assertStringContainsString('$t0(7);', $program->getSource());
        $this->assertStringContainsString('1 + 2;', $program->getSource());
        $this->assertStringNotContainsString('$t0 = $t0(7);', $program->getSource());
        $this->assertStringNotContainsString('$t1 = 1 + 2;', $program->getSource());
    }

    public function testCompilesFunctionWithParameters(): void
    {
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');

        $program = $compiler->compile(new MList([new Symbol('do')]));

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertNull($program->execute($env));
    }

    public function testRecursiveDefinitionUsesACompiledLoopWithoutSelfCapture(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('def'),
            new Symbol('count-down'),
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('n')]),
                new MList([
                    new Symbol('if'),
                    new MList([new Symbol('='), new Symbol('n'), 0]),
                    0,
                    new MList([
                        new Symbol('count-down'),
                        new MList([new Symbol('dec'), new Symbol('n')]),
                    ]),
                ]),
            ]),
        ]);

        $program = $compiler->compile($ast);
        $function = $program->execute($env);

        $this->assertSame(0, $function(20));
        $this->assertStringNotContainsString('$env->get(\'count-down\')', $program->getSource());
        $this->assertStringContainsString('while (true)', $program->getSource());
        $this->assertStringContainsString('continue;', $program->getSource());
        $this->assertStringNotContainsString('&$', $program->getSource());
    }

    public function testNamedLetCompilesAsARecursiveLoop(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('let'),
            new Symbol('loop'),
            new MList([new Symbol('n'), 20, new Symbol('acc'), 0]),
            new MList([
                new Symbol('if'),
                new MList([new Symbol('='), new Symbol('n'), 0]),
                new Symbol('acc'),
                new MList([
                    new Symbol('loop'),
                    new MList([new Symbol('dec'), new Symbol('n')]),
                    new MList([new Symbol('+'), new Symbol('acc'), new Symbol('n')]),
                ]),
            ]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(210, $program->execute($env));
        $this->assertStringContainsString('while (true)', $program->getSource());
        $this->assertStringContainsString('continue;', $program->getSource());
        $this->assertStringNotContainsString('$env->get(\'loop\')', $program->getSource());
    }

    public function testNonRecursiveDefinitionDoesNotCaptureItself(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $ast = new MList([
            new Symbol('def'),
            new Symbol('identity'),
            new MList([
                new Symbol('fn'),
                new MList([new Symbol('value')]),
                new Symbol('value'),
            ]),
        ]);

        $program = $compiler->compile($ast);

        $this->assertSame(42, $program->execute($env)(42));
        $this->assertStringNotContainsString('&$', $program->getSource());
    }

    public function testLooksUpUnboundSymbolInEnvironment(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $env->set('value', 42);

        $program = $compiler->compile(new Symbol('value'));

        $this->assertSame(42, $program->execute($env));
    }

    public function testLooksUpUnboundSymbolInParentEnvironment(): void
    {
        $compiler = new PhpCompiler(new Options());
        $parent = new Env('parent');
        $env = new Env('child', $parent);
        $parent->set('value', 42);

        $program = $compiler->compile(new Symbol('value'));

        $this->assertSame(42, $program->execute($env));
    }

    public function testThrowsWhenUnboundSymbolIsMissingFromEnvironment(): void
    {
        $compiler = new PhpCompiler(new Options());
        $env = new Env('root');
        $program = $compiler->compile(new Symbol('missing'));

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol missing not defined in env');
        $program->execute($env);
    }

    public function testLocalLetBindingShadowsEnvironmentBinding(): void
    {
        $compiler = new PhpCompiler(new Options());
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
        $compiler = new PhpCompiler(new Options());
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

