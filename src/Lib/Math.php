<?php
namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;

class Math implements ILib
{
    public function register(Env $env): void
    {
        // Basic arithmetic

        $env->set('+', new CoreFunc('+', 'Return the sum of all arguments.', 2, -1,
            function (...$args) {
                return array_sum($args);
            }
        ));

        $env->set('-', new CoreFunc('-', 'Subtract the other arguments from the first.', 2, -1,
            function (...$args) {
                return array_reduce(array_slice($args, 1), fn ($a, $b) => $a - $b, $args[0]);
            }
        ));

        $env->set('*', new CoreFunc('*', 'Multiply the arguments.', 2, -1,
            function (...$args) {
                return array_reduce(array_slice($args, 1), fn ($a, $b) => $a * $b, $args[0]);
            }
        ));

        $env->set('/', new CoreFunc('/', 'Divide the arguments.', 2, -1,
            function (...$args) {
                return array_reduce(array_slice($args, 1), fn ($a, $b) => $a / $b, $args[0]);
            }
        ));

        $env->set('//', new CoreFunc('//', 'Divide the arguments using integer division.', 2, -1,
            function (...$args) {
                return array_reduce(array_slice($args, 1), fn ($a, $b) => intdiv($a, $b), $args[0]);
            }
        ));

        $env->set('%', new CoreFunc('%', 'Calculate the modulo of arguments.', 2, -1,
            function (...$args) {
                return array_reduce(array_slice($args, 1), fn ($a, $b) => $a % $b, $args[0]);
            }
        ));

        // TODO: add pow, sqrt, floor, ceil, abs
    }
}
