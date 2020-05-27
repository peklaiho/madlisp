<?php

class MadLispException extends Exception {}

abstract class MLCollection
{
    protected array $data = [];

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function getData(): array
    {
        return $this->data;
    }
}

class MLList extends MLCollection
{
    public function get(int $index)
    {
        if ($this->has($index)) {
            return $this->data[$index];
        }

        throw new MadLispException("list does not contain index $index");
    }
}

class MLHash extends MLCollection
{
    public function get(string $key)
    {
        if ($this->has($key)) {
            return $this->data[$key];
        }

        throw new MadLispException("hash does not contain key $key");
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }
}

class MLEnv extends MLHash
{
    protected ?MLEnv $parent;

    public function __construct(?MLEnv $parent = null)
    {
        $this->parent = $parent;
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
}

class MLSymbol
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }
}
