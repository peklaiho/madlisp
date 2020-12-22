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

class Regex implements ILib
{
    public function register(Env $env): void
    {
        // More options need to be added for advanced usage. Basic functionality provided for now.

        $env->set('re-match', new CoreFunc('re-match', 'Return true if second argument matches with the regular expression given as first argument. Return the matched text if third argument is true.', 2, 3,
            function (string $pattern, string $subject, bool $returnMatch = false) {
                if ($returnMatch) {
                    if (preg_match($pattern, $subject, $matches)) {
                        return $matches[0];
                    } else {
                        return null;
                    }
                } else {
                    return boolval(preg_match($pattern, $subject));
                }
            }
        ));

        $env->set('re-match-all', new CoreFunc('re-match-all', 'Return all matches in second argument to regular expression given as first argument.', 2, 2,
            function (string $pattern, string $subject) {
                if (preg_match_all($pattern, $subject, $matches) > 0) {
                    return new Vector($matches[0]);
                } else {
                    return new Vector();
                }
            }
        ));

        $env->set('re-replace', new CoreFunc('re-replace', 'Replace matches to regular expression (first argument) by the second argument in the subject (third argument).', 3, 4,
            function (string $pattern, string $replacement, string $subject, int $limit = -1) {
                return preg_replace($pattern, $replacement, $subject, $limit);
            }
        ));

        $env->set('re-split', new CoreFunc('re-split', 'Split second argument by regular expression given as first argument.', 2, 4,
            function (string $pattern, string $subject, int $limit = -1, bool $removeEmpty = true) {
                $flags = 0;
                if ($removeEmpty) {
                    $flags = PREG_SPLIT_NO_EMPTY;
                }

                return new Vector(preg_split($pattern, $subject, $limit, $flags));
            }
        ));
    }
}
