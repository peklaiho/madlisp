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
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\Seq;
use MadLisp\Vector;
use MadLisp\Lib\Collections;

class CollectionsTest extends TestCase
{
    public function testHash(): void
    {
        $result = $this->getEnv()->get('hash')->call(['a', 1, 'b', 2]);

        $this->assertInstanceOf(Hash::class, $result);
        $this->assertSame(['a' => 1, 'b' => 2], $result->getData());
    }

    public function testHashWithUnevenArgumentsException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('uneven number of arguments for hash');

        $this->getEnv()->get('hash')->call(['a', 1, 'b']);
    }

    public function testHashWithNonStringKeyException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('invalid key for hash (not string)');

        $this->getEnv()->get('hash')->call([1, 'value']);
    }

    public function testList(): void
    {
        $result = $this->getEnv()->get('list')->call([1, 2, 3]);

        $this->assertInstanceOf(MList::class, $result);
        $this->assertSame([1, 2, 3], $result->getData());
    }

    public function testVector(): void
    {
        $result = $this->getEnv()->get('vector')->call([1, 2, 3]);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame([1, 2, 3], $result->getData());
    }

    public function rangeProvider(): array
    {
        return [
            [[4], [0, 1, 2, 3]],
            [[2, 7, 2], [2, 4, 6]],
        ];
    }

    /** @dataProvider rangeProvider */
    public function testRange(array $args, array $expected): void
    {
        $result = $this->getEnv()->get('range')->call($args);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame($expected, $result->getData());
    }

    public function testLtov(): void
    {
        $result = $this->getEnv()->get('ltov')->call([new MList([1, 2])]);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame([1, 2], $result->getData());
    }

    public function testVtol(): void
    {
        $result = $this->getEnv()->get('vtol')->call([new Vector([1, 2])]);

        $this->assertInstanceOf(MList::class, $result);
        $this->assertSame([1, 2], $result->getData());
    }

    public function testEmpty(): void
    {
        $function = $this->getEnv()->get('empty?');

        // Collections
        $this->assertTrue($function->call([new MList([])]));
        $this->assertFalse($function->call([new Vector([1])]));
        $this->assertFalse($function->call([new Hash(['a' => 1])]));

        // Strings
        $this->assertTrue($function->call(['']));
        $this->assertFalse($function->call(['a']));
    }

    public function testEmptyException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('argument to empty? is not collection or string');

        $this->getEnv()->get('empty?')->call([42]);
    }

    public function testContains(): void
    {
        $function = $this->getEnv()->get('contains?');

        $this->assertTrue($function->call([new MList([1, 2]), 2]));
        $this->assertFalse($function->call([new MList([1, 2]), 3]));
        $this->assertTrue($function->call([new MList([1, 2]), '2']));
        $this->assertFalse($function->call([new MList([1, 2]), '2', true]));
    }

    public function testGet(): void
    {
        $this->assertSame(20, $this->getEnv()->get('get')->call([new Vector([10, 20]), 1]));
        $this->assertSame(20, $this->getEnv()->get('get')->call([new MList([10, 20]), 1]));
        $this->assertSame(20, $this->getEnv()->get('get')->call([new Hash(['a' => 10, 'b' => 20]), 'b']));
    }

    public function testGetMissingListIndexException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('list does not contain index 2');

        $this->getEnv()->get('get')->call([new MList([10, 20]), 2]);
    }

    public function testGetMissingVectorIndexException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('vector does not contain index 2');

        $this->getEnv()->get('get')->call([new Vector([10, 20]), 2]);
    }

    public function testGetMissingHashKeyException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('hash does not contain key c');

        $this->getEnv()->get('get')->call([new Hash(['a' => 10, 'b' => 20]), 'c']);
    }

    public function testLen(): void
    {
        $function = $this->getEnv()->get('len');

        // Collections
        $this->assertSame(2, $function->call([new MList([1, 2])]));
        $this->assertSame(3, $function->call([new Vector([1, 2, 3])]));
        $this->assertSame(2, $function->call([new Hash(['a' => 1, 'b' => 2])]));

        // Strings
        $this->assertSame(3, $function->call(['abc']));
    }

    public function testLenException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('argument to len is not collection or string');

        $this->getEnv()->get('len')->call([42]);
    }

    public function testCarAndFirst(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $this->assertSame(1, $this->getEnv()->get('car')->call([new $type([1, 2, 3])]));
            $this->assertSame(1, $this->getEnv()->get('first')->call([new $type([1, 2, 3])]));
        }
    }

    public function testSecond(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $this->assertSame(2, $this->getEnv()->get('second')->call([new $type([1, 2, 3])]));
        }
    }

    public function testLast(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $this->assertSame(3, $this->getEnv()->get('last')->call([new $type([1, 2, 3])]));
        }
    }

    public function testPenult(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $this->assertSame(3, $this->getEnv()->get('penult')->call([new $type([1, 2, 3, 4])]));
        }
    }

    public function testHead(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('head')->call([new $type([1, 2, 3])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([1, 2], $result->getData());
        }
    }

    public function testCdr(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('cdr')->call([new $type([1, 2, 3])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([2, 3], $result->getData());
        }
    }

    public function testTail(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('tail')->call([new $type([1, 2, 3])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([2, 3], $result->getData());
        }
    }

    public function testSlice(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('slice')->call([new $type([0, 1, 2, 3]), 1, 2]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([1, 2], $result->getData());
        }
    }

    public function testApply(): void
    {
        $function = new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args));

        $this->assertSame(15, $this->getEnv()->get('apply')->call([
            $function, 1, 2, new MList([3, 4, 5])
        ]));
    }

    public function testApplyWithNonFunctionException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('first argument to apply is not function');

        $this->getEnv()->get('apply')->call([42, new MList([1, 2])]);
    }

    public function testApplyWithNonSequenceException(): void
    {
        $function = new CoreFunc('+', '', 1, -1, fn (...$args) => array_sum($args));

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('last argument to apply is not sequence');

        $this->getEnv()->get('apply')->call([$function, 42]);
    }

    public function testChunk(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('chunk')->call([new $type([1, 2, 3, 4, 5]), 2]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([[1, 2], [3, 4], [5]], array_map(
                fn (Seq $chunk) => $chunk->getData(),
                $result->getData()
            ));
        }
    }

    public function testConcat(): void
    {
        // Concat always returns a list, even when called with a vector

        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('concat')->call([
                new $type([1, 2]), new $type([3, 4])
            ]);

            $this->assertInstanceOf(MList::class, $result);
            $this->assertSame([1, 2, 3, 4], $result->getData());
        }
    }

    public function testPush(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('push')->call([new $type([1]), 2, 3]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([1, 2, 3], $result->getData());
        }
    }

    public function testCons(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('cons')->call([0, new $type([1, 2])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([0, 1, 2], $result->getData());
        }
    }

    public function testConsWithNonSequenceException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('last argument to cons is not sequence');

        $this->getEnv()->get('cons')->call([0, 42]);
    }

    public function testMap(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $function = new CoreFunc('double', '', 1, 1, fn ($value) => $value * 2);
            $result = $this->getEnv()->get('map')->call([$function, new $type([1, 2, 3])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([2, 4, 6], $result->getData());
        }
    }

    public function testMap2(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $function = new CoreFunc('add', '', 2, 2, fn ($a, $b) => $a + $b);
            $result = $this->getEnv()->get('map2')->call([
                $function, new $type([1, 2]), new $type([3, 4])
            ]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([4, 6], $result->getData());
        }
    }

    public function testMap2WithUnequalLengthsException(): void
    {
        $function = new CoreFunc('add', '', 2, 2, fn ($a, $b) => $a + $b);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('map2 requires equal number of elements in both sequences');

        $this->getEnv()->get('map2')->call([$function, new MList([1]), new Vector([2, 3])]);
    }

    public function testReduce(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $function = new CoreFunc('add', '', 2, 2, fn ($a, $b) => $a + $b);
            $result = $this->getEnv()->get('reduce')->call([$function, new $type([1, 2, 3]), 10]);

            $this->assertSame(16, $result);
        }
    }

    public function testFilter(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $function = new CoreFunc('positive', '', 1, 1, fn ($value) => $value > 0);
            $result = $this->getEnv()->get('filter')->call([$function, new $type([-1, 0, 2, 3])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([2, 3], $result->getData());
        }
    }

    public function testFilterh(): void
    {
        $function = new CoreFunc('positive', '', 2, 2, fn ($value, $key) => $value > 1 && $key !== 'skip');
        $result = $this->getEnv()->get('filterh')->call([
            $function, new Hash(['keep' => 2, 'skip' => 3, 'remove' => 1])
        ]);

        $this->assertInstanceOf(Hash::class, $result);
        $this->assertSame(['keep' => 2], $result->getData());
    }

    public function testReverse(): void
    {
        $function = $this->getEnv()->get('reverse');

        // Collections
        foreach ([MList::class, Vector::class] as $type) {
            $result = $function->call([new $type([1, 2, 3])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([3, 2, 1], $result->getData());
        }

        // Reverse string
        $this->assertSame('cba', $function->call(['abc']));
    }

    public function testReverseException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('argument to reverse is not sequence or string');

        $this->getEnv()->get('reverse')->call([42]);
    }

    public function testKey(): void
    {
        $this->assertTrue($this->getEnv()->get('key?')->call([new Hash(['a' => 1]), 'a']));
        $this->assertFalse($this->getEnv()->get('key?')->call([new Hash(['a' => 1]), 'b']));
    }

    public function testSet(): void
    {
        $hash = new Hash(['a' => 1]);

        $result = $this->getEnv()->get('set')->call([$hash, 'b', 2]);

        $this->assertInstanceOf(Hash::class, $result);
        $this->assertNotSame($hash, $result); // make sure we get new object
        $this->assertSame(['a' => 1, 'b' => 2], $result->getData());
    }

    public function testSetBang(): void
    {
        $hash = new Hash(['a' => 1]);

        $this->assertSame(2, $this->getEnv()->get('set!')->call([$hash, 'b', 2]));
        $this->assertSame(['a' => 1, 'b' => 2], $hash->getData());
    }

    public function testUnset(): void
    {
        $hash = new Hash(['a' => 1, 'b' => 2]);

        $result = $this->getEnv()->get('unset')->call([$hash, 'b']);

        $this->assertInstanceOf(Hash::class, $result);
        $this->assertNotSame($hash, $result); // make sure we get new object
        $this->assertSame(['a' => 1], $result->getData());
    }

    public function testUnsetBang(): void
    {
        $hash = new Hash(['a' => 1, 'b' => 2]);

        $this->assertSame(2, $this->getEnv()->get('unset!')->call([$hash, 'b']));
        $this->assertSame(['a' => 1], $hash->getData());
    }

    public function testKeys(): void
    {
        $result = $this->getEnv()->get('keys')->call([new Hash(['a' => 1, 'b' => 2])]);

        $this->assertInstanceOf(MList::class, $result);
        $this->assertSame(['a', 'b'], $result->getData());
    }

    public function testValues(): void
    {
        $result = $this->getEnv()->get('values')->call([new Hash(['a' => 1, 'b' => 2])]);

        $this->assertInstanceOf(MList::class, $result);
        $this->assertSame([1, 2], $result->getData());
    }

    public function testZip(): void
    {
        $result = $this->getEnv()->get('zip')->call([
            new MList(['a', 'b']), new Vector([10, 20])
        ]);

        $this->assertSame(['a' => 10, 'b' => 20], $result->getData());
    }

    public function testZipWithUnequalLengthsException(): void
    {
        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('zip requires equal number of keys and values');

        $this->getEnv()->get('zip')->call([new MList(['a']), new Vector([1, 2])]);
    }

    public function testSort(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $result = $this->getEnv()->get('sort')->call([new $type([3, 1, 2])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([1, 2, 3], $result->getData());
        }
    }

    public function testUsort(): void
    {
        foreach ([MList::class, Vector::class] as $type) {
            $function = new CoreFunc('descending', '', 2, 2, fn ($a, $b) => $b - $a);
            $result = $this->getEnv()->get('usort')->call([$function, new $type([1, 3, 2])]);

            $this->assertInstanceOf($type, $result);
            $this->assertSame([3, 2, 1], $result->getData());
        }
    }

    private function getEnv(): Env
    {
        $env = new Env('test');
        (new Collections())->register($env);

        return $env;
    }
}
