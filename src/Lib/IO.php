<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;

class IO implements ILib
{
    public function register(Env $env): void
    {
        $env->set('DIRSEP', \DIRECTORY_SEPARATOR);
        $env->set('HOME', $_SERVER['HOME'] . \DIRECTORY_SEPARATOR);

        $env->set('wd', new CoreFunc('wd', 'Get the current working directory.', 0, 0,
            fn () => getcwd() . \DIRECTORY_SEPARATOR
        ));

        $env->set('chdir', new CoreFunc('chdir', 'Change working directory.', 1, 1,
            fn (string $dir) => chdir($dir)
        ));

        $env->set('file?', new CoreFunc('file?', 'Check if file exists.', 1, 1,
            fn (string $filename) => file_exists($filename)
        ));

        $env->set('fget', new CoreFunc('fget', 'Read contents of a file.', 1, 1,
            function (string $filename) {
                return @file_get_contents($filename);
            }
        ));

        $env->set('fput', new CoreFunc('fput', 'Write string (second argument) to file (first argument). Give true as optional third argument to append instead of overwrite.', 2, 3,
            function (string $filename, $data, $append = false) {
                $flags = 0;
                if ($append) {
                    $flags = \FILE_APPEND;
                }

                $result = @file_put_contents($filename, $data, $flags);

                return $result !== false;
            }
        ));

        $env->set('fopen', new CoreFunc('fopen', 'Open a file for reading or writing. Give mode as second argument.', 2, 2,
            fn ($file, $mode) => @fopen($file, $mode)
        ));

        $env->set('fclose', new CoreFunc('fclose', 'Close a file resource.', 1, 1,
            fn ($handle) => @fclose($handle)
        ));

        $env->set('fread', new CoreFunc('fread', 'Read from a file resource. Give length in bytes as second argument.', 2, 2,
            fn ($handle, $length) => @fread($handle, $length)
        ));

        $env->set('fwrite', new CoreFunc('fwrite', 'Write to a file resource.', 2, 2,
            fn ($handle, $data) => @fwrite($handle, $data)
        ));

        $env->set('fflush', new CoreFunc('fflush', 'Persist buffered writes to disk for a file resource.', 1, 1,
            fn ($handle) => @fflush($handle)
        ));

        $env->set('feof?', new CoreFunc('feof?', 'Return true if end of file has been reached for a file resource.', 1, 1,
            fn ($handle) => @feof($handle)
        ));

        // Readline support
        if (extension_loaded('readline')) {
            $env->set('readline', new CoreFunc('readline', 'Read line of user input.', 0, 1,
                fn ($prompt = null) => readline($prompt)
            ));

            $env->set('readline-add', new CoreFunc('readline-add', 'Add new line of input to history.', 1, 1,
                fn (string $line) => readline_add_history($line)
            ));

            $env->set('readline-load', new CoreFunc('readline-load', 'Load the history for readline from a file.', 1, 1,
                fn (string $file) => readline_read_history($file)
            ));

            $env->set('readline-save', new CoreFunc('readline-save', 'Save the readline history into a file.', 1, 1,
                fn (string $file) => readline_write_history($file)
            ));
        }
    }
}
