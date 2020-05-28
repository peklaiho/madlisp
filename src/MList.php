<?php
namespace MadLisp;

class MList extends Collection
{
    public function get(int $index)
    {
        if ($this->has($index)) {
            return $this->data[$index];
        }

        throw new MadLispException("list does not contain index $index");
    }
}
