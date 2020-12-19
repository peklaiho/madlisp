<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

class Util
{
    public static function bindArguments(Env $env, array $bindings, array $args): void
    {
        for ($i = 0; $i < count($bindings); $i++) {
            if ($bindings[$i]->getName() == '&') {
                if ($i < count($bindings) - 1) {
                    $env->set($bindings[$i + 1]->getName(), new Vector(array_slice($args, $i)));
                    return;
                } else {
                    throw new MadLispException('no binding after &');
                }
            } else {
                $env->set($bindings[$i]->getName(), $args[$i] ?? null);
            }
        }
    }

    public static function makeHash(array $args): Hash
    {
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
    }

    public static function valueForCompare($a)
    {
        if ($a instanceof Symbol) {
            return $a->getName();
        } elseif ($a instanceof Collection) {
            return $a->getData();
        }

        return $a;
    }
}
