<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Lisp
{
    public function __construct(
        protected Tokenizer $tokenizer,
        protected Reader $reader,
        protected MacroExpander $macroExpander,
        protected PhpCompiler $compiler,
        protected Evaller $eval,
        protected Printer $printer,
        protected Env $env
    ) {

    }

    public function compile($ast, ?Env $customEnv = null): PhpCompiledProgram
    {
        $ast = $this->macroExpander->expand($ast, $customEnv ? $customEnv : $this->env);

        return $this->compiler->compile($ast);
    }

    public function execute(PhpCompiledProgram $program, ?Env $customEnv = null)
    {
        return $program->execute($customEnv ? $customEnv : $this->env);
    }

    public function getEnv(): Env
    {
        return $this->env;
    }

    public function print($value, bool $printReadable): void
    {
        $this->printer->print($value, $printReadable);
    }

    public function pstr($value, bool $printReadable): string
    {
        return $this->printer->pstr($value, $printReadable);
    }

    public function read(string $input)
    {
        return $this->reader->read($this->tokenizer->tokenize($input));
    }

    public function readEval(string $input, ?Env $customEnv = null)
    {
        $tokens = $this->tokenizer->tokenize($input);

        $expr = $this->reader->read($tokens);

        return $this->eval->eval($expr, $customEnv ? $customEnv : $this->env);
    }

    public function readEvalCompiled(string $input, ?Env $customEnv = null)
    {
        $tokens = $this->tokenizer->tokenize($input);

        $ast = $this->reader->read($tokens);

        $program = $this->compile($ast, $customEnv);

        return $program->execute($customEnv ? $customEnv : $this->env);
    }

    // read, eval, print
    public function rep(string $input, bool $printReadable): void
    {
        $this->print($this->readEval($input), $printReadable);
    }

    public function setDebug(bool $value): void
    {
        $this->eval->setDebug($value);
    }

    public function setEnv(Env $env): void
    {
        $this->env = $env;
    }

    public function setEnvValue(string $key, $value): void
    {
        $this->env->set($key, $value);
    }
}
