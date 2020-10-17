<?php
namespace MadLisp;

class LispFactory
{
    public function make(array $coreLibs = [], array $userLibs = []): Lisp
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $printer = new Printer();
        $eval = new Evaller($tokenizer, $reader, $printer);

        // Root environment
        $env = new Env('root');

        // Register core functions
        $env->set('doc', new CoreFunc('doc', 'Get documentation for a function.', 1, 1,
            function ($a) {
                if ($a instanceof Func) {
                    return $a->getDoc();
                }

                throw new MadLispException('first argument to doc is not function');
            }
        ));

        $env->set('read', new CoreFunc('read', 'Read string as code.', 1, 1,
            fn (string $a) => $reader->read($tokenizer->tokenize($a))
        ));

        $env->set('print', new CoreFunc('print', 'Print argument. Give second argument as true to show strings in readable format.', 1, 2,
            function ($a, bool $readable = false) use ($printer) {
                $printer->print($a, $readable);
                return null;
            }
        ));

        $env->set('error', new CoreFunc('error', 'Throw an exception using argument (string) as message.', 1, 1,
            function (string $error) {
                throw new MadLispException($error);
            }
        ));

        $env->set('exit', new CoreFunc('exit', 'Terminate the script with given exit code.', 0, 1,
            function ($status = 0) {
                exit($status);
            }
        ));

        // Register core libraries
        (new Lib\Collections())->register($env);
        (new Lib\Compare())->register($env);
        (new Lib\Database())->register($env);
        (new Lib\Http())->register($env);
        (new Lib\IO())->register($env);
        (new Lib\Json())->register($env);
        (new Lib\Math())->register($env);
        (new Lib\Strings())->register($env);
        (new Lib\Time())->register($env);
        (new Lib\Types())->register($env);

        // Register additional libs for root env
        foreach ($coreLibs as $lib) {
            $lib->register($env);
        }

        // User environment
        $env = new Env('user', $env);

        // Register additional libs for user env
        foreach ($userLibs as $lib) {
            $lib->register($env);
        }

        return new Lisp($tokenizer, $reader, $eval, $printer, $env);
    }
}
