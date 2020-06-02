<?php
namespace MadLisp;

class Hash extends Collection
{
    public function get(string $key)
    {
        if ($this->has($key)) {
            return $this->data[$key];
        }

        throw new MadLispException("hash does not contain key $key");
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
        return $value;
    }
}
