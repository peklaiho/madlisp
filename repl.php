<?php
require('bootstrap.php');

list($lisp, $rootEnv) = ml_get_lisp();

// Create new env for user definitions
$userEnv = new MadLisp\Env('repl', $rootEnv);

while (true) {
    $input = readline('> ');

    try {
        $lisp->rep($input, $userEnv);
    } catch (MadLisp\MadLispException $ex) {
        print('error: ' . $ex->getMessage());
    } catch (TypeError $ex) {
        print('error: invalid argument type: ' . $ex->getMessage());
    }

    print(PHP_EOL);
}
