<?php
require('vendor/autoload.php');

function ml_get_lisp(): array
{
    $tokenizer = new MadLisp\Tokenizer();
    $reader = new MadLisp\Reader();
    $eval = new MadLisp\Evaller();
    $printer = new MadLisp\Printer();

    $lisp = new MadLisp\Lisp($tokenizer, $reader, $eval, $printer);

    // environment

    $env = new MadLisp\Env();

    (new MadLisp\Lib\Math())->register($env);
    (new MadLisp\Lib\Compare())->register($env);
    (new MadLisp\Lib\Types())->register($env);

    /*
    $env->set('eval', function (...$args) use ($eval, $env) {
        $results = $eval->eval($args, $env);
        return $results[count($results) - 1];
    });

    $env->set('print', function (...$args) use ($printer) {
        $printer->print($args);
        return null;
    });
    */

    return [$lisp, $env];
}
