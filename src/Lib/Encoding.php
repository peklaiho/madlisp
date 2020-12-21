<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\CoreFunc;
use MadLisp\Env;

class Encoding implements ILib
{
    public function register(Env $env): void
    {
        $env->set('bin2hex', new CoreFunc('bin2hex', 'Convert binary data to hexadecimal representation.', 1, 1,
            fn (string $a) => bin2hex($a)
        ));

        $env->set('hex2bin', new CoreFunc('hex2bin', 'Decode a hexadecimally encoded binary string.', 1, 1,
            fn (string $a) => hex2bin($a)
        ));

        $env->set('to-base64', new CoreFunc('to-base64', 'Encode binary data to Base64 representation.', 1, 1,
            fn (string $a) => base64_encode($a)
        ));

        $env->set('from-base64', new CoreFunc('from-base64', 'Decode a Base64 encoded binary string.', 1, 1,
            fn (string $a) => base64_decode($a)
        ));

        $env->set('url-encode', new CoreFunc('url-encode', 'Encode special characters in URL.', 1, 1,
            fn (string $a) => urlencode($a)
        ));

        $env->set('url-decode', new CoreFunc('url-decode', 'Decode special characters in URL.', 1, 1,
            fn (string $a) => urldecode($a)
        ));

        $env->set('utf8-encode', new CoreFunc('utf8-encode', 'Encode ISO-8859-1 string to UTF-8.', 1, 1,
            fn (string $a) => utf8_encode($a)
        ));

        $env->set('utf8-decode', new CoreFunc('utf8-decode', 'Decode UTF-8 string to ISO-8859-1.', 1, 1,
            fn (string $a) => utf8_decode($a)
        ));
    }
}
