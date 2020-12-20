<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Vector;

class Strings implements ILib
{
    public function register(Env $env): void
    {
        $env->set('EOL', \PHP_EOL);

        $env->set('trim', new CoreFunc('trim', 'Remove whitespace from beginning and end of string. Give characters to remove in optional second argument.', 1, 2,
            function (string $str, ?string $chars = null) {
                if (func_num_args() == 1) {
                    return trim($str);
                } else {
                    return trim($str, $chars);
                }
            }
        ));

        $env->set('ltrim', new CoreFunc('ltrim', 'Remove whitespace from beginning of string. Give characters to remove in optional second argument.', 1, 2,
            function (string $str, ?string $chars = null) {
                if (func_num_args() == 1) {
                    return ltrim($str);
                } else {
                    return ltrim($str, $chars);
                }
            }
        ));

        $env->set('rtrim', new CoreFunc('rtrim', 'Remove whitespace from end of string. Give characters to remove in optional second argument.', 1, 2,
            function (string $str, ?string $chars = null) {
                if (func_num_args() == 1) {
                    return rtrim($str);
                } else {
                    return rtrim($str, $chars);
                }
            }
        ));

        $env->set('upcase', new CoreFunc('upcase', 'Return string in upper case.', 1, 1,
            fn (string $a) => strtoupper($a)
        ));

        $env->set('lowcase', new CoreFunc('lowcase', 'Return string in lower case.', 1, 1,
            fn (string $a) => strtolower($a)
        ));

        $env->set('strpos', new CoreFunc('strpos', 'Find second argument from the first argument and return the index, or false if not found. Offset can be given as optional third argument.', 2, 3,
            function (string $haystack, string $needle, int $offset = 0) {
                if (func_num_args() == 2) {
                    return strpos($haystack, $needle);
                } else {
                    return strpos($haystack, $needle, $offset);
                }
            }
        ));

        $env->set('stripos', new CoreFunc('stripos', 'Case-insensitive version of strpos.', 2, 3,
            function (string $haystack, string $needle, int $offset = 0) {
                if (func_num_args() == 2) {
                    return stripos($haystack, $needle);
                } else {
                    return stripos($haystack, $needle, $offset);
                }
            }
        ));

        $env->set('substr', new CoreFunc('substr', 'Return substring starting from index as second argument and length as optional third argument.', 2, 3,
            function (string $str, int $idx, ?int $len = null) {
                if (func_num_args() == 2) {
                    return substr($str, $idx);
                } else {
                    return substr($str, $idx, $len);
                }
            }
        ));

        $env->set('replace', new CoreFunc('replace', 'Change occurrences in first argument from second argument to third argument.', 3, 3,
            fn (string $a, string $b, string $c) => str_replace($b, $c, $a)
        ));

        $env->set('split', new CoreFunc('split', 'Split the second argument by the first argument into a vector.', 2, 2,
            fn (string $a, string $b) => new Vector(explode($a, $b))
        ));

        $env->set('join', new CoreFunc('join', 'Join the remaining arguments together by using the first argument as glue.', 1, -1,
            fn (string $a, ...$b) => implode($a, $b)
        ));

        $env->set('format', new CoreFunc('format', 'Format the remaining arguments as string specified by the first argument.', 1, -1,
            fn (string $a, ...$b) => sprintf($a, ...$b)
        ));

        $env->set('prefix?', new CoreFunc('prefix?', 'Return true if the first argument starts with the second argument.', 2, 2,
            fn (string $str, string $start) => substr($str, 0, strlen($start)) === $start
        ));

        $env->set('suffix?', new CoreFunc('suffix?', 'Return true if the first argument ends with the second argument.', 2, 2,
            fn (string $str, string $end) => substr($str, strlen($str) - strlen($end)) === $end
        ));

        // If the mbstring extension is loaded,
        // define multibyte versions of some functions.

        if (extension_loaded('mbstring')) {
            $env->set('mb-len', new CoreFunc('mb-len', 'Count the number of characters in a multibyte string.', 1, 1,
                fn (string $a) => mb_strlen($a)
            ));

            $env->set('mb-upcase', new CoreFunc('mb-upcase', 'Multibyte version of upcase.', 1, 1,
                fn (string $a) => mb_strtoupper($a)
            ));

            $env->set('mb-lowcase', new CoreFunc('mb-lowcase', 'Multibyte version of lowcase.', 1, 1,
                fn (string $a) => mb_strtolower($a)
            ));

            $env->set('mb-strpos', new CoreFunc('mb-strpos', 'Multibyte version of strpos.', 2, 3,
                function (string $haystack, string $needle, int $offset = 0) {
                    if (func_num_args() == 2) {
                        return mb_strpos($haystack, $needle);
                    } else {
                        return mb_strpos($haystack, $needle, $offset);
                    }
                }
            ));

            $env->set('mb-stripos', new CoreFunc('mb-stripos', 'Multibyte version of stripos.', 2, 3,
                function (string $haystack, string $needle, int $offset = 0) {
                    if (func_num_args() == 2) {
                        return mb_stripos($haystack, $needle);
                    } else {
                        return mb_stripos($haystack, $needle, $offset);
                    }
                }
            ));

            $env->set('mb-substr', new CoreFunc('mb-substr', 'Multibyte version of substr.', 2, 3,
                function (string $str, int $idx, ?int $len = null) {
                    if (func_num_args() == 2) {
                        return mb_substr($str, $idx);
                    } else {
                        return mb_substr($str, $idx, $len);
                    }
                }
            ));
        }
    }
}
