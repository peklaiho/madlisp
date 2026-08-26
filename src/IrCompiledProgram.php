<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class IrCompiledProgram
{
    public function __construct(
        protected array $code,
        protected array $constants,
        protected int $localCount
    ) {

    }

    public function getCode(): array
    {
        return $this->code;
    }

    public function getConstants(): array
    {
        return $this->constants;
    }

    public function getLocalCount(): int
    {
        return $this->localCount;
    }
}
