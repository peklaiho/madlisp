<?php
namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Func;

class Time implements ILib
{
    public function register(Env $env): void
    {
        $env->set('time', new CoreFunc('time', 'Return the current time as unix timestamp.', 0, 0,
            fn () => time()
        ));

        $env->set('mtime', new CoreFunc('mtime', 'Return the current unix timestamp with microseconds as float.', 0, 0,
            fn () => microtime(true)
        ));

        $env->set('date', new CoreFunc('date', 'Format the time according to first argument.', 1, 2,
            fn (string $format, ?int $time = null) => date($format, $time !== null ? $time : time())
        ));

        $env->set('strtotime', new CoreFunc('strtotime', 'Parse datetime string into unix timestamp. Optional second argument can be used to give time for relative formats.', 1, 2,
            fn (string $format, ?int $time = null) => strtotime($format, $time !== null ? $time : time())
        ));
    }
}
