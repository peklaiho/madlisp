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

    public function __construct(
        ?CompileScope $parent = null,
        bool $functionBoundary = false,
        bool $isolatedSlots = false
    ) {
        $this->parent = $parent;
        $this->root = $isolatedSlots || !$parent ? $this : $parent->root;
        $this->functionBoundary = $functionBoundary;
    }

    public function child(): CompileScope
    {
        return new CompileScope($this);
    }

    public function functionChild(): CompileScope
    {
        return new CompileScope($this, true, true);
    }

    public function isCapture(string $name): bool
    {
        $scope = $this;

        while ($scope) {
            if (array_key_exists($name, $scope->locals)) {
                return false;
            }

            if ($scope->functionBoundary) {
                return $scope->parent?->resolve($name) !== null;
            }

            $scope = $scope->parent;
        }

        return false;
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

    public function getLocalCount(): int
    {
        return $this->root->nextSlot;
    }
}
