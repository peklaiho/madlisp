<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;

class Crypto implements ILib
{
    public function register(Env $env): void
    {
        $env->set('md5', new CoreFunc('md5', 'Calculate the MD5 hash of a string.', 1, 1,
            fn (string $a) => md5($a)
        ));

        $env->set('sha1', new CoreFunc('sha1', 'Calculate the SHA1 hash of a string.', 1, 1,
            fn (string $a) => sha1($a)
        ));

        $env->set('pw-hash', new CoreFunc('pw-hash', 'Calculate hash from a password.', 1, 1,
            fn (string $a) => password_hash($a, PASSWORD_DEFAULT)
        ));

        $env->set('pw-verify', new CoreFunc('pw-verify', 'Verify that given password matches a hash.', 2, 2,
            fn (string $pw, string $hash) => password_verify($pw, $hash)
        ));
    }
}
