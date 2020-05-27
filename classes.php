<?php

class MadLispException extends Exception {}

class Env
{
    private array $data = [];
    private ?Env $parent;

    public function __construct(?Env $parent = null)
    {
        $this->parent = $parent;
    }

    public function has(string $key)
    {
        return array_key_exists($key, $this->data);
    }

    public function get(string $key)
    {
        if ($this->has($key)) {
            return $this->data[$key];
        } elseif ($this->parent) {
            return $this->parent->get($key);
        }

        throw new MadLispException("symbol $key not defined");
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
}
