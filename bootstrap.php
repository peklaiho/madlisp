<?php
require('vendor/autoload.php');

function ml_get_env(): MadLisp\Env
{
    $env = new MadLisp\Env();

    $core = new MadLisp\Lib\Core();
    $core->register($env);

    return $env;
}

function ml_get_lisp(): MadLisp\Lisp
{
    $tokenizer = new MadLisp\Tokenizer();
    $reader = new MadLisp\Reader();
    $eval = new MadLisp\Evaller();
    $printer = new MadLisp\Printer();

    return new MadLisp\Lisp($tokenizer, $reader, $eval, $printer);
}
