<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\Vector;
use MadLisp\Lib\Strings;

class StringsTest extends TestCase
{
    public function testEOL(): void
    {
        $this->assertSame(\PHP_EOL, $this->getEnv()->get('EOL'));
    }

    public function testTrim(): void
    {
        $fn = $this->getEnv()->get('trim');

        $this->assertSame('text', $fn->call(['  text  ']));
        $this->assertSame('text', $fn->call(['xxtextxx', 'x']));
    }

    public function testLtrim(): void
    {
        $fn = $this->getEnv()->get('ltrim');

        $this->assertSame('text  ', $fn->call(['  text  ']));
        $this->assertSame('textxx', $fn->call(['xxtextxx', 'x']));
    }

    public function testRtrim(): void
    {
        $fn = $this->getEnv()->get('rtrim');

        $this->assertSame('  text', $fn->call(['  text  ']));
        $this->assertSame('xxtext', $fn->call(['xxtextxx', 'x']));
    }

    public function testUpcase(): void
    {
        $fn = $this->getEnv()->get('upcase');

        $this->assertSame('HELLO WORLD', $fn->call(['Hello World']));
        $this->assertSame('', $fn->call(['']));
    }

    public function testLowcase(): void
    {
        $fn = $this->getEnv()->get('lowcase');

        $this->assertSame('hello world', $fn->call(['Hello World']));
        $this->assertSame('', $fn->call(['']));
    }

    public function testStrpos(): void
    {
        $fn = $this->getEnv()->get('strpos');

        $this->assertSame(2, $fn->call(['hello', 'l']));
        $this->assertSame(3, $fn->call(['hello', 'l', 3]));
        $this->assertFalse($fn->call(['hello', 'x']));
    }

    public function testStripos(): void
    {
        $fn = $this->getEnv()->get('stripos');

        $this->assertSame(2, $fn->call(['Hello', 'L']));
        $this->assertSame(3, $fn->call(['Hello', 'L', 3]));
        $this->assertFalse($fn->call(['hello', 'X']));
    }

    public function testSubstr(): void
    {
        $fn = $this->getEnv()->get('substr');

        $this->assertSame('llo', $fn->call(['hello', 2]));
        $this->assertSame('ell', $fn->call(['hello', 1, 3]));
        $this->assertSame('lo', $fn->call(['hello', -2]));
    }

    public function testReplace(): void
    {
        $fn = $this->getEnv()->get('replace');

        $this->assertSame('hello world', $fn->call(['hello there', 'there', 'world']));
        $this->assertSame('one two one', $fn->call(['one two one', 'one', 'one']));
        $this->assertSame('hello', $fn->call(['hello', 'x', 'y']));
    }

    public function testSplit(): void
    {
        $fn = $this->getEnv()->get('split');

        $result = $fn->call([',', 'one,two,three']);
        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame(['one', 'two', 'three'], $result->getData());

        $result = $fn->call(['-', 'one']);
        $this->assertSame(['one'], $result->getData());
    }

    public function testJoin(): void
    {
        $fn = $this->getEnv()->get('join');

        $this->assertSame('one,two,three', $fn->call([',', 'one', 'two', 'three']));
        $this->assertSame('', $fn->call([',']));
        $this->assertSame('one', $fn->call([',', 'one']));
    }

    public function testFormat(): void
    {
        $fn = $this->getEnv()->get('format');

        $this->assertSame('Hello, Ada!', $fn->call(['Hello, %s!', 'Ada']));
        $this->assertSame('2 + 3 = 5', $fn->call(['%d + %d = %d', 2, 3, 5]));
        $this->assertSame('plain text', $fn->call(['plain text']));
    }

    public function testPrefix(): void
    {
        $fn = $this->getEnv()->get('prefix?');

        $this->assertTrue($fn->call(['hello', 'he']));
        $this->assertFalse($fn->call(['hello', 'lo']));
        $this->assertTrue($fn->call(['hello', '']));
    }

    public function testSuffix(): void
    {
        $fn = $this->getEnv()->get('suffix?');

        $this->assertTrue($fn->call(['hello', 'lo']));
        $this->assertFalse($fn->call(['hello', 'he']));
        $this->assertTrue($fn->call(['hello', '']));
    }

    public function testStrcmp(): void
    {
        $fn = $this->getEnv()->get('strcmp');

        $this->assertSame(0, $fn->call(['same', 'same']));
        $this->assertLessThan(0, $fn->call(['apple', 'banana']));
        $this->assertGreaterThan(0, $fn->call(['banana', 'apple']));
    }

    public function testStrcasecmp(): void
    {
        $fn = $this->getEnv()->get('strcasecmp');

        $this->assertSame(0, $fn->call(['Hello', 'hello']));
        $this->assertLessThan(0, $fn->call(['apple', 'BANANA']));
        $this->assertGreaterThan(0, $fn->call(['BANANA', 'apple']));
    }

    public function testStrnatcmp(): void
    {
        $fn = $this->getEnv()->get('strnatcmp');

        $this->assertSame(0, $fn->call(['file2', 'file2']));
        $this->assertLessThan(0, $fn->call(['file2', 'file10']));
        $this->assertGreaterThan(0, $fn->call(['file10', 'file2']));
    }

    public function testStrnatcasecmp(): void
    {
        $fn = $this->getEnv()->get('strnatcasecmp');

        $this->assertSame(0, $fn->call(['File2', 'file2']));
        $this->assertLessThan(0, $fn->call(['File2', 'file10']));
        $this->assertGreaterThan(0, $fn->call(['File10', 'file2']));
    }

    private function getEnv(): Env
    {
        $env = new Env('test');
        (new Strings())->register($env);

        return $env;
    }
}
