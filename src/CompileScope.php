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

    public function __construct(?CompileScope $parent = null)
    {
        $this->parent = $parent;
        $this->root = $parent ? $parent->root : $this;
    }

    public function child(): CompileScope
    {
        return new CompileScope($this);
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
