<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class CompiledFunc extends Func
{
    public function __construct(
        protected CompiledProgram $program,
        protected Env $env,
        protected int $arity,
        protected array $captures = []
    ) {
        parent::__construct(fn () => null);
    }

    public function getProgram(): CompiledProgram
    {
        return $this->program;
    }

    public function getEnv(): Env
    {
        return $this->env;
    }

    public function getArity(): int
    {
        return $this->arity;
    }

    public function getCaptures(): array
    {
        return $this->captures;
    }

    public function call(array $args)
    {
        throw new MadLispException('compiled function must be invoked by executor');
    }
}
