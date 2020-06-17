<?php
require(__DIR__ . '/vendor/autoload.php');

if (php_sapi_name() != 'cli') {
    exit('Currently only cli usage is supported.');
}

function ml_repl($lisp)
{
    // Read history
    $historyFile = $_SERVER['HOME'] . '/.madlisp_history';
    if (is_readable($historyFile)) {
        readline_read_history($historyFile);
    }

    while (true) {
        $input = readline('> ');

        try {
            $lisp->rep($input, true);

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

// Create the Lisp interpreter
$factory = new MadLisp\LispFactory();
$lisp = $factory->make();

if ($argc < 2) {
    // Read input from stdin
    $input = file_get_contents('php://stdin');
    $lisp->rep($input, false);
} elseif ($argv[1] == '-r') {
    // Run the repl
    ml_repl($lisp);
} elseif ($argv[1] == '-e') {
    // Evaluate next argument
    $lisp->rep($argv[2] ?? '', false);
} elseif ($argv[1] == '-h') {
    // Show help
    print("Usage:" . PHP_EOL);
    print("-e <code>       :: Evaluate code" . PHP_EOL);
    print("-h              :: Show this help" . PHP_EOL);
    print("-r              :: Run the interactive Repl" . PHP_EOL);
    print("<filename>      :: Evaluate file" . PHP_EOL);
    print("<no arguments>  :: Read from stdin" . PHP_EOL);
} else {
    // Read file
    $file = $argv[1];
    if (is_readable($file)) {
        $input = "(load \"$file\")";
        $lisp->rep($input, false);
    } else {
        print("Unable to read file: $file\n");
        exit(1); // exit with error code
    }
}
