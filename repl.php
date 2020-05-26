<?php

require('lisp.php');

while (true) {
    $input = readline('> ');

    print('% ');

    try {
        ml_rep($input);
    } catch (MadLispException $ex) {
        print('error: ' . $ex->getMessage());
    }

    print(PHP_EOL);
}
