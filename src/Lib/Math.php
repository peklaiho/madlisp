<?php
namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;

class Math implements ILib
{
    public function register(Env $env): void
    {
        // Constants

        $env->set('pi', \M_PI);

        // Basic arithmetic

        $env->set('+', new CoreFunc('+', 'Return the sum of all arguments.', 1, -1,
            function (...$args) {
                return array_sum($args);
            }
        ));

        $env->set('-', new CoreFunc('-', 'Subtract the other arguments from the first.', 1, -1,
            function (...$args) {
                if (count($args) == 1) {
                    return -$args[0];
                } else {
                    return array_reduce(array_slice($args, 1), fn ($a, $b) => $a - $b, $args[0]);
                }
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

        // Helpers for change by 1

        $env->set('inc', new CoreFunc('inc', 'Increase argument by one.', 1, 1,
            fn ($a) => $a + 1
        ));

        $env->set('dec', new CoreFunc('dec', 'Decrease argument by one.', 1, 1,
            fn ($a) => $a - 1
        ));

        // Trigonometry

        $env->set('sin', new CoreFunc('sin', 'Calculate the sine of argument.', 1, 1,
            fn ($a) => sin($a)
        ));

        $env->set('cos', new CoreFunc('cos', 'Calculate the cosine of argument.', 1, 1,
            fn ($a) => cos($a)
        ));

        $env->set('tan', new CoreFunc('tan', 'Calculate the tangent of argument.', 1, 1,
            fn ($a) => tan($a)
        ));

        // Other

        $env->set('abs', new CoreFunc('abs', 'Return the absolute value of argument.', 1, 1,
            fn ($a) => abs($a)
        ));

        $env->set('floor', new CoreFunc('floor', 'Return the next lowest integer by rounding argument down.', 1, 1,
            fn ($a) => intval(floor($a))
        ));

        $env->set('ceil', new CoreFunc('ceil', 'Return the next highest integer by rounding argument up.', 1, 1,
            fn ($a) => intval(ceil($a))
        ));

        $env->set('pow', new CoreFunc('pow', 'Return the first argument raised to the power of second argument.', 2, 2,
            fn ($a, $b) => pow($a, $b)
        ));

        $env->set('sqrt', new CoreFunc('sqrt', 'Return the square root of the arguemnt.', 1, 1,
            fn ($a) => sqrt($a)
        ));
    }
}
