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
            $results = [];
            foreach ($ast->getData() as $val) {
                $results[] = $this->doEval($val, $env);
            }
            return new MList($results);
        } elseif ($ast instanceof Hash) {
            $results = [];
            foreach ($ast->getData() as $key => $val) {
                $results[$key] = $this->doEval($val, $env);
            }
            return new Hash($results);
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

        $first = $ast->get(0);

        // Handle special keywords
        if ($first instanceof Symbol) {
            if ($first->getName() == 'quote') {
                if ($ast->count() != 2) {
                    throw new MadLispException("quote requires exactly 1 argument");
                }

                return $ast->get(1);
            }
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
