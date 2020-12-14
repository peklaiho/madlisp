<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

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

        $env->set('range', new CoreFunc('range', 'Return vector which contains values from 0 to first argument (exclusive). Two arguments can be used to give start and end values.', 1, 3,
            function (...$args) {
                if (count($args) == 1) {
                    $data = range(0, $args[0] - 1);
                } else {
                    $data = range($args[0], $args[1] - 1, $args[2] ?? 1);
                }

                return new Vector($data);
            }
        ));

        // Conversion

        $env->set('ltov', new CoreFunc('ltov', 'Convert list to vector.', 1, 1,
            fn (Seq $a) => new Vector($a->getData())
        ));

        $env->set('vtol', new CoreFunc('vtol', 'Convert vector to list.', 1, 1,
            fn (Seq $a) => new MList($a->getData())
        ));

        // Read information

        $env->set('empty?', new CoreFunc('empty?', 'Return true if collection is empty.', 1, 1,
            function ($a) {
                if ($a instanceof Collection) {
                    return $a->count() === 0;
                } elseif (is_string($a)) {
                    return $a === '';
                }

                throw new MadLispException('argument to empty? is not collection or string');
            }
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

                throw new MadLispException('argument to len is not collection or string');
            }
        ));

        // Get partial seq

        $env->set('first', new CoreFunc('first', 'Return the first element of a sequence or null.', 1, 1,
            fn (Seq $a) => $a->getData()[0] ?? null
        ));

        $env->set('second', new CoreFunc('second', 'Return the second element of a sequence or null.', 1, 1,
            fn (Seq $a) => $a->getData()[1] ?? null
        ));

        $env->set('last', new CoreFunc('last', 'Return the last element of a sequence or null.', 1, 1,
            function (Seq $a) {
                return $a->getData()[$a->count() - 1] ?? null;
            }
        ));

        $env->set('penult', new CoreFunc('penult', 'Return the second last element of a sequence or null.', 1, 1,
            function (Seq $a) {
                return $a->getData()[$a->count() - 2] ?? null;
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

        $env->set('slice', new CoreFunc('slice', 'Return a slice of the given sequence. Second argument is offset and third is length.', 2, 3,
            function (Seq $a, int $offset, ?int $length = null) {
                return $a::new(array_slice($a->getData(), $offset, $length));
            }
        ));

        // Manipulate seq

        $env->set('apply', new CoreFunc('apply', 'Apply the first argument (function) using second argument (sequence) as arguments.', 2, -1,
            function (...$args) {
                $fn = $args[0];
                $seq = $args[count($args) - 1];

                if (!($fn instanceof Func)) {
                    throw new MadLispException('first argument to apply is not function');
                } elseif (!($seq instanceof Seq)) {
                    throw new MadLispException('last argument to apply is not sequence');
                }

                $args2 = [];
                for ($i = 1; $i < count($args) - 1; $i++) {
                    $args2[] = $args[$i];
                }
                foreach ($seq->getData() as $a) {
                    $args2[] = $a;
                }

                return $fn->call($args2);
            }
        ));

        $env->set('chunk', new CoreFunc('chunk', 'Divide first argument (sequence) into new sequences with length of second argument (int).', 2, 2,
            function (Seq $a, int $len) {
                $chunks = array_chunk($a->getData(), $len);
                $data = [];
                foreach ($chunks as $c) {
                    $data[] = $a::new($c);
                }
                return $a::new($data);
            }
        ));

        $env->set('concat', new CoreFunc('concat', 'Concatenate multiple sequences together.', 1, -1,
            function (Seq ...$args) {
                // This is used by quasiquote, so we need to always return
                // a list for it to work properly.

                $data = [];
                foreach ($args as $a) {
                    $data[] = $a->getData();
                }

                return new MList(array_merge(...$data));
            }
        ));

        $env->set('push', new CoreFunc('push', 'Push the remaining arguments at the end of the sequence (first argument).', 2, -1,
            function (Seq $a, ...$b) {
                $data = $a->getData();
                foreach ($b as $c) {
                    $data[] = $c;
                }
                return $a::new($data);
            }
        ));

        $env->set('cons', new CoreFunc('cons', 'Insert the other arguments at the beginning of the sequence (last argument).', 2, -1,
            function (...$args) {
                // This is used by quasiquote.

                $seq = $args[count($args) - 1];
                if (!($seq instanceof Seq)) {
                    throw new MadLispException('last argument to cons is not sequence');
                }

                $data = [];
                for ($i = 0; $i < count($args) - 1; $i++) {
                    $data[] = $args[$i];
                }
                foreach ($seq->getData() as $val) {
                    $data[] = $val;
                }

                return $seq::new($data);
            }
        ));

        $env->set('map', new CoreFunc('map', 'Apply the first argument (function) to all elements of second argument (sequence).', 2, 2,
            function (Func $f, Seq $a) {
                return $a::new(array_map($f->getClosure(), $a->getData()));
            }
        ));

        $env->set('map2', new CoreFunc('map2', 'Apply the first argument (function) to each element from second and third argument (sequences).', 3, 3,
            function (Func $f, Seq $a, Seq $b) {
                if ($a->count() != $b->count()) {
                    throw new MadLispException('map2 requires equal number of elements in both sequences');
                }

                return $a::new(array_map($f->getClosure(), $a->getData(), $b->getData()));
            }
        ));

        $env->set('reduce', new CoreFunc('reduce', 'Apply the first argument (function) to each element of second argument (sequence) incrementally. Optional third argument is the initial value to be used as first input for function and it defaults to null.', 2, 3,
            function (Func $f, Seq $a, $initial = null) {
                return array_reduce($a->getData(), $f->getClosure(), $initial);
            }
        ));

        $env->set('filter', new CoreFunc('filter', 'Create new sequence which contains items that evaluate to true using first argument (function) from the second argument (sequence).', 2, 2,
            function (Func $f, Seq $a) {
                return $a::new(array_values(array_filter($a->getData(), $f->getClosure())));
            }
        ));

        $env->set('filterh', new CoreFunc('filterh', 'Same as filter but for hash maps. First argument passed to the callback is the value and second is the key.', 2, 2,
            function (Func $f, Hash $a) {
                return new Hash(array_filter($a->getData(), $f->getClosure(), ARRAY_FILTER_USE_BOTH));
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

        $env->set('unset', new CoreFunc('unset', 'Create a new hash-map from the first argument with the given key removed.', 2, 2,
            function (Hash $a, string $key) {
                $data = $a->getData();
                unset($data[$key]);
                return new Hash($data);
            }
        ));

        $env->set('unset!', new CoreFunc('unset!', 'Modify the hash-map (first argument) and remove the given key.', 2, 2,
            function (Hash $a, string $key) {
                return $a->unset($key);
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

        $env->set('usort', new CoreFunc('usort', 'Sort the sequence using custom comparison function.', 2, 2,
            function (Func $f, Seq $a) {
                $data = $a->getData();
                usort($data, $f->getClosure());
                return $a::new($data);
            }
        ));
    }
}
