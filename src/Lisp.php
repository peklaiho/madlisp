<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

class Lisp
{
    protected Tokenizer $tokenizer;
    protected Reader $reader;
    protected Evaller $eval;
    protected Printer $printer;
    protected Env $env;

    public function __construct(Tokenizer $tokenizer, Reader $reader, Evaller $eval, Printer $printer, Env $env)
    {
        $this->tokenizer = $tokenizer;
        $this->reader = $reader;
        $this->eval = $eval;
        $this->printer = $printer;
        $this->env = $env;
    }

    public function print($value, bool $printReadable): void
    {
        $this->printer->print($value, $printReadable);
    }

    public function pstr($value, bool $printReadable): string
    {
        return $this->printer->pstr($value, $printReadable);
    }

    public function readEval(string $input)
    {
        $tokens = $this->tokenizer->tokenize($input);

        $expr = $this->reader->read($tokens);

        return $this->eval->eval($expr, $this->env);
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
