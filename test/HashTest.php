<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Hash;
use MadLisp\MadLispException;

class HashTest extends TestCase
{
    public function testHash()
    {
        $hash = new Hash(['a' => 1]);
        $hash->set('b', 2);

        $this->assertSame(1, $hash->get('a'));
        $this->assertSame(2, $hash->get('b'));

        $this->assertSame(2, $hash->unset('b'));

        $this->assertSame(['a' => 1], $hash->getData());
    }

    public function testNotFound()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('hash does not contain key abc');

        $hash = new Hash();
        $hash->get('abc');
    }
}
