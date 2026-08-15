<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\MList;
use MadLisp\Symbol;
use MadLisp\UserFunc;
use MadLisp\Vector;
use MadLisp\Lib\Types;

class TypesTest extends TestCase
{
    public function testBool(): void
    {
        $this->assertTrue($this->getEnv()->get('bool')->call([1]));
        $this->assertFalse($this->getEnv()->get('bool')->call([0]));

        $this->assertTrue($this->getEnv()->get('bool')->call(['text']));
        $this->assertFalse($this->getEnv()->get('bool')->call(['']));
    }

    public function testFloat(): void
    {
        $this->assertSame(2.5, $this->getEnv()->get('float')->call(['2.5']));
    }

    public function testInt(): void
    {
        $this->assertSame(2, $this->getEnv()->get('int')->call(['2.9']));
    }

    public function testStr(): void
    {
        $this->assertSame('value is 42', $this->getEnv()->get('str')->call(['value ', new Symbol('is '), 42]));
    }

    public function testSymbol(): void
    {
        $result = $this->getEnv()->get('symbol')->call(['hello']);

        $this->assertInstanceOf(Symbol::class, $result);
        $this->assertSame('hello', $result->getName());
    }

    public function testNot(): void
    {
        $this->assertTrue($this->getEnv()->get('not')->call([false]));
        $this->assertFalse($this->getEnv()->get('not')->call([true]));
    }

    public function testType(): void
    {
        $test = $this->getEnv()->get('type');

        $fn = new CoreFunc('fn', '', 0, 0, fn () => null);
        $this->assertSame('function', $test->call([$fn]));

        $fn = new UserFunc(fn () => null, null, new Env('fn'), new MList(), false);
        $this->assertSame('function', $test->call([$fn]));

        $macro = new UserFunc(fn () => null, null, new Env('fn'), new MList(), true);
        $this->assertSame('macro', $test->call([$macro]));

        $this->assertSame('list', $test->call([new MList()]));
        $this->assertSame('vector', $test->call([new Vector()]));
        $this->assertSame('hash', $test->call([new Hash()]));
        $this->assertSame('symbol', $test->call([new Symbol('name')]));
        $this->assertSame('object', $test->call([new stdClass]));

        $res = fopen('php://memory', 'r');
        $this->assertSame('resource', $test->call([$res]));
        fclose($res);

        $this->assertSame('bool', $test->call([true]));
        $this->assertSame('bool', $test->call([false]));
        $this->assertSame('null', $test->call([null]));
        $this->assertSame('int', $test->call([42]));
        $this->assertSame('float', $test->call([2.5]));
        $this->assertSame('string', $test->call(['hello']));
    }

    public function testFn(): void
    {
        $test = $this->getEnv()->get('fn?');

        $this->assertFalse($test->call([1]));

        $fn = new CoreFunc('fn', '', 0, 0, fn () => null);
        $this->assertTrue($test->call([$fn]));

        $fn = new UserFunc(fn () => null, null, new Env('fn'), new MList(), false);
        $this->assertTrue($test->call([$fn]));

        $macro = new UserFunc(fn () => null, null, new Env('fn'), new MList(), true);
        $this->assertTrue($test->call([$macro]));
    }

    public function testMacro(): void
    {
        $test = $this->getEnv()->get('macro?');

        $this->assertFalse($test->call([1]));

        $fn = new CoreFunc('fn', '', 0, 0, fn () => null);
        $this->assertFalse($test->call([$fn]));

        $fn = new UserFunc(fn () => null, null, new Env('fn'), new MList(), false);
        $this->assertFalse($test->call([$fn]));

        $macro = new UserFunc(fn () => null, null, new Env('fn'), new MList(), true);
        $this->assertTrue($test->call([$macro]));
    }

    public function testList(): void
    {
        $test = $this->getEnv()->get('list?');

        $this->assertTrue($test->call([new MList([])]));
        $this->assertFalse($test->call([new Vector([])]));
    }

    public function testVector(): void
    {
        $test = $this->getEnv()->get('vector?');

        $this->assertTrue($test->call([new Vector([])]));
        $this->assertFalse($test->call([new MList([])]));
    }

