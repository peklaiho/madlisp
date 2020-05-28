<?php
namespace MadLisp;

class Lisp
{
    protected Tokenizer $tokenizer;
    protected Reader $reader;
    protected Evaller $eval;
    protected Printer $printer;

    public function __construct(Tokenizer $tokenizer, Reader $reader, Evaller $eval, Printer $printer)
    {
        $this->tokenizer = $tokenizer;
        $this->reader = $reader;
        $this->eval = $eval;
        $this->printer = $printer;
    }

    public function rep(string $input, Env $env): void
    {
        $tokens = $this->tokenizer->tokenize($input);

        $expressions = $this->reader->read($tokens);

        $results = $this->eval->eval($expressions, $env);

        $this->printer->print($results);
    }
}
