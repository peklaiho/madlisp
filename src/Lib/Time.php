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

        $env->set('date', new CoreFunc('date', 'Format the time according to first argument.', 1, 2,
            fn (string $format, ?int $time = null) => date($format, $time !== null ? $time : time())
        ));

        $env->set('strtotime', new CoreFunc('strtotime', 'Parse datetime string into unix timestamp. Optional second argument can be used to give time for relative formats.', 1, 2,
            fn (string $format, ?int $time = null) => strtotime($format, $time !== null ? $time : time())
        ));

        $env->set('sleep', new CoreFunc('sleep', 'Sleep (wait) for the specified time in milliseconds.', 1, 1,
            function (int $time) {
                usleep($time * 1000);
                return null;
            }
        ));

        $env->set('timer', new CoreFunc('timer', 'Measure the execution time of a function and return it in seconds.', 1, -1,
            function (Func $f, ...$args) {
                $start = microtime(true);
                $f->call($args);
                $end = microtime(true);
                return $end - $start;
            }
        ));
    }
}
