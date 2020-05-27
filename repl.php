<?php

require_once('lib.php');
require_once('lisp.php');

$env = ml_get_env();

while (true) {
    $input = readline('> ');

    try {
        print(ml_rep($input, $env));
    } catch (MadLispException $ex) {
        print('error: ' . $ex->getMessage());
    }

    print(PHP_EOL);
}
