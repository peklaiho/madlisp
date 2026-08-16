<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
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

    public function testChildShadowsParentBinding()
    {
        $parent = new Env('parent');
        $child = new Env('child', $parent);

        $parent->set('value', 10);
        $child->set('value', 20);

        $this->assertSame(20, $child->get('value'));
        $this->assertSame(10, $parent->get('value'));
    }

    public function testSetCreatesOrUpdatesOnlyLocalBinding()
    {
        $parent = new Env('parent');
        $child = new Env('child', $parent);

        $parent->set('value', 10);
        $child->set('value', 20);
        $child->set('new-value', 30);

        $this->assertSame(10, $parent->get('value'));
        $this->assertSame(20, $child->get('value'));
        $this->assertFalse($parent->has('new-value'));
        $this->assertSame(30, $child->get('new-value'));
    }

    public function testUnsetOnlyRemovesLocalBinding()
    {
        $parent = new Env('parent');
        $child = new Env('child', $parent);

        $parent->set('value', 10);
        $child->set('value', 20);

        $this->assertSame(20, $child->unset('value'));
        $this->assertSame(10, $child->get('value'));
        $this->assertNull($child->unset('value'));
        $this->assertSame(10, $parent->get('value'));
    }

    public function testNullBindingIsDefined()
    {
        $env = new Env('env');
        $env->set('value', null);

        $this->assertTrue($env->has('value'));
        $this->assertNull($env->get('value'));
    }

    public function testMissingLookupCanReturnNull()
    {
        $root = new Env('root');
        $child = new Env('child', $root);
        $grandchild = new Env('grandchild', $child);

        $this->assertNull($grandchild->get('missing', false));
    }

    public function testNonThrowingLookupStillFindsParentValue()
    {
        $parent = new Env('parent');
        $child = new Env('child', $parent);

        $parent->set('value', 123);

        $this->assertSame(123, $child->get('value', false));
    }
}
