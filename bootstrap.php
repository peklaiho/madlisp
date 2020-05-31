<?php
require('vendor/autoload.php');

function ml_get_lisp(): array
{
    $tokenizer = new MadLisp\Tokenizer();
    $reader = new MadLisp\Reader();
    $eval = new MadLisp\Evaller();
    $printer = new MadLisp\Printer();

    $lisp = new MadLisp\Lisp($tokenizer, $reader, $eval, $printer);

    // Environment
    $env = new MadLisp\Env();

    // Register core functions
    $lisp->register($env);

    // Register libraries
    (new MadLisp\Lib\Math())->register($env);
    (new MadLisp\Lib\Compare())->register($env);
    (new MadLisp\Lib\Types())->register($env);

    return [$lisp, $env];
}
