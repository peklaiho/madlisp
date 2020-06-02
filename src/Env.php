<?php
namespace MadLisp;

class Env extends Hash
{
    protected ?Env $parent;

    public function __construct(?Env $parent = null)
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

        throw new MadLispException("symbol $key not defined in env");
    }
}
