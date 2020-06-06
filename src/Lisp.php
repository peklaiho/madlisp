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

    public function re(string $input, Env $env)
    {
        $tokens = $this->tokenizer->tokenize($input);

        $expr = $this->reader->read($tokens);

        return $this->eval->eval($expr, $env);
    }

    public function rep(string $input, Env $env): void
    {
        $result = $this->re($input, $env);

        $this->printer->print($result);
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

        $env->set('eval', new CoreFunc('eval', 'Evaluate argument.', 1, 1,
            fn ($a) => $this->eval->eval($a, $env)
        ));

        $env->set('print', new CoreFunc('print', 'Print argument.', 1, 1,
            function ($a) {
                $this->printer->print($a);
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
