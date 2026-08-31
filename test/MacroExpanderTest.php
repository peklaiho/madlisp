<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\LispFactory;
use MadLisp\MacroExpander;

class MacroExpanderTest extends TestCase
{
    private function getLispAndExpander(): array
    {
        $lisp = (new LispFactory())->make();
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
}
