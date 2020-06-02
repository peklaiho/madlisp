<?php
namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\MList;

class Strings implements ILib
{
    public function register(Env $env): void
    {
        $env->set('trim', new CoreFunc('trim', 'Remove whitespace from beginning and end of string.', 1, 1,
            fn (string $a) => trim($a)
        ));

        $env->set('upcase', new CoreFunc('upcase', 'Return string in upper case.', 1, 1,
            fn (string $a) => strtoupper($a)
        ));

        $env->set('locase', new CoreFunc('locase', 'Return string in lower case.', 1, 1,
            fn (string $a) => strtolower($a)
        ));

        $env->set('substr', new CoreFunc('substr', 'Return substring starting from index as second argument and length as optional third argument.', 2, 3,
            function (string $a, int $i, ?int $l = null) {
                if ($l === null) {
                    return substr($a, $i);
                } else {
                    return substr($a, $i, $l);
                }
            }
        ));

        $env->set('split', new CoreFunc('split', 'Split the second argument by the first argument into a list.', 2, 2,
            fn (string $a, string $b) => new MList(explode($a, $b))
        ));

        $env->set('join', new CoreFunc('join', 'Join the remaining arguments together by using the first argument as glue.', 1, -1,
            fn (string $a, ...$b) => implode($a, $b)
        ));

        $env->set('format', new CoreFunc('format', 'Format the remaining arguments as string specified by the first argument.', 1, -1,
            fn (string $a, ...$b) => sprintf($a, ...$b)
        ));
    }
}
