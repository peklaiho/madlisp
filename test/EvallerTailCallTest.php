<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Compiler;
use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Tokenizer;
use MadLisp\Lib\Collections;
use MadLisp\Lib\Compare;
use MadLisp\Lib\Core;
use MadLisp\Lib\Math;
use MadLisp\Lib\Strings;
use MadLisp\Lib\Types;

class EvallerTailCallTest extends TestCase
{
    public function testDirectUserFunctionTailCall()
    {
        // Tests the evaluator's user-function loop by making a direct recursive
        // call the final expression of the function body.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (loop (dec n)))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testAndTailCall()
    {
        // Tests the final operand of and, where the recursive call is assigned
        // to $ast and continued in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (and true (loop (dec n))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testCaseTailCall()
    {
        // Tests the selected case clause by placing the recursive call in its
        // final expression, which uses the case tail-call continuation.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (case n
                            (0 0)
                            (else (loop (dec n))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testCaseStrictTailCall()
    {
        // Tests the strict case branch with recursion in the selected clause's
        // final expression and therefore its tail-call continuation.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (case-strict n
                            (0 0)
                            (else (loop (dec n))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testCondTailCall()
    {
        // Tests a selected cond clause whose final expression is recursive,
        // exercising cond's tail-position jump back to the evaluator loop.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (cond
                            ((= n 0) 0)
                            (else (loop (dec n))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testDoTailCall()
    {
        // Tests do's final body expression after an earlier expression runs;
        // the recursive final expression is evaluated in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (do false (loop (dec n))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testEvalTailCall()
    {
        // Tests eval replacing the current AST with the evaluated form, which
        // is then continued without adding an evaluator stack frame.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (eval (quote (loop (dec n)))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testIfElseBranchTailCall()
    {
        // Tests the false condition path, where the recursive call is the else branch
        // and is continued in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (loop (dec n)))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testIfThenBranchTailCall()
    {
        // Tests the true condition path, where the recursive call is the then branch
        // and is continued in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (> n 0)
                            (loop (dec n))
                            0)))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testLetTailCall()
    {
        // Tests let's final body expression after switching to the child
        // environment, with the recursive call in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (let (next (dec n))
                                (loop next)))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testOrTailCall()
    {
        // Tests the final operand of or, where the recursive call is assigned
        // to $ast and continued in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (or false (loop (dec n))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    public function testQuasiquoteTailCall()
    {
        // Tests quasiquote producing a form that becomes the next AST; eval
        // then continues that generated recursive call in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (eval (quasiquote
                                (loop (unquote (dec n))))))))
                (loop 1000))
        ');

        $this->assertSame(0, $result);
    }

    public function testTryCatchTailCall()
    {
        // Tests the catch path creating a catch environment and continuing
        // with its recursive final expression in tail position.

        $result = $this->evalCode('
            (do
                (def loop
                    (fn (n)
                        (if (= n 0)
                            0
                            (try
                                (throw (dec n))
                                (catch error
                                    (if (= error 0)
                                        0
                                        (loop error)))))))
                (loop 10000))
        ');

        $this->assertSame(0, $result);
    }

    private function evalCode(string $code, bool $safemode = false)
    {
        [$env, $evaller] = $this->getEnvAndEvaller($safemode);
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $ast = $reader->read($tokenizer->tokenize($code));
        return $evaller->eval($ast, $env);
    }

    private function getEnvAndEvaller(bool $safemode = false): array
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $compiler = new Compiler();
        $printer = new Printer();

        $evaller = new Evaller(
            $tokenizer,
            $reader,
            $printer,
            $safemode
        );

        $env = new Env('root');

        $env->set('__FILE__', null);
        $env->set('__DIR__', null);

        // Define some functions for testing
        $lib = new Core(
            $tokenizer,
            $reader,
            $compiler,
            $printer,
            $evaller,
            $safemode
        );
        $lib->register($env);
        $lib = new Collections();
        $lib->register($env);
        $lib = new Compare();
        $lib->register($env);
        $lib = new Math();
        $lib->register($env);
        $lib = new Strings();
        $lib->register($env);
        $lib = new Types();
        $lib->register($env);

        return [$env, $evaller];
    }
}
