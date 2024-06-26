<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\MadLispException;
use MadLisp\Seq;

class Math implements ILib
{
    public function register(Env $env): void
    {
        // Constants

        $env->set('PI', \M_PI);

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
                    $a = $args[0];
                    for ($i = 1; $i < count($args); $i++) {
                        $a -= $args[$i];
                    }
                    return $a;
                }
            }
        ));

        $env->set('*', new CoreFunc('*', 'Multiply the arguments.', 2, -1,
            function (...$args) {
                $a = $args[0];
                for ($i = 1; $i < count($args); $i++) {
                    $a *= $args[$i];
                }
                return $a;
            }
        ));

        $env->set('/', new CoreFunc('/', 'Divide the arguments.', 2, -1,
            function (...$args) {
                $a = $args[0];
                for ($i = 1; $i < count($args); $i++) {
                    $a /= $args[$i];
                }
                return $a;
            }
        ));

        $env->set('//', new CoreFunc('//', 'Divide the arguments using integer division.', 2, -1,
            function (...$args) {
                $a = $args[0];
                for ($i = 1; $i < count($args); $i++) {
                    $a = intdiv($a, $args[$i]);
                }
                return $a;
            }
        ));

        $env->set('%', new CoreFunc('%', 'Calculate the modulo of arguments.', 2, -1,
            function (...$args) {
                $a = $args[0];
                for ($i = 1; $i < count($args); $i++) {
                    $a %= $args[$i];
                }
                return $a;
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

        $env->set('max', new CoreFunc('max', 'Find the largest of arguments.', 1, -1,
            function (...$args) {
                if (count($args) == 1) {
                    $seq = $args[0];
                    if (!($seq instanceof Seq)) {
                        throw new MadLispException('single argument to max is not sequence');
                    }
                    return max($seq->getData());
                } else {
                    return max(...$args);
                }
            }
        ));

        $env->set('min', new CoreFunc('min', 'Find the smallest of arguments.', 1, -1,
            function (...$args) {
                if (count($args) == 1) {
                    $seq = $args[0];
                    if (!($seq instanceof Seq)) {
                        throw new MadLispException('single argument to min is not sequence');
                    }
                    return min($seq->getData());
                } else {
                    return min(...$args);
                }
            }
        ));

        $env->set('pow', new CoreFunc('pow', 'Return the first argument raised to the power of second argument.', 2, 2,
            fn ($a, $b) => pow($a, $b)
        ));

        $env->set('sqrt', new CoreFunc('sqrt', 'Return the square root of the arguemnt.', 1, 1,
            fn ($a) => sqrt($a)
        ));

        // Random number generator

        $env->set('rand', new CoreFunc('rand', 'Return a random integer between given min and max values.', 2, 2,
            fn ($min, $max) => mt_rand($min, $max)
        ));

        $env->set('randf', new CoreFunc('randf', 'Return a random float between given 0 (inclusive) and 1 (exclusive).', 0, 0,
            fn () => mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax()
        ));

        $env->set('rand-seed', new CoreFunc('rand-seed', 'Seed the random number generator with the given value.', 1, 1,
            function (int $a) {
                mt_srand($a);
                return $a;
            }
        ));

        $env->set('rand-bytes', new CoreFunc('rand-bytes', 'Generate the number of random bytes as specified by the argument.', 1, 1,
            fn (int $length) => random_bytes($length)
        ));
    }
}
