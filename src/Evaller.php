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

    public function doEval($expr, Env $env)
    {
        if ($expr instanceof MList && $expr->count() > 0) {
            $first = $expr->get(0);

            if ($first instanceof Symbol) {
                // Special built-in features
                if ($first->getName() == 'env') {
                    return $env;
                } elseif ($first->getName() == 'quote') {
                    if ($expr->count() != 2) {
                        throw new MadLispException("quote requires exactly 1 argument");
                    }

                    return $expr->get(1);
                } elseif ($first->getName() == 'if') {
                    if ($expr->count() != 4) {
                        throw new MadLispException("if requires exactly 3 arguments");
                    }

                    // Eval condition
                    $result = $this->doEval($expr->get(1), $env);

                    // Eval true or false branch and return it
                    if ($result == true) {
                        return $this->doEval($expr->get(2), $env);
                    } else {
                        return $this->doEval($expr->get(3), $env);
                    }
                }

                // Normal symbol, fetch from env
                $first = $env->get($first->getName());
            }

            if (!($first instanceof Closure)) {
                throw new MadLispException("first argument of list is not function");
            }

            $args = array_slice($expr->getData(), 1);

            // Evaluate args
            $args = array_map(fn ($a) => $this->doEval($a, $env), $args);

            // Call func and return result
            return $first(...$args);
        } elseif ($expr instanceof Hash) {
            // Hash: return new hash with all items evaluated
            $items = [];
            foreach ($expr->getData() as $key => $val) {
                $items[$key] = $this->doEval($val, $env);
            }
            return new Hash($items);
        } elseif ($expr instanceof Symbol) {
            return $env->get($expr->getName());
        }

        // Return the expression unchanged
        return $expr;
    }
}
