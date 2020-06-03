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

        // Read information

        $env->set('empty?', new CoreFunc('empty?', 'Return true if collection is empty.', 1, 1,
            fn (Collection $a) => $a->count() == 0
        ));

        $env->set('get', new CoreFunc('get', 'Get the item from first argument (collection) by using the second argument as index or key.', 2, 2,
            fn (Collection $a, $b) => $a->get($b)
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

        // Get partial list

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

        // Manipulate list

        $env->set('push', new CoreFunc('push', 'Push the remaining arguments at the end of the sequence (first argument).', 2, -1,
            function (Seq $a, ...$b) {
                $data = $a->getData();
                foreach ($b as $c) {
                    $data[] = $c;
                }
                return $a::new($data);
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

        $env->set('reverse', new CoreFunc('reverse', 'Create new sequence with reversed order.', 1, 1,
            fn (Seq $a) => $a::new(array_reverse($a->getData()))
        ));

        // Hash map functions

        $env->set('key?', new CoreFunc('key?', 'Return true if first argument (hash-map) contains the second argument as key.', 2, 2,
            fn (Hash $a, string $b) => $a->has($b)
        ));

        $env->set('set', new CoreFunc('set', 'Create new hash-map from first argument and set key (second argument) to value given by third argument.', 3, 3,
            function (Hash $a, string $key, $val) {
                // Immutable version
                $hash = new Hash($a->getData());
                $hash->set($key, $val);
                return $hash;
            }
        ));

        $env->set('set!', new CoreFunc('set!', 'Modify the hash-map (first argument) and set key (second argument) to value given by third argument and return the value.', 3, 3,
            function (Hash $a, string $key, $val) {
                // Mutable version
                return $a->set($key, $val);
            }
        ));

        $env->set('keys', new CoreFunc('keys', 'Return the keys of a hash-map as a list.', 1, 1,
            fn (Hash $a) => new MList(array_keys($a->getData()))
        ));

        $env->set('values', new CoreFunc('values', 'Return the values of a hash-map as a list.', 1, 1,
            fn (Hash $a) => new MList(array_values($a->getData()))
        ));

        $env->set('zip', new CoreFunc('zip', 'Create new hash-map using first argument as keys and second argument as values.', 2, 2,
            function (Seq $keys, Seq $vals) {
                if ($keys->count() != $vals->count()) {
                    throw new MadLispException('zip requires equal number of keys and values');
                }

                return new Hash(array_combine($keys->getData(), $vals->getData()));
            }
        ));

        // Sorting

        $env->set('sort', new CoreFunc('sort', 'Sort the sequence in ascending order.', 1, 1,
            function (Seq $a) {
                $data = $a->getData();
                sort($data);
                return $a::new($data);
            }
        ));
    }
}
