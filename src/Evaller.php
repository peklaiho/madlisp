<?php
namespace MadLisp;

class Evaller
{
    private $p = null;

    public function eval($ast, Env $env)
    {
        // Debug
        if ($this->p == null) {
            $this->p = new Printer();
        }
        print("eval: ");
        $this->p->print($ast);
        print("\n");

        while (true) {

            // Not list or empty list
            if (!($ast instanceof MList)) {
                return $this->evalAst($ast, $env);
            } elseif ($ast->count() == 0) {
                return $ast;
            }

            // Handle special forms
            if ($ast->get(0) instanceof Symbol) {
                if ($ast->get(0)->getName() == 'and') {
                    if ($ast->count() == 1) {
                        return true;
                    }

                    for ($i = 1; $i < $ast->count() - 1; $i++) {
                        $value = $this->eval($ast->get($i), $env);
                        if ($value == false) {
                            return $value;
                        }
                    }

                    $ast = $ast->get($ast->count() - 1);
                    continue; // tco
                } elseif ($ast->get(0)->getName() == 'case') {
                    if ($ast->count() < 2) {
                        throw new MadLispException("case requires at least 1 argument");
                    }

                    for ($i = 1; $i < $ast->count() - 1; $i += 2) {
                        $test = $this->eval($ast->get($i), $env);
                        if ($test == true) {
                            return $this->eval($ast->get($i + 1), $env);
                        }
                    }

                    // Last value, no test
                    if ($ast->count() % 2 == 0) {
                        return $this->eval($ast->get($ast->count() - 1), $env);
                    } else {
                        return null;
                    }
                } elseif ($ast->get(0)->getName() == 'def') {
                    if ($ast->count() != 3) {
                        throw new MadLispException("def requires exactly 2 arguments");
                    }

                    if (!($ast->get(1) instanceof Symbol)) {
                        throw new MadLispException("first argument to def is not symbol");
                    }

                    $value = $this->eval($ast->get(2), $env);
                    return $env->set($ast->get(1)->getName(), $value);
                } elseif ($ast->get(0)->getName() == 'do') {
                    if ($ast->count() < 2) {
                        throw new MadLispException("do requires at least 1 argument");
                    }

                    for ($i = 1; $i < $ast->count() - 1; $i++) {
                        $this->eval($ast->get($i), $env);
                    }

                    $ast = $ast->get($ast->count() - 1);
                    continue; // tco
                } elseif ($ast->get(0)->getName() == 'env') {
                    if ($ast->count() >= 2) {
                        if (!($ast->get(1) instanceof Symbol)) {
                            throw new MadLispException("first argument to env is not symbol");
                        }

                        return $env->get($ast->get(1)->getName());
                    } else {
                        return $env;
                    }
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

                        return $this->eval($ast->get(2), $newEnv);
                    });
                } elseif ($ast->get(0)->getName() == 'if') {
                    if ($ast->count() < 3 || $ast->count() > 4) {
                        throw new MadLispException("if requires 2 or 3 arguments");
                    }

                    $result = $this->eval($ast->get(1), $env);

                    if ($result == true) {
                        echo "if tco\n";
                        $ast = $ast->get(2);
                        continue;
                    } elseif ($ast->count() == 4) {
                        echo "if tco\n";
                        $ast = $ast->get(3);
                        continue;
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

                        $val = $this->eval($bindings[$i + 1], $newEnv);
                        $newEnv->set($key->getName(), $val);
                    }

                    $ast = $ast->get(2);
                    $env = $newEnv;
                    continue; // tco
                } elseif ($ast->get(0)->getName() == 'or') {
                    if ($ast->count() == 1) {
                        return false;
                    }

                    for ($i = 1; $i < $ast->count() - 1; $i++) {
                        $value = $this->eval($ast->get($i), $env);
                        if ($value == true) {
                            return $value;
                        }
                    }

                    $ast = $ast->get($ast->count() - 1);
                    continue; // tco
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
                throw new MadLispException("eval: first item of list is not function");
            }

            $args = array_slice($ast->getData(), 1);
            return $func->call($args);
        }
    }

    private function evalAst($ast, Env $env)
    {
        if ($ast instanceof Symbol) {
            // Lookup symbol from env
            return $env->get($ast->getName());
        } elseif ($ast instanceof MList) {
            return new MList(array_map(fn ($a) => $this->eval($a, $env), $ast->getData()));
        } elseif ($ast instanceof Vector) {
            return new Vector(array_map(fn ($a) => $this->eval($a, $env), $ast->getData()));
        } elseif ($ast instanceof Hash) {
            $results = [];
            foreach ($ast->getData() as $key => $val) {
                $results[$key] = $this->eval($val, $env);
            }
            return new Hash($results);
        }

        return $ast;
    }
}
