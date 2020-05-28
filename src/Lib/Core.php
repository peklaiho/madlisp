<?php
namespace MadLisp\Lib;

use Closure;
use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\MList;
use MadLisp\Symbol;
use MadLisp\MadLispException;

class Core implements ILib
{
    public function register(Env $env): void
    {
        // logic

        $env->set('or', function (...$args) {
            // return first true
            for ($i = 0; $i < count($args) - 1; $i++) {
                if ($args[$i] == true) {
                    return $args[$i];
                }
            }

            // return last
            return $args[count($args) - 1];
        });

        $env->set('and', function (...$args) {
            // return first false
            for ($i = 0; $i < count($args) - 1; $i++) {
                if ($args[$i] == false) {
                    return $args[$i];
                }
            }

            // return last
            return $args[count($args) - 1];
        });

        $env->set('not', fn ($a) => !$a);

        // arithmetic

        $env->set('+', function (...$args) {
            return array_sum($args);
        });

        $env->set('-', function (...$args) {
            $result = $args[0] ?? null;
            for ($i = 1; $i < count($args); $i++) {
                $result -= $args[$i];
            }
            return $result;
        });

        $env->set('*', function (...$args) {
            $result = $args[0] ?? null;
            for ($i = 1; $i < count($args); $i++) {
                $result *= $args[$i];
            }
            return $result;
        });

        $env->set('/', function (...$args) {
            $result = $args[0] ?? null;
            for ($i = 1; $i < count($args); $i++) {
                $result /= $args[$i];
            }
            return $result;
        });

        $env->set('%', function (...$args) {
            $result = $args[0] ?? null;
            for ($i = 1; $i < count($args); $i++) {
                $result %= $args[$i];
            }
            return $result;
        });

        // comparison

        $env->set('=', fn ($a, $b) => $a == $b);
        $env->set('<', fn ($a, $b) => $a < $b);
        $env->set('>', fn ($a, $b) => $a > $b);
        $env->set('<=', fn ($a, $b) => $a <= $b);
        $env->set('>=', fn ($a, $b) => $a >= $b);
        $env->set('!=', fn ($a, $b) => $a != $b);

        // types

        $env->set('type?', function ($a) {
            if ($a instanceof Closure) {
                return 'function';
            } elseif ($a instanceof MList) {
                return 'list';
            } elseif ($a instanceof Hash) {
                return 'hash';
            } elseif ($a instanceof Symbol) {
                return 'symbol';
            } elseif ($a === true || $a === false) {
                return 'bool';
            } elseif ($a === null) {
                return 'null';
            } elseif (is_int($a)) {
                return 'int';
            } elseif (is_float($a)) {
                return 'float';
            } else {
                return 'string';
            }
        });

        $env->set('fn?', fn ($a) => $a instanceof Closure);
        $env->set('list?', fn ($a) => $a instanceof MList);
        $env->set('hash?', fn ($a) => $a instanceof Hash);
        $env->set('sym?', fn ($a) => $a instanceof Symbol);
        $env->set('bool?', fn ($a) => $a === true || $a === false);
        $env->set('true?', fn ($a) => $a == true); // not strict
        $env->set('false?', fn ($a) => $a == false); // not strict
        $env->set('null?', fn ($a) => $a === null);
        $env->set('int?', fn ($a) => is_int($a));
        $env->set('float?', fn ($a) => is_float($a));
        $env->set('str?', fn ($a) => is_string($a));

        // collections

        $env->set('list', function (...$args) {
            return new MList($args);
        });

        $env->set('hash', function (...$args) {
            if (count($args) % 2 == 1) {
                throw new MadLispException('uneven number of arguments for hash');
            }

            $data = [];

            for ($i = 0; $i < count($args) - 1; $i += 2) {
                $key = $args[$i];
                $val = $args[$i + 1];

                if (!is_string($key)) {
                    throw new MadLispException('invalid key for hash (not string)');
                }

                $data[$key] = $val;
            }

            return new Hash($data);
        });
    }
}
