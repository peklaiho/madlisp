<?php
namespace MadLisp;

class Vector extends Seq
{
    public function get(int $index)
    {
        if ($this->has($index)) {
            return $this->data[$index];
        }

        throw new MadLispException("vector does not contain index $index");
    }
}