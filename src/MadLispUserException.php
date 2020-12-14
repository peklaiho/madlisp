<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

class MadLispUserException extends \Exception
{
    protected $value;

    public function __construct($value)
    {
        if (is_string($value)) {
            parent::__construct($value);
        }

        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}
