<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class ExecutionFrame
{
    public int $pc = 0;
    public array $locals;
    public ?array $continuation = null;
    public ?array $collectionOperation = null;

    public function __construct(
        public CompiledProgram $program,
        public Env $env,
        public int $stackBase = 0,
        public ?int $returnPc = null,
        public array $captures = []
    ) {
        $this->locals = array_fill(0, $program->getLocalCount(), null);
    }
}
