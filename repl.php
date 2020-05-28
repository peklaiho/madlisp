<?php
require('bootstrap.php');

$env = ml_get_env();
$lisp = new MadLisp\Lisp();

while (true) {
    $input = readline('> ');

    try {
        $lisp->rep($input, $env);
    } catch (MadLispException $ex) {
        print('error: ' . $ex->getMessage());
    }

    print(PHP_EOL);
}
