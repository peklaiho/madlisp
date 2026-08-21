<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class CompiledFuncTemplate
{
    public function __construct(
        public CompiledProgram $program,
        public int $arity,
        public array $captureSources
    ) {
    }
}
