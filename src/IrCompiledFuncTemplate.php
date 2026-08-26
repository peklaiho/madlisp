<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class IrCompiledFuncTemplate
{
    public function __construct(
        public IrCompiledProgram $program,
        public int $arity,
        public array $captureSources
    ) {
    }
}
