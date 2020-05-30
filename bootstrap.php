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

    $core = new MadLisp\Lib\Core();
    $core->register($env);

    $env->set('eval', function (...$args) use ($eval, $env) {
        $results = $eval->eval($args, $env);
        return $results[count($results) - 1];
    });

    return [$lisp, $env];
}
