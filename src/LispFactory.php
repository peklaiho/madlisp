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
        (new Lib\Core($tokenizer, $reader, $printer, $eval))->register($env);

        // Register core libraries
        (new Lib\Collections())->register($env);
        (new Lib\Compare())->register($env);
        (new Lib\Database())->register($env);
        (new Lib\Http())->register($env);
        (new Lib\IO())->register($env);
        (new Lib\Json())->register($env);
        (new Lib\Math())->register($env);
        (new Lib\Regex())->register($env);
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
