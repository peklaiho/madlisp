<?php
namespace MadLisp;

class Hash extends Collection
{
    public function get(string $key)
    {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }

        throw new MadLispException("hash does not contain key $key");
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
        return $value;
    }

    public function unset(string $key)
    {
        if (array_key_exists($key, $this->data)) {
            $value = $this->data[$key];
            unset($this->data[$key]);
            return $value;
        }

        return null;
    }
}
