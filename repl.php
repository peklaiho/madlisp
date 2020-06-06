<?php
require('bootstrap.php');

list($lisp, $rootEnv) = ml_get_lisp();

// Read history
$historyFile = $_SERVER['HOME'] . '/.madlisp_history';
if (is_readable($historyFile)) {
    readline_read_history($historyFile);
}

// Create new env for user definitions
$userEnv = new MadLisp\Env('repl', $rootEnv);

while (true) {
    $input = readline('> ');

    try {
        $lisp->rep($input, $userEnv);

        if ($input) {
            readline_add_history($input);
            readline_write_history($historyFile);
        }
    } catch (MadLisp\MadLispException $ex) {
        print('error: ' . $ex->getMessage());
    } catch (TypeError $ex) {
        print('error: invalid argument type: ' . $ex->getMessage());
    }

    print(PHP_EOL);
}
