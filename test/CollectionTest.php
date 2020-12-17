<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Hash;
use MadLisp\MList;
use MadLisp\Vector;

class CollectionTest extends TestCase
{
    public function testNew()
    {
        // Test that Collection::new returns the correct type

        $a = new Vector();
        $vector = $a::new();

        $a = new MList();
        $list = $a::new();

        $a = new Hash();
        $hash = $a::new();

        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertInstanceOf(MList::class, $list);
        $this->assertInstanceOf(Hash::class, $hash);
    }

    public function testCollection()
    {
        $a = new Hash(['a' => 1, 'b' => 2, 'c' => 3]);

        $data = $a->getData();
        $this->assertCount(3, $data);
        $this->assertSame(3, $a->count());
        $this->assertTrue($a->has('a'));
        $this->assertFalse($a->has('d'));
        $this->assertSame(['a' => 1, 'b' => 2, 'c' => 3], $data);
    }
}
