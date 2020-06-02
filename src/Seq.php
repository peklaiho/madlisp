<?php
namespace MadLisp;

abstract class Seq extends Collection
{
    public static function new(array $data = []): self
    {
        // late static binding
        return new static($data);
    }
}
