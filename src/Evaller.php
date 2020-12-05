<?php
namespace MadLisp;

class Evaller
{
    protected Tokenizer $tokenizer;
    protected Reader $reader;
    protected Printer $printer;

    protected bool $debug = false;

    public function __construct(Tokenizer $tokenizer, Reader $reader, Printer $printer)
    {
        $this->tokenizer = $tokenizer;
        $this->reader = $reader;
        $this->printer = $printer;
    }

    public function eval($ast, Env $env)
    {
        if ($this->debug) {
            print("eval: ");
            $this->printer->print($ast);
            print("\n");
            $loops = 0;
        }

        while (true) {

            if ($this->debug) {
                if ($loops++ > 0) {
                    print("eval loop: ");
                    $this->printer->print($ast);
                    print("\n");
                }
            }

            // Not list
            if (!($ast instanceof MList)) {
                return $this->evalAst($ast, $env);
            }

            $astData = $ast->getData();
            $astLength = count($astData);

            // Empty list
            if ($astLength == 0) {
                return $ast;
            }

            // Handle special forms
            if ($astData[0] instanceof Symbol) {
                $symbolName = $astData[0]->getName();

                if ($symbolName == 'and') {
                    if ($astLength == 1) {
                        return true;
                    }

                    for ($i = 1; $i < $astLength - 1; $i++) {
                        $value = $this->eval($astData[$i], $env);
                        if ($value == false) {
                            return $value;
                        }
                    }

                    $ast = $astData[$astLength - 1];
                    continue; // tco
                } elseif ($symbolName == 'case') {
                    if ($astLength < 2) {
                        throw new MadLispException("case requires at least 1 argument");
                    }

                    for ($i = 1; $i < $astLength - 1; $i += 2) {
                        $test = $this->eval($astData[$i], $env);
                        if ($test == true) {
                            $ast = $astData[$i + 1];
                            continue 2; // tco
                        }
                    }

                    // Last value, no test
                    if ($astLength % 2 == 0) {
                        $ast = $astData[$astLength - 1];
                        continue; // tco
                    } else {
                        return null;
                    }
                } elseif ($symbolName == 'def') {
                    if ($astLength != 3) {
                        throw new MadLispException("def requires exactly 2 arguments");
                    }

                    if (!($astData[1] instanceof Symbol)) {
                        throw new MadLispException("first argument to def is not symbol");
                    }

                    $name = $astData[1]->getName();

                    // Do not allow reserved symbols to be defined
                    $reservedSymbols = ['__FILE__', '__DIR__'];
                    if (in_array($name, $reservedSymbols)) {
                        throw new MadLispException("def reserved symbol $name");
                    }

                    $value = $this->eval($astData[2], $env);
                    return $env->set($name, $value);
                } elseif ($symbolName == 'do') {
                    if ($astLength == 1) {
                        return null;
                    }

                    for ($i = 1; $i < $astLength - 1; $i++) {
                        $this->eval($astData[$i], $env);
                    }

                    $ast = $astData[$astLength - 1];
                    continue; // tco
                } elseif ($symbolName == 'env') {
                    if ($astLength >= 2) {
                        if (!($astData[1] instanceof Symbol)) {
                            throw new MadLispException("first argument to env is not symbol");
                        }

                        return $env->get($astData[1]->getName());
                    } else {
                        return $env;
                    }
                } elseif ($symbolName == 'eval') {
                    if ($astLength == 1) {
                        return null;
                    }

                    $ast = $this->eval($astData[1], $env);
                    continue; // tco
                } elseif ($symbolName == 'fn') {
                    if ($astLength != 3) {
                        throw new MadLispException("fn requires exactly 2 arguments");
                    }

                    if (!($astData[1] instanceof MList)) {
                        throw new MadLispException("first argument to fn is not list");
                    }

                    $bindings = $astData[1]->getData();
                    foreach ($bindings as $bind) {
                        if (!($bind instanceof Symbol)) {
                            throw new MadLispException("binding key for fn is not symbol");
                        }
                    }

                    $closure = function (...$args) use ($bindings, $ast, $env) {
                        $newEnv = new Env('closure', $env);

                        for ($i = 0; $i < count($bindings); $i++) {
                            $newEnv->set($bindings[$i]->getName(), $args[$i] ?? null);
                        }

                        return $this->eval($astData[2], $newEnv);
                    };

                    return new UserFunc($closure, $astData[2], $env, $astData[1]);
                } elseif ($symbolName == 'if') {
                    if ($astLength < 3 || $astLength > 4) {
                        throw new MadLispException("if requires 2 or 3 arguments");
                    }

                    $result = $this->eval($astData[1], $env);

                    if ($result == true) {
                        $ast = $astData[2];
                        continue;
                    } elseif ($astLength == 4) {
                        $ast = $astData[3];
                        continue;
                    } else {
                        return null;
                    }
                } elseif ($symbolName == 'let') {
                    if ($astLength != 3) {
                        throw new MadLispException("let requires exactly 2 arguments");
                    }

                    if (!($astData[1] instanceof MList)) {
                        throw new MadLispException("first argument to let is not list");
                    }

                    $bindings = $astData[1]->getData();

                    if (count($bindings) % 2 == 1) {
                        throw new MadLispException("uneven number of bindings for let");
                    }

                    $newEnv = new Env('let', $env);

                    for ($i = 0; $i < count($bindings) - 1; $i += 2) {
                        $key = $bindings[$i];

                        if (!($key instanceof Symbol)) {
                            throw new MadLispException("binding key for let is not symbol");
                        }

                        $val = $this->eval($bindings[$i + 1], $newEnv);
                        $newEnv->set($key->getName(), $val);
                    }

                    $ast = $astData[2];
                    $env = $newEnv;
                    continue; // tco
                } elseif ($symbolName == 'load') {
                    // Load is here because we want to load into
                    // current $env which is hard otherwise.

                    if ($astLength != 2) {
                        throw new MadLispException("load requires exactly 1 argument");
                    }

                    // We have to evaluate the argument, it could be a function
                    $filename = $this->eval($astData[1], $env);

                    if (!is_string($filename)) {
                        throw new MadLispException("first argument to load is not string");
                    }

                    // Replace ~ with user home directory
                    // Expand relative path names into absolute
                    $targetFile = realpath(str_replace('~', $_SERVER['HOME'], $filename));

                    if (!$targetFile || !is_readable($targetFile)) {
                        throw new MadLispException("unable to read file $filename");
                    }

                    $input = @file_get_contents($targetFile);

                    // Wrap input in a do to process multiple expressions
                    $input = "(do $input)";

                    $expr = $this->reader->read($this->tokenizer->tokenize($input));

                    // Handle special constants
                    $rootEnv = $env->getRoot();
                    $prevFile = $rootEnv->get('__FILE__');
                    $prevDir = $rootEnv->get('__DIR__');
                    $rootEnv->set('__FILE__', $targetFile);
                    $rootEnv->set('__DIR__', dirname($targetFile) . \DIRECTORY_SEPARATOR);

                    // Evaluate the contents
                    $ast = $this->eval($expr, $env);

                    // Restore the special constants to previous values
                    $rootEnv->set('__FILE__', $prevFile);
                    $rootEnv->set('__DIR__', $prevDir);

                    continue; // tco
                } elseif ($symbolName == 'or') {
                    if ($astLength == 1) {
                        return false;
                    }

                    for ($i = 1; $i < $astLength - 1; $i++) {
                        $value = $this->eval($astData[$i], $env);
                        if ($value == true) {
                            return $value;
                        }
                    }

                    $ast = $astData[$astLength - 1];
                    continue; // tco
                } elseif ($symbolName == 'quote') {
                    if ($astLength != 2) {
                        throw new MadLispException("quote requires exactly 1 argument");
                    }

                    return $astData[1];
                }
            }

            // Get new evaluated list
            $ast = $this->evalAst($ast, $env);
            $astData = $ast->getData();

            // First item is function, rest are arguments
            $func = $astData[0];
            $args = array_slice($astData, 1);

            if ($func instanceof CoreFunc) {
                return $func->call($args);
            } elseif ($func instanceof UserFunc) {
                $ast = $func->getAst();
                $env = $func->getEnv($args);
            } else {
                throw new MadLispException("eval: first item of list is not function");
            }
        }
    }

    public function getDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $val): void
    {
        $this->debug = $val;
    }

    private function evalAst($ast, Env $env)
    {
        if ($ast instanceof Symbol) {
            // Lookup symbol from env
            return $env->get($ast->getName());
        } elseif ($ast instanceof Seq) {
            $results = [];
            foreach ($ast->getData() as $val) {
                $results[] = $this->eval($val, $env);
            }
            return $ast::new($results);
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
