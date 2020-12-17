<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\MadLispException;

class EnvTest extends TestCase
{
    public function testEnv()
    {
        $aa = new Env('aa');
        $bb = new Env('bb', $aa);
        $cc = new Env('cc', $bb);

        $this->assertSame('aa', $aa->getFullName());
        $this->assertSame('aa/bb', $bb->getFullName());
        $this->assertSame('aa/bb/cc', $cc->getFullName());

        $aa->set('dd', 12);
        $bb->set('ee', 34);
        $cc->set('ff', 56);

        // Make sure get finds values from parent
        $this->assertSame(12, $cc->get('dd'));
        $this->assertSame(34, $cc->get('ee'));
        $this->assertSame(56, $cc->get('ff'));

        $this->assertNull($aa->getParent());
        $this->assertSame($aa, $bb->getParent());
        $this->assertSame($bb, $cc->getParent());

        $this->assertSame($aa, $cc->getRoot());
    }

    public function testNotFound()
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('symbol abc not defined in env');

        $env = new Env('env');
        $env->get('abc');
    }
}
