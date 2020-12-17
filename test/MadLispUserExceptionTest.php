<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\MadLispUserException;
use MadLisp\Vector;

class MadLispUserExceptionTest extends TestCase
{
    public function testException()
    {
        try {
            throw new MadLispUserException('message');
        } catch (MadLispUserException $ex) {
            $this->assertSame('message', $ex->getMessage());
            $this->assertSame('message', $ex->getValue());
        }

        $value = new Vector([1, 2, 3]);

        try {
            throw new MadLispUserException($value);
        } catch (MadLispUserException $ex) {
            $this->assertSame($value, $ex->getValue());
        }
    }
}
