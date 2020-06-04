<?php
namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\Func;
use MadLisp\MList;
use MadLisp\Seq;
use MadLisp\Symbol;
use MadLisp\Vector;

class Types implements ILib
{
    public function register(Env $env): void
    {
        // Conversions

        $env->set('bool', new CoreFunc('bool', 'Convert argument to boolean.', 1, 1,
            fn ($a) => boolval($a)
        ));

        $env->set('float', new CoreFunc('float', 'Convert argument to float.', 1, 1,
            fn ($a) => floatval($a)
        ));

        $env->set('int', new CoreFunc('int', 'Convert argument to integer.', 1, 1,
            fn ($a) => intval($a)
        ));

        $env->set('str', new CoreFunc('str', 'Convert arguments to string and concatenate them together.', 0, -1,
            fn (...$args) => implode('', array_map([$this, 'getStrValue'], $args))
        ));

        $env->set('symbol', new CoreFunc('symbol', 'Convert argument to symbol.', 1, 1,
            fn (string $a) => new Symbol($a)
        ));

        // Test types

        $env->set('type', new CoreFunc('type', 'Return the type of argument as a string.', 1, 1,
            function ($a) {
                if ($a instanceof Func) {
                    return 'function';
                } elseif ($a instanceof MList) {
                    return 'list';
                } elseif ($a instanceof Vector) {
                    return 'vector';
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
            }
        ));

        $env->set('fn?', new CoreFunc('fn?', 'Return true if argument is a function.', 1, 1,
            fn ($a) => $a instanceof Func
        ));

        $env->set('list?', new CoreFunc('list?', 'Return true if argument is a list.', 1, 1,
            fn ($a) => $a instanceof MList
        ));

        $env->set('vector?', new CoreFunc('vector?', 'Return true if argument is a vector.', 1, 1,
            fn ($a) => $a instanceof Vector
        ));

        $env->set('seq?', new CoreFunc('seq?', 'Return true if argument is a list or a vector.', 1, 1,
            fn ($a) => $a instanceof Seq
        ));

        $env->set('hash?', new CoreFunc('hash?', 'Return true if argument is a hash map.', 1, 1,
            fn ($a) => $a instanceof Hash
        ));

        $env->set('symbol?', new CoreFunc('symbol?', 'Return true if argument is a symbol.', 1, 1,
            fn ($a) => $a instanceof Symbol
        ));

        $env->set('bool?', new CoreFunc('bool?', 'Return true if argument is a boolean.', 1, 1,
            fn ($a) => $a === true || $a === false
        ));

        $env->set('true?', new CoreFunc('true?', 'Return true if argument evaluates to true.', 1, 1,
            fn ($a) => $a == true // not strict
        ));

        $env->set('false?', new CoreFunc('false?', 'Return true if argument evaluates to false.', 1, 1,
            fn ($a) => $a == false // not strict
        ));

        $env->set('null?', new CoreFunc('null?', 'Return true if argument is null.', 1, 1,
            fn ($a) => $a === null
        ));

        $env->set('int?', new CoreFunc('int?', 'Return true if argument is an integer.', 1, 1,
            fn ($a) => is_int($a)
        ));

        $env->set('float?', new CoreFunc('float?', 'Return true if argument is a float.', 1, 1,
            fn ($a) => is_float($a)
        ));

        $env->set('str?', new CoreFunc('str?', 'Return true if argument is a string.', 1, 1,
            fn ($a) => is_string($a)
        ));

        // Helpers for numbers

        $env->set('zero?', new CoreFunc('zero?', 'Return true if argument is an integer with value 0.', 1, 1,
            fn ($a) => $a === 0
        ));

        $env->set('one?', new CoreFunc('one?', 'Return true if argument is an integer with value 1.', 1, 1,
            fn ($a) => $a === 1
        ));

        $env->set('even?', new CoreFunc('even?', 'Return true if argument is divisible by 2.', 1, 1,
            fn ($a) => $a % 2 === 0
        ));

        $env->set('odd?', new CoreFunc('odd?', 'Return true if argument is not divisible by 2.', 1, 1,
            fn ($a) => $a % 2 !== 0
        ));
    }

    private function getStrValue($a)
    {
        if ($a instanceof Symbol) {
            return $a->getName();
        }

        return strval($a);
    }
}
