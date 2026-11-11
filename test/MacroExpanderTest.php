<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\LispFactory;
use MadLisp\Options;
use MadLisp\MacroExpander;

class MacroExpanderTest extends TestCase
{
    private function getLispAndExpander(): array
    {
        $lisp = (new LispFactory())->make(new Options());
        $macroExpander = new MacroExpander();

        return [$lisp, $macroExpander];
    }

    public function testExpandsDefn(): void
    {
        [$lisp, $macroExpander] = $this->getLispAndExpander();
        $env = $lisp->getEnv();

        $ast = $lisp->read('(defn add (a b) (+ a b))');
        $expanded = $macroExpander->expand($ast, $env);

        $expected = $lisp->read('(def add (fn (a b) (+ a b)))');
        $this->assertEquals($expected, $expanded);
    }

    public function testExpandsWhen(): void
    {
        [$lisp, $macroExpander] = $this->getLispAndExpander();
        $env = $lisp->getEnv();

        $ast = $lisp->read('(when test (+ 1 2))');
        $expanded = $macroExpander->expand($ast, $env);

        $expected = $lisp->read('(if test (+ 1 2) null)');
        $this->assertEquals($expected, $expanded);
    }

    public function testExpandsUnless(): void
    {
        [$lisp, $macroExpander] = $this->getLispAndExpander();
        $env = $lisp->getEnv();

        $ast = $lisp->read('(unless test (+ 1 2))');
        $expanded = $macroExpander->expand($ast, $env);

        $expected = $lisp->read('(if test null (+ 1 2))');
        $this->assertEquals($expected, $expanded);
    }

    public function testRecursivelyExpandsNestedForms(): void
    {
        [$lisp, $macroExpander] = $this->getLispAndExpander();
        $env = $lisp->getEnv();

        $ast = $lisp->read('(when test (unless other body))');
        $expanded = $macroExpander->expand($ast, $env);

        $expected = $lisp->read('(if test (if other null body) null)');
        $this->assertEquals($expected, $expanded);
    }

    public function testDoesNotExpandQuotedForms(): void
    {
        [$lisp, $macroExpander] = $this->getLispAndExpander();
        $env = $lisp->getEnv();

        $ast = $lisp->read('(quote (when test body))');
        $expanded = $macroExpander->expand($ast, $env);

        $this->assertEquals($ast, $expanded);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('structuredExpansionProvider')]
    public function testExpandsExpressionPositionsWithLexicalScope(string $source, string $expected): void
    {
        [$lisp, $macroExpander] = $this->getLispAndExpander();

        $this->assertEquals(
            $lisp->read($expected),
            $macroExpander->expand($lisp->read($source), $lisp->getEnv())
        );
    }

    public static function structuredExpansionProvider(): array
    {
        return [
            'list parameters' => [
                '(fn (when x y) (when x y))',
                '(fn (when x y) (when x y))',
            ],
            'vector parameters and nested closure' => [
                '(fn [when] (fn [] (when true 1)))',
                '(fn [when] (fn [] (when true 1)))',
            ],
            'list bindings' => [
                '(let (when (fn [a b] a)) (when 1 2))',
                '(let (when (fn [a b] a)) (when 1 2))',
            ],
            'sequential bindings' => [
                '(let [when (when true (fn [a b] a)) x (when 1 2)] x)',
                '(let [when (if true (fn [a b] a) null) x (when 1 2)] x)',
            ],
            'scope does not leak' => [
                '(do (let [when f] (when 1 2)) (when true 3))',
                '(do (let [when f] (when 1 2)) (if true 3 null))',
            ],
            'named let scope starts after initializers' => [
                '(let when [x (when true 1)] (when x 2))',
                '(let when [x (if true 1 null)] (when x 2))',
            ],
            'case labels' => [
                "(case (when true x) ((when true 1) (when true 7)) (else 9))",
                "(case (if true x null) ((when true 1) (if true 7 null)) (else 9))",
            ],
            'strict case vector clause' => [
                '(case-strict x [(when true 1) (unless false 7)] [else 9])',
                '(case-strict x [(when true 1) (if false null 7)] [else 9])',
            ],
            'cond clause container' => [
                '(cond (when (when true 7)) (else 9))',
                '(cond (when (if true 7 null)) (else 9))',
            ],
            'catch binding and scope' => [
                '(do (try (when true x) (catch when (when 1 2))) (when true 3))',
                '(do (try (if true x null) (catch when (when 1 2))) (if true 3 null))',
            ],
            'macro output introduces bindings' => [
                '(defn f (when x y) (when x y))',
                '(def f (fn (when x y) (when x y)))',
            ],
            'quoted data' => [
                "(quote (fn (when x y) (when x y)))",
                "(quote (fn (when x y) (when x y)))",
            ],
            'quasiquoted data' => [
                '(quasiquote (when true 1))',
                '(quasiquote (when true 1))',
            ],
            'definition name' => [
                '(def when (unless false 1))',
                '(def when (if false null 1))',
            ],
        ];
    }

}
