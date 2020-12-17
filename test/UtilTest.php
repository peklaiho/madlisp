<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\Util;

class UtilTest extends TestCase
{
    public function testMakeHash()
    {
        $hash = Util::makeHash(['a', 1, 'b', 2]);

        $this->assertInstanceOf(Hash::class, $hash);
        $this->assertSame(['a' => 1, 'b' => 2], $hash->getData());
    }

    public function testMakeHashUnevenArgs()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('uneven number of arguments for hash');

        Util::makeHash(['a', 1, 'b', 2, 'c']);
    }

    public function testMakeHashInvalidKey()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('invalid key for hash (not string)');

        Util::makeHash([1, 2]);
    }
}
