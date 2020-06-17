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

    public function rep(string $input, bool $printReadable): void
    {
        $tokens = $this->tokenizer->tokenize($input);

        $expr = $this->reader->read($tokens);

        $result = $this->eval->eval($expr, $this->env);

        $this->printer->print($result, $printReadable);
    }
}
