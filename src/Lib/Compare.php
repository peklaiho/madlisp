<?php
namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;

class Compare implements ILib
{
    public function register(Env $env): void
    {
        // TODO: handle collections

        $env->set('=', new CoreFunc('=', 'Return true if arguments are equal.', 2, 2,
            fn ($a, $b) => $a == $b
        ));

        $env->set('==', new CoreFunc('==', 'Return true if arguments are equal using strict comparison.', 2, 2,
            fn ($a, $b) => $a === $b
        ));

        $env->set('!=', new CoreFunc('!=', 'Return true if arguments are not equal.', 2, 2,
            fn ($a, $b) => $a != $b
        ));

        $env->set('!==', new CoreFunc('!==', 'Return true if arguments are not equal using strict comparison.', 2, 2,
            fn ($a, $b) => $a !== $b
        ));

        $env->set('<', new CoreFunc('<', 'Return true if first argument is less than second argument.', 2, 2,
            fn ($a, $b) => $a < $b
        ));

        $env->set('<=', new CoreFunc('<=', 'Return true if first argument is less or equal to second argument.', 2, 2,
            fn ($a, $b) => $a <= $b
        ));

        $env->set('>', new CoreFunc('>', 'Return true if first argument is greater than second argument.', 2, 2,
            fn ($a, $b) => $a > $b
        ));

        $env->set('>=', new CoreFunc('>=', 'Return true if first argument is greater or equal to second argument.', 2, 2,
            fn ($a, $b) => $a >= $b
        ));
    }
}
