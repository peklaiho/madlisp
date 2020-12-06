<?php
namespace MadLisp;

abstract class Collection
{
    public static function new(array $data = []): self
    {
        // late static binding
        return new static($data);
    }

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
