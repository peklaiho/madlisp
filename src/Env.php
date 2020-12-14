<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

class Env extends Hash
{
    protected string $name;
    protected ?Env $parent;

    public function __construct(string $name, ?Env $parent = null)
    {
        $this->name = $name;
        $this->parent = $parent;
    }

    public function getFullName(): string
    {
        if ($this->parent) {
            return $this->parent->getFullName() . '/' . $this->name;
        }

        return $this->name;
    }

    public function get(string $key, bool $throw = true)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        } elseif ($this->parent) {
            return $this->parent->get($key, $throw);
        }

        if ($throw) {
            throw new MadLispException("symbol $key not defined in env");
        } else {
            return null;
        }
    }

    public function getParent(): ?Env
    {
        return $this->parent;
    }

    public function getRoot(): ?Env
    {
        return $this->parent ? $this->parent->getRoot() : $this;
    }
}
