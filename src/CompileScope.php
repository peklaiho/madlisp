<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class CompileScope
{
    protected array $locals = [];
    protected ?CompileScope $parent;
    protected CompileScope $root;
    protected int $nextSlot = 0;
    protected bool $functionBoundary;
    protected ?CompileScope $functionOwner;
    protected array $captures = [];
    protected array $captureSources = [];

    public function __construct(
        ?CompileScope $parent = null,
        bool $functionBoundary = false,
        bool $isolatedSlots = false
    ) {
        $this->parent = $parent;
        $this->root = $isolatedSlots || !$parent ? $this : $parent->root;
        $this->functionBoundary = $functionBoundary;
        $this->functionOwner = $functionBoundary ? $this : $parent?->functionOwner;
    }

    public function child(): CompileScope
    {
        return new CompileScope($this);
    }

    public function functionChild(): CompileScope
    {
        return new CompileScope($this, true, true);
    }

    public function allocate(): int
    {
        return $this->root->nextSlot++;
    }

    public function bind(string $name, int $slot): void
    {
        $this->locals[$name] = $slot;
    }

    public function define(string $name): int
    {
        $slot = $this->allocate();
        $this->bind($name, $slot);

        return $slot;
    }

    public function resolve(string $name): ?int
    {
        if (array_key_exists($name, $this->locals)) {
            return $this->locals[$name];
        }

        return $this->parent ? $this->parent->resolve($name) : null;
    }

    /**
     * Resolve a name captured by the function containing this scope.
     * The returned index is local to that function's capture array.
     */
    public function resolveCapture(string $name): ?int
    {
        $owner = $this->functionOwner;
        if (!$owner) {
            return null;
        }

        for ($scope = $this; $scope && $scope !== $owner; $scope = $scope->parent) {
            if (array_key_exists($name, $scope->locals)) {
                return null;
            }
        }

        if (array_key_exists($name, $owner->locals)) {
            return null;
        }

        $source = $owner->captureSource($name);
        if ($source === null) {
            return null;
        }

        return $owner->registerCapture($name, $source);
    }

    public function getCaptureSources(): array
    {
        return $this->captureSources;
    }

    public function getLocalCount(): int
    {
        return $this->root->nextSlot;
    }

    private function captureSource(string $name): ?array
    {
        for ($scope = $this->parent; $scope; $scope = $scope->parent) {
            if (array_key_exists($name, $scope->locals)) {
                return ['kind' => 'local', 'index' => $scope->locals[$name]];
            }

            if ($scope->functionBoundary) {
                $index = $scope->registerCaptureFromParent($name);
                if ($index === null) {
                    return null;
                }

                return ['kind' => 'capture', 'index' => $index];
            }
        }

        return null;
    }

    private function registerCaptureFromParent(string $name): ?int
    {
        $source = $this->captureSource($name);
        if ($source === null) {
            return null;
        }

        return $this->registerCapture($name, $source);
    }

    private function registerCapture(string $name, array $source): int
    {
        if (array_key_exists($name, $this->captures)) {
            return $this->captures[$name];
        }

        $index = count($this->captures);
        $this->captures[$name] = $index;
        $this->captureSources[$index] = $source;

        return $index;
    }
}
