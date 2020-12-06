<?php
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

    public function readEval(string $input)
    {
        $tokens = $this->tokenizer->tokenize($input);

        $expr = $this->reader->read($tokens);

        return $this->eval->eval($expr, $this->env);
    }

    // read, eval, print
    public function rep(string $input, bool $printReadable): void
    {
        $result = $this->readEval($input);

        $this->printer->print($result, $printReadable);
    }

    public function setEnv(Env $env): void
    {
        $this->env = $env;
    }
}
