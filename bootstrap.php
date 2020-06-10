<?php
require('vendor/autoload.php');

function ml_get_lisp(bool $debug): array
{
    $tokenizer = new MadLisp\Tokenizer();
    $reader = new MadLisp\Reader();
    $printer = new MadLisp\Printer();
    $eval = new MadLisp\Evaller($tokenizer, $reader, $printer);
    $eval->setDebug($debug);

    $lisp = new MadLisp\Lisp($tokenizer, $reader, $eval, $printer);

    // Root environment
    $env = new MadLisp\Env('root');

    // Register core functions
    $lisp->register($env);

    // Register libraries
    (new MadLisp\Lib\Collections())->register($env);
    (new MadLisp\Lib\Compare())->register($env);
    (new MadLisp\Lib\IO())->register($env);
    (new MadLisp\Lib\Math())->register($env);
    (new MadLisp\Lib\Strings())->register($env);
    (new MadLisp\Lib\Time())->register($env);
    (new MadLisp\Lib\Types())->register($env);

    // User environment
    $env = new MadLisp\Env('user', $env);

    return [$lisp, $env];
}
