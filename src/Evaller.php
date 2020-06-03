<?php
namespace MadLisp;

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
            return new MList(array_map(fn ($a) => $this->doEval($a, $env), $ast->getData()));
        } elseif ($ast instanceof Vector) {
            return new Vector(array_map(fn ($a) => $this->doEval($a, $env), $ast->getData()));
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
        // Not list or empty list
        if (!($ast instanceof MList)) {
            return $this->evalAst($ast, $env);
        } elseif ($ast->count() == 0) {
            return $ast;
        }

        // Handle special keywords
        if ($ast->get(0) instanceof Symbol) {
            if ($ast->get(0)->getName() == 'def') {
                if ($ast->count() != 3) {
                    throw new MadLispException("def requires exactly 2 arguments");
                }

                if (!($ast->get(1) instanceof Symbol)) {
                    throw new MadLispException("first argument to def is not symbol");
                }

                $value = $this->doEval($ast->get(2), $env);
                return $env->set($ast->get(1)->getName(), $value);
            } elseif ($ast->get(0)->getName() == 'do') {
                if ($ast->count() < 2) {
                    throw new MadLispException("do requires at least 1 argument");
                }

                for ($i = 1; $i < $ast->count(); $i++) {
                    $value = $this->evalAst($ast->get($i), $env);
                }

                return $value;
            } elseif ($ast->get(0)->getName() == 'env') {
                return $env;
            } elseif ($ast->get(0)->getName() == 'fn') {
                if ($ast->count() != 3) {
                    throw new MadLispException("fn requires exactly 2 arguments");
                }

                if (!($ast->get(1) instanceof MList)) {
                    throw new MadLispException("first argument to fn is not list");
                }

                $bindings = $ast->get(1)->getData();
                foreach ($bindings as $bind) {
                    if (!($bind instanceof Symbol)) {
                        throw new MadLispException("binding key for fn is not symbol");
                    }
                }

                return new UserFunc(function (...$args) use ($bindings, $ast, $env) {
                    $newEnv = new Env($env);

                    for ($i = 0; $i < count($bindings); $i++) {
                        $newEnv->set($bindings[$i]->getName(), $args[$i] ?? null);
                    }

                    return $this->doEval($ast->get(2), $newEnv);
                });
            } elseif ($ast->get(0)->getName() == 'if') {
                if ($ast->count() < 3 || $ast->count() > 4) {
                    throw new MadLispException("if requires 2 or 3 arguments");
                }

                $result = $this->doEval($ast->get(1), $env);

                if ($result == true) {
                    return $this->doEval($ast->get(2), $env);
                } elseif ($ast->count() == 4) {
                    return $this->doEval($ast->get(3), $env);
                } else {
                    return null;
                }
            } elseif ($ast->get(0)->getName() == 'let') {
                if ($ast->count() != 3) {
                    throw new MadLispException("let requires exactly 2 arguments");
                }

                if (!($ast->get(1) instanceof MList)) {
                    throw new MadLispException("first argument to let is not list");
                }

                $bindings = $ast->get(1)->getData();

                if (count($bindings) % 2 == 1) {
                    throw new MadLispException("uneven number of bindings for let");
                }

                $newEnv = new Env($env);

                for ($i = 0; $i < count($bindings) - 1; $i += 2) {
                    $key = $bindings[$i];

                    if (!($key instanceof Symbol)) {
                        throw new MadLispException("binding key for let is not symbol");
                    }

                    $val = $this->doEval($bindings[$i + 1], $newEnv);
                    $newEnv->set($key->getName(), $val);
                }

                return $this->doEval($ast->get(2), $newEnv);
            } elseif ($ast->get(0)->getName() == 'quote') {
                if ($ast->count() != 2) {
                    throw new MadLispException("quote requires exactly 1 argument");
                }

                return $ast->get(1);
            }
        }

        // Get new evaluated list
        $ast = $this->evalAst($ast, $env);

        // Call first argument as function
        $func = $ast->get(0);
        if (!($func instanceof Func)) {
            throw new MadLispException("first item of list is not function");
        }
        $args = array_slice($ast->getData(), 1);
        return $func->call($args);
    }
}
