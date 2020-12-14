<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

class LispFactory
{
    public function make(bool $safemode = false): Lisp
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $printer = new Printer();
        $eval = new Evaller($tokenizer, $reader, $printer, $safemode);

        // Root environment
        $env = new Env('root');

        // Register core functions
        (new Lib\Core($tokenizer, $reader, $printer, $eval, $safemode))->register($env);

        // Register core libraries
        (new Lib\Collections())->register($env);
        (new Lib\Compare())->register($env);
        if (extension_loaded('json')) {
            (new Lib\Json())->register($env);
        }
        (new Lib\Math())->register($env);
        if (extension_loaded('pcre')) {
            (new Lib\Regex())->register($env);
        }
        (new Lib\Strings())->register($env);
        (new Lib\Time())->register($env);
        (new Lib\Types())->register($env);

        // Register unsafe libraries if not in safe-mode
        if (!$safemode) {
            if (extension_loaded('PDO')) {
                (new Lib\Database())->register($env);
            }
            if (extension_loaded('curl')) {
                (new Lib\Http())->register($env);
            }
            (new Lib\IO())->register($env);
        }

        $lisp = new Lisp($tokenizer, $reader, $eval, $printer, $env);

        // Add some built-in macros
        $lisp->readEval('(def defn (macro (name args body) (quasiquote (def (unquote name) (fn (unquote args) (unquote body))))))');
        $lisp->readEval('(def defmacro (macro (name args body) (quasiquote (def (unquote name) (macro (unquote args) (unquote body))))))');

        // Separate environment for user-defined stuff
        $lisp->setEnv(new Env('user', $env));

        return $lisp;
    }
}
