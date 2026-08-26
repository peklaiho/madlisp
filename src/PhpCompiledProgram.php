<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class PhpCompiledProgram
{
    public function __construct(
        protected \Closure $closure,
        protected string $source
    ) {

    }

    public function execute(Env $env)
    {
        return ($this->closure)($env);
    }

    public function getSource(): string
    {
        return $this->source;
    }
}
