<?php
namespace MadLisp\Lib;

use MadLisp\Env;
use MadLisp\Func;
use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\Symbol;
use MadLisp\Util;

class Core implements ILib
{
    public function register(Env $env): void
    {
        // arithmetic

        // comparison

        $env->set('=', fn ($a, $b) => $a == $b);
        $env->set('<', fn ($a, $b) => $a < $b);
        $env->set('>', fn ($a, $b) => $a > $b);
        $env->set('<=', fn ($a, $b) => $a <= $b);
        $env->set('>=', fn ($a, $b) => $a >= $b);
        $env->set('!=', fn ($a, $b) => $a != $b);

        // types

        $env->set('type?', function ($a) {
            if ($a instanceof Func) {
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

        $env->set('fn?', fn ($a) => $a instanceof Func);
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

    }
}
