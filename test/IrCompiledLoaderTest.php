<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\IrCompiledLoader;
use MadLisp\IrCompiledProgram;
use MadLisp\IrCompiler;
use MadLisp\IrCoreFuncId;
use MadLisp\IrOpCode;
use MadLisp\Reader;
use MadLisp\Tokenizer;

class IrCompiledLoaderTest extends TestCase
{
    public function testLoadsFile(): void
    {
        $filename = tempnam(sys_get_temp_dir(), 'madlisp-');
        file_put_contents($filename, '(+ 1 2)');

        try {
            $loader = new IrCompiledLoader(new Tokenizer(), new Reader(), new IrCompiler());
            $program = $loader->load($filename);

            $this->assertInstanceOf(IrCompiledProgram::class, $program);
            $this->assertSame([
                IrOpCode::LOAD_CONSTANT, 0,
                IrOpCode::LOAD_CONSTANT, 1,
                IrOpCode::CALL_CORE, IrCoreFuncId::ADD, 2,
                IrOpCode::RETURN,
            ], $program->getCode());
            $this->assertSame([1, 2], $program->getConstants());
            $this->assertSame(0, $program->getLocalCount());
        } finally {
            unlink($filename);
        }
    }
}
