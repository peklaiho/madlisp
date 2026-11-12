<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Env;
use MadLisp\Hash;
use MadLisp\MList;
use MadLisp\Vector;
use MadLisp\Lib\Json;

class JsonTest extends TestCase
{
    public function testEncodeEmptyHash(): void
    {
        $this->assertSame('{}', $this->getEnv()->get('to-json')->call([new Hash()]));
    }

    public function testEncodeHashWithNumericStringKeys(): void
    {
        $value = new Hash(['0' => 'a', '1' => 'b']);

        $this->assertSame('{"0":"a","1":"b"}', $this->getEnv()->get('to-json')->call([$value]));
    }

    public function testEncodeNestedCollections(): void
    {
        $value = new MList([
            new Hash([
                'items' => new Vector([new Hash(['0' => new MList([1, 2])])]),
                'empty' => new Hash(),
            ]),
            new MList(),
            new Vector(),
        ]);

        $this->assertSame(
            '[{"items":[{"0":[1,2]}],"empty":{}},[],[]]',
            $this->getEnv()->get('to-json')->call([$value])
        );
    }

    public function testRoundTripEmptyObject(): void
    {
        $env = $this->getEnv();
        $value = $env->get('from-json')->call(['{}']);

        $this->assertSame('{}', $env->get('to-json')->call([$value]));
    }

    private function getEnv(): Env
    {
        $env = new Env('test');
        (new Json())->register($env);

        return $env;
    }
}
