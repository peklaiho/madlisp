<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CompiledLoader;
use MadLisp\CompiledProgram;
use MadLisp\Compiler;
use MadLisp\CoreFuncId;
use MadLisp\OpCode;
use MadLisp\Reader;
use MadLisp\Tokenizer;

class CompiledLoaderTest extends TestCase
{
    public function testLoadsFile(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'madlisp-');
        file_put_contents($filename, '(+ 1 2)');

        try {
            $loader = new CompiledLoader(new Tokenizer(), new Reader(), new Compiler());
            $program = $loader->load($filename);

            $this->assertInstanceOf(CompiledProgram::class, $program);
            $this->assertSame([
                OpCode::LOAD_CONSTANT, 0,
                OpCode::LOAD_CONSTANT, 1,
                OpCode::CALL_CORE, CoreFuncId::ADD, 2,
                OpCode::RETURN,
            ], $program->getCode());
            $this->assertSame([1, 2], $program->getConstants());
            $this->assertSame(0, $program->getLocalCount());
        } finally {
            unlink($filename);
        }
    }
}
