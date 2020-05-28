<?php
require('bootstrap.php');

$env = ml_get_env();
$lisp = ml_get_lisp();

while (true) {
    $input = readline('> ');

    try {
        $lisp->rep($input, $env);
    } catch (MadLisp\MadLispException $ex) {
        print('error: ' . $ex->getMessage());
    }

    print(PHP_EOL);
}