    public function testSeq(): void
    {
        $test = $this->getEnv()->get('seq?');

        $this->assertTrue($test->call([new MList([])]));
        $this->assertTrue($test->call([new Vector([])]));
        $this->assertFalse($test->call([new Hash([])]));
    }

    public function testHash(): void
    {
        $test = $this->getEnv()->get('hash?');

        $this->assertTrue($test->call([new Hash([])]));
        $this->assertFalse($test->call([new MList([])]));
    }

    public function testSymbolPredicate(): void
    {
        $test = $this->getEnv()->get('symbol?');

        $this->assertTrue($test->call([new Symbol('name')]));
        $this->assertFalse($test->call(['name']));
    }

    public function testObject(): void
    {
        $test = $this->getEnv()->get('object?');

        $this->assertTrue($test->call([new stdClass()]));
        $this->assertFalse($test->call([new MList()]));
        $this->assertFalse($test->call([42]));
    }

    public function testResource(): void
    {
        $test = $this->getEnv()->get('resource?');
        $resource = fopen('php://memory', 'r');

        $this->assertTrue($test->call([$resource]));
        $this->assertFalse($test->call(['resource']));
        fclose($resource);
    }

    public function testBoolPredicate(): void
    {
        $test = $this->getEnv()->get('bool?');

        $this->assertTrue($test->call([false]));
        $this->assertTrue($test->call([true]));
        $this->assertFalse($test->call([0]));
    }

    public function testTrue(): void
    {
        $test = $this->getEnv()->get('true?');

        // This predicate intentionally uses loose comparison.
        $this->assertTrue($test->call([true]));
        $this->assertTrue($test->call([1]));
        $this->assertTrue($test->call(['1']));
        $this->assertFalse($test->call([false]));
        $this->assertFalse($test->call([0]));
        $this->assertFalse($test->call(['']));
    }

    public function testFalse(): void
    {
        $test = $this->getEnv()->get('false?');

        // This predicate intentionally uses loose comparison.
        $this->assertTrue($test->call([false]));
        $this->assertTrue($test->call([0]));
        $this->assertTrue($test->call(['']));
        $this->assertFalse($test->call([true]));
        $this->assertFalse($test->call([1]));
        $this->assertFalse($test->call(['1']));
    }

    public function testNull(): void
    {
        $test = $this->getEnv()->get('null?');

        $this->assertTrue($test->call([null]));
        $this->assertFalse($test->call([false]));
        $this->assertFalse($test->call([0]));
        $this->assertFalse($test->call(['']));
    }

    public function testIntPredicate(): void
    {
        $test = $this->getEnv()->get('int?');

        $this->assertTrue($test->call([1]));
        $this->assertFalse($test->call([1.0]));
        $this->assertFalse($test->call(['1']));
    }

    public function testFloatPredicate(): void
    {
        $test = $this->getEnv()->get('float?');

        $this->assertTrue($test->call([1.0]));
        $this->assertFalse($test->call([1]));
        $this->assertFalse($test->call(['1.0']));
    }

    public function testStrPredicate(): void
    {
        $test = $this->getEnv()->get('str?');

        $this->assertTrue($test->call(['text']));
        $this->assertFalse($test->call([new Symbol('text')]));
        $this->assertFalse($test->call([1]));
    }

    public function testZero(): void
    {
        $test = $this->getEnv()->get('zero?');

        $this->assertTrue($test->call([0]));
        $this->assertFalse($test->call([0.0]));
        $this->assertFalse($test->call(['0']));
        $this->assertFalse($test->call([1]));
    }

    public function testOne(): void
    {
        $test = $this->getEnv()->get('one?');

        $this->assertTrue($test->call([1]));
        $this->assertFalse($test->call([1.0]));
        $this->assertFalse($test->call(['1']));
        $this->assertFalse($test->call([0]));
    }

    public function testEven(): void
    {
        $test = $this->getEnv()->get('even?');

        $this->assertTrue($test->call([4]));
        $this->assertTrue($test->call([-2]));
        $this->assertFalse($test->call([3]));
    }

    public function testOdd(): void
    {
        $test = $this->getEnv()->get('odd?');

        $this->assertTrue($test->call([3]));
        $this->assertTrue($test->call([-1]));
        $this->assertFalse($test->call([4]));
    }

    private function getEnv(): Env
    {
        $env = new Env('test');
        (new Types())->register($env);

        return $env;
    }
}
