<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class IrCompiledLoader
{
    public function __construct(
        private Tokenizer $tokenizer,
        private Reader $reader,
        private IrCompiler $compiler
    ) {

    }

    public function load(string $filename): IrCompiledProgram
    {
        $targetFile = realpath(str_replace('~', getenv('HOME') ?: '~', $filename));
        if (!$targetFile || !is_readable($targetFile)) {
            throw new MadLispException("unable to read file $filename");
        }

        $input = @file_get_contents($targetFile);
        if ($input === false) {
            throw new MadLispException("unable to read file $filename");
        }

        // Remove a shebang from scripts before parsing.
        $input = preg_replace('/^#![^\n\r]*[\n\r]+/', '', $input, 1);
        $input = "(do $input)";

        $ast = $this->reader->read($this->tokenizer->tokenize($input));
        return $this->compiler->compile($ast);
    }
}
