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

        $env->set('to-bool', new CoreFunc('to-bool', 'Convert argument to boolean.', 1, 1,
            fn ($a) => boolval($a)
        ));

        $env->set('to-float', new CoreFunc('to-float', 'Convert argument to float.', 1, 1,
            fn ($a) => floatval($a)
        ));

        $env->set('to-int', new CoreFunc('to-int', 'Convert argument to integer.', 1, 1,
            fn ($a) => intval($a)
        ));

        $env->set('to-str', new CoreFunc('fn?', 'Convert argument to string.', 1, 1,
            fn ($a) => strval($a)
        ));

        // Test types

        $env->set('type?', new CoreFunc('type?', 'Return the type of argument as a string.', 1, 1,
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
    }
}
