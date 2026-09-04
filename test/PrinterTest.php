<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\UserFunc;
use MadLisp\MList;
use MadLisp\Printer;
use MadLisp\Symbol;
use MadLisp\Vector;

class PrinterTest extends TestCase
{
    public static function notReadableProvider(): array
    {
        $mc = new UserFunc(fn () => null, null, new Env('test'), new MList([]), true);
        $fn = new UserFunc(fn () => null, null, new Env('test'), new MList([]));

        return [
            [$mc, '<macro>'],
            [$fn, '<function>'],
            [new MList([new Symbol('aa'), new Symbol('bb'), new MList([new Symbol('cc')])]), '(aa bb (cc))'],
            [new Vector([12, 34, new Vector([56])]), '[12 34 [56]]'],
            [new Hash(['aa' => 'bb', 'cc' => new Hash(['dd' => 'ee'])]), '{aa:bb cc:{dd:ee}}'],
            [new Symbol('abc'), 'abc'],
            [new \stdClass(), '<object<stdClass>>'],
            [true, 'true'],
            [false, 'false'],
            [null, 'null'],
            [123, '123'],
            [34.56, '34.56'],

            // Test strings
            ['abc', 'abc'],
            ["a\\b\nc\rd\te\"f\vg\0h", "a\\b\nc\rd\te\"f\vg\0h"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notReadableProvider')]
    public function testPrintStringNotReadable($input, string $expected)
    {
        $printer = new Printer();
        $result = $printer->pstr($input, false);
        $this->assertSame($expected, $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notReadableProvider')]
    public function testPrintNotReadable($input, string $expected)
    {
        $printer = new Printer();

        ob_start();
        $printer->print($input, false);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame($expected, $result);
    }

    public static function readableProvider(): array
    {
        return [
            [new Hash(['aa' => 'bb', 'cc' => new Hash(['dd' => 'ee'])]), '{"aa":"bb" "cc":{"dd":"ee"}}'],
            [new Symbol('abc'), 'abc'], // symbol is not quoted

            // Test strings
            ['abc', '"abc"'],
            ["a\\b\nc\rd\te\"f\vg\0h", "\"a\\\\b\\nc\\rd\\te\\\"f\\vg\\0h\""],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readableProvider')]
    public function testPrintStringReadable($input, string $expected)
    {
        $printer = new Printer();
        $result = $printer->pstr($input, true);
        $this->assertSame($expected, $result);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readableProvider')]
    public function testPrintReadable($input, string $expected)
    {
        $printer = new Printer();

        ob_start();
        $printer->print($input, true);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame($expected, $result);
    }

    public function testPrintResource()
    {
        $file = tmpfile();

        $printer = new Printer();
        $result = $printer->pstr($file, false);
        $this->assertSame('<resource>', $result);

        fclose($file);
    }
}
