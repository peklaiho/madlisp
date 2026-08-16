<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
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

    public function testSetReplacesExistingValue()
    {
        $hash = new Hash(['a' => 1]);

        $this->assertSame(2, $hash->set('a', 2));
        $this->assertSame(2, $hash->get('a'));
        $this->assertSame(['a' => 2], $hash->getData());
    }

    public function testHasChecksWhetherKeyExists()
    {
        $hash = new Hash(['a' => 1, 'null' => null]);

        $this->assertTrue($hash->has('a'));
        $this->assertTrue($hash->has('null'));
        $this->assertFalse($hash->has('missing'));
    }

    public function testUnsetMissingKeyReturnsNull()
    {
        $hash = new Hash(['a' => 1]);

        $this->assertNull($hash->unset('missing'));
        $this->assertSame(['a' => 1], $hash->getData());
    }
}
