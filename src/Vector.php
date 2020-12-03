<?php
namespace MadLisp;

class Vector extends Seq
{
    public function get(int $index)
    {
        if (array_key_exists($index, $this->data)) {
            return $this->data[$index];
        }

        throw new MadLispException("vector does not contain index $index");
    }
}