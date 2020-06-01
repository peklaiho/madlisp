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

    public function register(Env $env): void
    {
        $env->set('doc', new CoreFunc('doc', 'Get documentation for a function.', 1, 1,
            function ($a) {
                if ($a instanceof Func) {
                    return $a->getDoc();
                }

                throw new MadLispException('first argument to doc is not function');
            }
        ));

        $env->set('eval', new CoreFunc('eval', 'Evaluate arguments.', 1, -1,
            function (...$args) use ($env) {
                $results = $this->eval->eval($args, $env);

                // Return last evaluated value
                return $results[count($results) - 1];
            }
        ));

        $env->set('print', new CoreFunc('print', 'Print arguments.', 1, -1,
            function (...$args) {
                $this->printer->print($args);

                return null;
            }
        ));
    }
}
