<?php
namespace MadLisp;

abstract class Collection
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
