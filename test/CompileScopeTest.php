<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CompileScope;

class CompileScopeTest extends TestCase
{
    public function testAllocatesAndResolvesBindings(): void
    {
        $scope = new CompileScope();

        $this->assertSame(0, $scope->allocate());
        $scope->bind('bound', 3);
        $this->assertSame(3, $scope->resolve('bound'));
        $this->assertSame(1, $scope->define('defined'));
        $this->assertSame(1, $scope->resolve('defined'));
        $this->assertNull($scope->resolve('missing'));
        $this->assertSame(2, $scope->getLocalCount());
    }

    public function testChildScopeResolvesParentBindingsAndSharesSlots(): void
    {
        $parent = new CompileScope();
        $parent->define('value');
        $child = $parent->child();

        $this->assertSame(0, $child->resolve('value'));
        $this->assertSame(1, $child->define('childValue'));
        $this->assertSame(2, $parent->getLocalCount());
        $this->assertSame(2, $child->getLocalCount());

        $child->bind('value', 5);
        $this->assertSame(5, $child->resolve('value'));
        $this->assertSame(0, $parent->resolve('value'));
    }

    public function testFunctionChildUsesIsolatedSlots(): void
    {
        $parent = new CompileScope();
        $parent->define('parentValue');
        $function = $parent->functionChild();

        $this->assertSame(0, $function->define('localValue'));
        $this->assertSame(0, $function->resolve('parentValue'));
        $this->assertSame(1, $function->getLocalCount());
        $this->assertSame(1, $parent->getLocalCount());
    }

    public function testResolvesCapturesFromEnclosingFunction(): void
    {
        $root = new CompileScope();
        $root->define('value');
        $function = $root->functionChild();
        $body = $function->child();

        $this->assertSame(0, $body->resolveCapture('value'));
        $this->assertSame(0, $body->resolveCapture('value'));
        $this->assertSame([
            ['kind' => 'local', 'index' => 0],
        ], $function->getCaptureSources());
        $this->assertSame([], $root->getCaptureSources());
    }

    public function testNestedFunctionCapturesThroughOuterFunction(): void
    {
        $root = new CompileScope();
        $root->define('value');
        $outer = $root->functionChild();
        $inner = $outer->functionChild();

        $this->assertSame(0, $inner->resolveCapture('value'));
        $this->assertSame([
            ['kind' => 'capture', 'index' => 0],
        ], $inner->getCaptureSources());
        $this->assertSame([
            ['kind' => 'local', 'index' => 0],
        ], $outer->getCaptureSources());
    }

    public function testLocalBindingPreventsCapture(): void
    {
        $root = new CompileScope();
        $root->define('value');
        $function = $root->functionChild();
        $body = $function->child();
        $body->define('value');

        $this->assertNull($body->resolveCapture('value'));
        $this->assertSame([], $function->getCaptureSources());
    }
}
