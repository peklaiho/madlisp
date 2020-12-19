<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Lisp;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Tokenizer;
use MadLisp\Lib\Math;

class LispTest extends TestCase
{
    public function testPrint()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $printer->expects($this->once())
            ->method('print')
            ->with($this->equalTo("abc"), $this->equalTo(false));

        $lisp = new Lisp($tokenizer, $reader, $evaller, $printer, new Env('env'));

        $lisp->print('abc', false);
    }

    public function testReadEval()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $tokenizer->expects($this->once())
            ->method('tokenize');

        $reader->expects($this->once())
            ->method('read');

        $evaller->expects($this->once())
            ->method('eval');

        $lisp = new Lisp($tokenizer, $reader, $evaller, $printer, new Env('env'));

        $lisp->readEval('abc');
    }

    public function repProvider(): array
    {
        // This tests all main components together:
        // Tokenizer, Reader, Evaller, Printer

        return [
            ['[(- (+ 2 3) 4) (- (* 2 3) 4)]', false, '[1 2]'],

            ['"string"', false, 'string'],
            ['"string"', true, '"string"'],
        ];
    }

    /**
     * @dataProvider repProvider
     */
    public function testRep(string $input, bool $readable, string $expected)
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $printer = new Printer();
        $evaller = new Evaller($tokenizer, $reader, $printer, false);

        // Define some math functions for testing
        $env = new Env('env');
        $lib = new Math();
        $lib->register($env);

        $lisp = new Lisp($tokenizer, $reader, $evaller, $printer, $env);

        ob_start();
        $lisp->rep($input, $readable);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame($expected, $result);
    }

    public function testSetDebug()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $evaller->expects($this->once())
            ->method('setDebug')
            ->with($this->equalTo(true));

        $lisp = new Lisp($tokenizer, $reader, $evaller, $printer, new Env('env'));

        $lisp->setDebug(true);
    }
}
