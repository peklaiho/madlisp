<?php
namespace MadLisp;

use Closure;

class Evaller
{
    public function eval(array $expressions, Env $env): array
    {
        $results = [];

        foreach ($expressions as $expr) {
            $results[] = $this->doEval($expr, $env);
        }

        return $results;
    }

    public function evalAst($ast, Env $env)
    {
        if ($ast instanceof Symbol) {
            // Lookup symbol from env
            return $env->get($ast->getName());
        } elseif ($ast instanceof MList) {
            // Eval contents and return new list
            $results = array_map(fn ($a) => $this->doEval($a, $env), $ast->getData());
            return new MList($results);
        }

        return $ast;
    }

    public function doEval($ast, Env $env)
    {
        // Not list
        if (!($ast instanceof MList)) {
            return $this->evalAst($ast, $env);
        }

        // Empty list
        if ($ast->count() == 0) {
            return $ast;
        }

        // Get new evaluated list
        $ast = $this->evalAst($ast, $env);

        $first = $ast->get(0);

        if (!($first instanceof Closure)) {
            throw new MadLispException("first item of list is not function");
        }

        $args = array_slice($ast->getData(), 1);

        return $first(...$args);
    }
}
