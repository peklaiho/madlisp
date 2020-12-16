<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\MadLispException;
use MadLisp\Hash;
use MadLisp\MList;
use MadLisp\Reader;
use MadLisp\Symbol;
use MadLisp\Vector;

class ReaderTest extends TestCase
{
    public function testReadEmpty()
    {
        $reader = new Reader();
        $result = $reader->read([]);
        $this->assertNull($result);
    }

    public function specialFormProvider(): array
    {
        return [
            ["'", 'quote'],
            ['`', 'quasiquote'],
            ['~', 'unquote'],
        ];
    }

    /**
     * Test readSpecialForm.
     * @dataProvider specialFormProvider
     */
    public function testReadSpecialForm(string $specialChar, string $symbolName)
    {
        $reader = new Reader();

        $result = $reader->read([$specialChar, 'abc']);

        $this->assertInstanceOf(MList::class, $result);
        $data = $result->getData();
        $this->assertCount(2, $data);
        $this->assertInstanceOf(Symbol::class, $data[0]);
        $this->assertInstanceOf(Symbol::class, $data[1]);
        $this->assertSame($symbolName, $data[0]->getName());
        $this->assertSame('abc', $data[1]->getName());
    }

    public function testReadList()
    {
        $reader = new Reader();
        $input = ['(', 'aa', 'bb', '(', 'cc', ')', ')'];
        $result = $reader->read($input);

        $this->assertInstanceOf(MList::class, $result);
        $data = $result->getData();
        $this->assertCount(3, $data);

        $this->assertInstanceOf(Symbol::class, $data[0]);
        $this->assertInstanceOf(Symbol::class, $data[1]);
        $this->assertSame('aa', $data[0]->getName());
        $this->assertSame('bb', $data[1]->getName());

        $this->assertInstanceOf(MList::class, $data[2]);
        $data2 = $data[2]->getData();
        $this->assertCount(1, $data2);
        $this->assertInstanceOf(Symbol::class, $data2[0]);
        $this->assertSame('cc', $data2[0]->getName());
    }

    public function testReadVector()
    {
        $reader = new Reader();
        $input = ['[', 1, 2, '[', 3, ']', ']'];
        $result = $reader->read($input);

        $this->assertInstanceOf(Vector::class, $result);
        $data = $result->getData();
        $this->assertCount(3, $data);

        $this->assertSame(1, $data[0]);
        $this->assertSame(2, $data[1]);

        $this->assertInstanceOf(Vector::class, $data[2]);
        $data2 = $data[2]->getData();
        $this->assertCount(1, $data2);
        $this->assertSame(3, $data2[0]);
    }

    public function testReadHash()
    {
        $reader = new Reader();
        $input = ['{', '"aa"', '"bb"', '"cc"', '{', '"dd"', 123, '}', '}'];
        $result = $reader->read($input);

        $this->assertInstanceOf(Hash::class, $result);
        $data = $result->getData();
        $this->assertCount(2, $data);
        $this->assertSame('bb', $data['aa']);

        $this->assertInstanceOf(Hash::class, $data['cc']);
        $data2 = $data['cc']->getData();
        $this->assertCount(1, $data2);
        $this->assertSame(123, $data2['dd']);
    }

    public function testReadAtom()
    {
        $reader = new Reader();

        $this->assertTrue($reader->read(['true']));
        $this->assertFalse($reader->read(['false']));
        $this->assertNull($reader->read(['null']));
        $this->assertSame('abc', $reader->read(['"abc"']));
        $this->assertSame(123, $reader->read(['123']));
        $this->assertSame(34.56, $reader->read(['34.56']));

        $symbol = $reader->read(['abc']);
        $this->assertInstanceOf(Symbol::class, $symbol);
        $this->assertSame('abc', $symbol->getName());
    }
}
