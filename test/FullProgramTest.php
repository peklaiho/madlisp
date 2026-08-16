<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\LispFactory;

class FullProgramTest extends TestCase
{
    public function testPyramid()
    {
        $this->runFile(
            __DIR__ . '/lisp/pyramid.lisp',
            __DIR__ . '/lisp/pyramid.result'
        );
    }

    private function runFile($programFile, $resultFile)
    {
        $lisp = (new LispFactory())->make();

        ob_start();

        $lisp->readEval("(load \"$programFile\")");

        $output = ob_get_contents();
        ob_end_clean();

        $expected = file_get_contents($resultFile);

        $this->assertSame($expected, $output);
    }
}
