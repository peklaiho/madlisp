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

    public function rep(string $input, Env $env, bool $printReadable): void
    {
        $tokens = $this->tokenizer->tokenize($input);

        $expr = $this->reader->read($tokens);

        $result = $this->eval->eval($expr, $env);

        $this->printer->print($result, $printReadable);
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

        $env->set('read', new CoreFunc('read', 'Read string as code.', 1, 1,
            fn (string $a) => $this->reader->read($this->tokenizer->tokenize($a))
        ));

        $env->set('print', new CoreFunc('print', 'Print argument. Give second argument as true to show strings in readable format.', 1, 2,
            function ($a, bool $readable = false) {
                $this->printer->print($a, $readable);
                return null;
            }
        ));

        $env->set('error', new CoreFunc('error', 'Throw an exception using argument (string) as message.', 1, 1,
            function (string $error) {
                throw new MadLispException($error);
            }
        ));
    }
}
