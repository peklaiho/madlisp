<?php
require('bootstrap.php');

if (php_sapi_name() != 'cli') {
    exit('Currently only cli usage is supported.');
}

function ml_repl($lisp, $env)
{
    // Read history
    $historyFile = $_SERVER['HOME'] . '/.madlisp_history';
    if (is_readable($historyFile)) {
        readline_read_history($historyFile);
    }

    while (true) {
        $input = readline('> ');

        try {
            $lisp->rep($input, $env);

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
}

function ml_run()
{
    $args = getopt('de:f:r');

    $debug = array_key_exists('d', $args);

    list($lisp, $env) = ml_get_lisp($debug);

    if (array_key_exists('e', $args)) {
        $lisp->rep($args['e'], $env);
    } elseif (array_key_exists('f', $args)) {
        $input = "(load \"{$args['f']}\")";
        $lisp->rep($input, $env);
    } elseif (array_key_exists('r', $args)) {
        ml_repl($lisp, $env);
    } else {
        print("Usage:" . PHP_EOL);
        print("-d         :: Debug mode" . PHP_EOL);
        print("-e <code>  :: Evaluate code" . PHP_EOL);
        print("-f <file>  :: Evaluate file" . PHP_EOL);
        print("-r         :: Run the interactive repl" . PHP_EOL);
    }
}

ml_run();
