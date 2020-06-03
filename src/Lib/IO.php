<?php
namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;

class IO implements ILib
{
    public function register(Env $env): void
    {
        $env->set('file?', new CoreFunc('file?', 'Check if file exists.', 1, 1,
            fn (string $filename) => file_exists($filename)
        ));

        $env->set('fread', new CoreFunc('fread', 'Read contents of a file.', 1, 1,
            function (string $filename) {
                return @file_get_contents($filename);
            }
        ));

        $env->set('fwrite', new CoreFunc('fwrite', 'Write string (second argument) to file (first argument). Give true as optional third argument to append instead of overwrite.', 2, 3,
            function (string $filename, $data, $append = false) {
                $flags = 0;
                if ($append) {
                    $flags = \FILE_APPEND;
                }

                $result = @file_put_contents($filename, $data, $flags);

                return $result !== false;
            }
        ));
    }
}
