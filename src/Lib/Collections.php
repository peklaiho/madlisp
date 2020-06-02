<?php
namespace MadLisp\Lib;

use MadLisp\Collection;
use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Func;
use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\Seq;
use MadLisp\Util;
use MadLisp\Vector;

class Collections implements ILib
{
    public function register(Env $env): void
    {
        // Creation

        $env->set('hash', new CoreFunc('hash', 'Return hash which contains the arguments.', 0, -1,
            fn (...$args) => Util::makeHash($args)
        ));

        $env->set('list', new CoreFunc('list', 'Return list which contains the arguments.', 0, -1,
            fn (...$args) => new MList($args)
        ));

        $env->set('vector', new CoreFunc('vector', 'Return vector which contains the arguments.', 0, -1,
            fn (...$args) => new Vector($args)
        ));

        // Other

        $env->set('empty?', new CoreFunc('empty?', 'Return true if collection is empty.', 1, 1,
            fn (Collection $a) => $a->count() == 0
        ));

        $env->set('len', new CoreFunc('len', 'Return the length of string or number of elements in collection.', 1, 1,
            function ($a) {
                if ($a instanceof Collection) {
                    return $a->count();
                } elseif (is_string($a)) {
                    return strlen($a);
                }

                throw new MadLispException('len required string or collection as argument');
            }
        ));

        $env->set('first', new CoreFunc('first', 'Return the first element of a sequence or null.', 1, 1,
            fn (Seq $a) => $a->getData()[0] ?? null
        ));

        $env->set('last', new CoreFunc('last', 'Return the last element of a sequence or null.', 1, 1,
            function (Seq $a) {
                if ($a->count() == 0) {
                    return null;
                }

                return $a->getData()[$a->count() - 1];
            }
        ));

        $env->set('head', new CoreFunc('head', 'Return new sequence containing all elements of argument except last.', 1, 1,
            function (Seq $a) {
                return $a::new(array_slice($a->getData(), 0, $a->count() - 1));
            }
        ));

        $env->set('tail', new CoreFunc('tail', 'Return new sequence containing all elements of argument except first.', 1, 1,
            function (Seq $a) {
                return $a::new(array_slice($a->getData(), 1));
            }
        ));

        $env->set('map', new CoreFunc('map', 'Apply the first argument (function) to all elements of second argument (sequence).', 2, 2,
            function (Func $f, Seq $a) {
                return $a::new(array_map($f->getClosure(), $a->getData()));
            }
        ));

        $env->set('reduce', new CoreFunc('reduce', 'Apply the first argument (function) to each element of second argument (sequence) incrementally. Optional third argument is the initial value to be used as first input for function and it defaults to null.', 2, 3,
            function (Func $f, Seq $a, $initial = null) {
                return array_reduce($a->getData(), $f->getClosure(), $initial);
            }
        ));

        $env->set('keys', new CoreFunc('keys', 'Return the keys of a hash-map as a list.', 1, 1,
            fn (Hash $a) => new MList(array_keys($a->getData()))
        ));

        $env->set('values', new CoreFunc('values', 'Return the values of a hash-map as a list.', 1, 1,
            fn (Hash $a) => new MList(array_values($a->getData()))
        ));
    }
}
