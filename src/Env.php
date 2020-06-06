<?php
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

    public function get(string $key)
    {
        if ($this->has($key)) {
            return $this->data[$key];
        } elseif ($this->parent) {
            return $this->parent->get($key);
        }

        throw new MadLispException("symbol $key not defined in env");
    }

    public function set(string $key, $value)
    {
        // Do not allow overwriting values in root env
        if ($this->has($key) && $this->parent == null) {
            throw new MadLispException("attempt to overwrite $key in root env");
        }

        return parent::set($key, $value);
    }
}
