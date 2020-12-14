<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

class MList extends Seq
{
    public function get(int $index)
    {
        if (array_key_exists($index, $this->data)) {
            return $this->data[$index];
        }

        throw new MadLispException("list does not contain index $index");
    }
}
