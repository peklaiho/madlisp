<?php
require('bootstrap.php');

list($lisp, $env) = ml_get_lisp();

while (true) {
    $input = readline('> ');

    try {
        $lisp->rep($input, $env);
    } catch (MadLisp\MadLispException $ex) {
        print('error: ' . $ex->getMessage());
    }

    print(PHP_EOL);
}
