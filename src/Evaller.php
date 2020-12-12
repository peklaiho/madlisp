<?php
namespace MadLisp;

class Evaller
{
    protected Tokenizer $tokenizer;
    protected Reader $reader;
    protected Printer $printer;
    protected bool $safemode;

    protected bool $debug = false;

    // Keep cache of macro names so we can skip
    // macro expansion when possible.
    protected array $macros = [];

    public function __construct(Tokenizer $tokenizer, Reader $reader, Printer $printer, bool $safemode)
    {
        $this->tokenizer = $tokenizer;
        $this->reader = $reader;
        $this->printer = $printer;
        $this->safemode = $safemode;
    }

    public function eval($ast, Env $env, int $depth = 1)
    {
        // Loop for tail call optimization
        $isTco = false;
        while (true) {

            // Show debug output
            if ($this->debug) {
                printf("%s %2d : ", $isTco ? ' tco' : 'eval', $depth);
                $this->printer->print($ast, true);
                print("\n");
                $isTco = true;
            }

            // Return here after macro expansion
            $expandMacros = true;
            beginning:

            // Handle response for anything but a list
            if ($ast instanceof Symbol) {
                return $env->get($ast->getName());
            } elseif ($ast instanceof Vector || $ast instanceof Hash) {
                $newData = [];
                foreach ($ast->getData() as $key => $val) {
                    if ($val instanceof Symbol) {
                        $newData[$key] = $env->get($val->getName());
                    } elseif ($val instanceof Collection) {
                        $newData[$key] = $this->eval($val, $env, $depth + 1);
                    } else {
                        $newData[$key] = $val;
                    }
                }
                return $ast::new($newData);
            } elseif ($ast instanceof MList == false) {
                return $ast;
            }

            $astData = $ast->getData();
            $astLength = count($astData);

            // Empty list, we can return
            if ($astLength == 0) {
                return $ast;
            }

            // Handle special forms
            if ($astData[0] instanceof Symbol) {
                $symbolName = $astData[0]->getName();

                // Handle macro expansion and go back to beginning to check
                // if ast is still something we need to evaluate or not.
                if ($expandMacros && array_key_exists($symbolName, $this->macros)) {
                    $ast = $this->macroexpand($ast, $env);
                    $expandMacros = false;
                    goto beginning;
                }

                if ($symbolName == 'and') {
                    if ($astLength == 1) {
                        return true;
                    }

                    for ($i = 1; $i < $astLength - 1; $i++) {
                        $value = $this->eval($astData[$i], $env, $depth + 1);
                        if ($value == false) {
                            return $value;
                        }
                    }

                    $ast = $astData[$astLength - 1];
                    continue; // tco
                } elseif ($symbolName == 'case' || $symbolName == 'case-strict') {
                    if ($astLength < 3) {
                        throw new MadLispException("$symbolName requires at least 2 arguments");
                    }

                    $value = $this->eval($astData[1], $env, $depth + 1);

                    for ($i = 2; $i < $astLength; $i++) {
                        if (!($astData[$i] instanceof Seq)) {
                            throw new MadLispException("argument to $symbolName is not seq");
                        }

                        $data = $astData[$i]->getData();

                        if (count($data) < 2) {
                            throw new MadLispException("clause for $symbolName requires at least 2 arguments");
                        }

                        if ($data[0] instanceof Symbol && $data[0]->getName() == 'else') {
                            $test = true;
                        } elseif ($symbolName == 'case') {
                            $test = Util::valueForCompare($value) == Util::valueForCompare($data[0]);
                        } else {
                            // Strict comparison
                            $test = Util::valueForCompare($value) === Util::valueForCompare($data[0]);
                        }

                        if ($test) {
                            // Evaluate interval expressions
                            for ($j = 1; $j < count($data) - 1; $j++) {
                                $this->eval($data[$j], $env, $depth + 1);
                            }

                            // Evaluate last expression
                            $ast = $data[count($data) - 1];
                            continue 2; // tco
                        }
                    }

                    // No match
                    return null;
                } elseif ($symbolName == 'cond') {
                    if ($astLength < 2) {
                        throw new MadLispException("cond requires at least 1 argument");
                    }

                    for ($i = 1; $i < $astLength; $i++) {
                        if (!($astData[$i] instanceof Seq)) {
                            throw new MadLispException("argument to cond is not seq");
                        }

                        $data = $astData[$i]->getData();

                        if (count($data) < 2) {
                            throw new MadLispException("clause for cond requires at least 2 arguments");
                        }

                        if ($data[0] instanceof Symbol && $data[0]->getName() == 'else') {
                            $test = true;
                        } else {
                            $test = $this->eval($data[0], $env, $depth + 1);
                        }

                        if ($test) {
                            // Evaluate interval expressions
                            for ($j = 1; $j < count($data) - 1; $j++) {
                                $this->eval($data[$j], $env, $depth + 1);
                            }

                            // Evaluate last expression
                            $ast = $data[count($data) - 1];
                            continue 2; // tco
                        }
                    }

                    // No match
                    return null;
                } elseif ($symbolName == 'def') {
                    if ($astLength != 3) {
                        throw new MadLispException("def requires exactly 2 arguments");
                    } elseif (!($astData[1] instanceof Symbol)) {
                        throw new MadLispException("first argument to def is not symbol");
                    }

                    $name = $astData[1]->getName();

                    // Do not allow reserved symbols to be defined
                    $reservedSymbols = ['__FILE__', '__DIR__'];
                    if (in_array($name, $reservedSymbols)) {
                        throw new MadLispException("def reserved symbol $name");
                    }

                    $value = $this->eval($astData[2], $env, $depth + 1);

                    // Save macros in cache
                    if ($value instanceof Func && $value->isMacro()) {
                        // value does not matter, we check for key
                        $this->macros[$name] = 1;
                    }

                    return $env->set($name, $value);
                } elseif ($symbolName == 'do') {
                    if ($astLength == 1) {
                        return null;
                    }

                    for ($i = 1; $i < $astLength - 1; $i++) {
                        $this->eval($astData[$i], $env, $depth + 1);
                    }

                    $ast = $astData[$astLength - 1];
                    continue; // tco
                } elseif ($symbolName == 'env') {
                    if ($astLength != 1) {
                        throw new MadLispException("env does not take arguments");
                    }

                    return $env;
                } elseif ($symbolName == 'eval') {
                    if ($astLength == 1) {
                        return null;
                    }

                    $ast = $this->eval($astData[1], $env, $depth + 1);
                    continue; // tco
                } elseif ($symbolName == 'fn' || $symbolName == 'macro') {
                    if ($astLength != 3) {
                        throw new MadLispException("$symbolName requires exactly 2 arguments");
                    } elseif (!($astData[1] instanceof Seq)) {
                        throw new MadLispException("first argument to $symbolName is not seq");
                    }

                    $bindings = $astData[1]->getData();
                    foreach ($bindings as $bind) {
                        if (!($bind instanceof Symbol)) {
                            throw new MadLispException("binding key for $symbolName is not symbol");
                        }
                    }

                    $closure = function (...$args) use ($bindings, $env, $astData, $depth) {
                        $newEnv = new Env('closure', $env);

                        for ($i = 0; $i < count($bindings); $i++) {
                            $newEnv->set($bindings[$i]->getName(), $args[$i] ?? null);
                        }

                        return $this->eval($astData[2], $newEnv, $depth + 1);
                    };

                    return new UserFunc($closure, $astData[2], $env, $astData[1], $symbolName == 'macro');
                } elseif ($symbolName == 'if') {
                    if ($astLength < 3 || $astLength > 4) {
                        throw new MadLispException("if requires 2 or 3 arguments");
                    }

                    $result = $this->eval($astData[1], $env, $depth + 1);

                    if ($result == true) {
                        $ast = $astData[2];
                        continue; // tco
                    } elseif ($astLength == 4) {
                        $ast = $astData[3];
                        continue; // tco
                    } else {
                        return null;
                    }
                } elseif ($symbolName == 'let') {
                    if ($astLength < 3) {
                        throw new MadLispException("let requires at least 2 arguments");
                    } elseif (!($astData[1] instanceof Seq)) {
                        throw new MadLispException("first argument to let is not seq");
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

                        $val = $this->eval($bindings[$i + 1], $newEnv, $depth + 1);
                        $newEnv->set($key->getName(), $val);
                    }

                    // Eval interval expressions
                    for ($i = 2; $i < $astLength - 1; $i++) {
                        $this->eval($astData[$i], $newEnv, $depth + 1);
                    }

                    // Eval last expression
                    $ast = $astData[$astLength - 1];
                    $env = $newEnv;
                    continue; // tco
                } elseif (!$this->safemode && $symbolName == 'load') {
                    // Load is here because we want to load into
                    // current $env which is hard otherwise.

                    // This is disabled now for safe-mode, but some
                    // use (maybe restricted) might need to be allowed.

                    if ($astLength != 2) {
                        throw new MadLispException("load requires exactly 1 argument");
                    }

                    // We have to evaluate the argument, it could be a function
                    $filename = $this->eval($astData[1], $env, $depth + 1);

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
                    $ast = $this->eval($expr, $env, $depth + 1);

                    // Restore the special constants to previous values
                    $rootEnv->set('__FILE__', $prevFile);
                    $rootEnv->set('__DIR__', $prevDir);

                    continue; // tco
                } elseif ($symbolName == 'macroexpand') {
                    if ($astLength != 2) {
                        throw new MadLispException("macroexpand requires exactly 1 argument");
                    }

                    return $this->macroexpand($astData[1], $env);
                } elseif ($symbolName == 'meta') {
                    if ($astLength != 3) {
                        throw new MadLispException("meta requires exactly 2 arguments");
                    } elseif (!is_string($astData[2])) {
                        throw new MadLispException("third argument to meta is not string");
                    }

                    $obj = $this->eval($astData[1], $env, $depth + 1);
                    $attribute = $astData[2];

                    if ($obj instanceof Env) {
                        if ($attribute == 'name') {
                            return $obj->getFullName();
                        } elseif ($attribute == 'parent') {
                            return $obj->getParent();
                        } else {
                            throw new MadLispException('unknown attribute for meta');
                        }
                    } elseif ($obj instanceof UserFunc) {
                        if ($attribute == 'args') {
                            return $obj->getBindings();
                        } elseif ($attribute == 'body') {
                            return $obj->getAst();
                        } elseif ($attribute == 'code') {
                            $name = $obj->isMacro() ? 'macro' : 'fn';
                            return new MList([new Symbol($name), $obj->getBindings(), $obj->getAst()]);
                        } elseif ($attribute == 'def') {
                            if ($astData[1] instanceof Symbol) {
                                $def = $obj->isMacro() ? 'defmacro' : 'defn';
                                return new MList([new Symbol($def), $astData[1], $obj->getBindings(), $obj->getAst()]);
                            } else {
                                throw new MadLispException('no name for def in meta');
                            }
                        } else {
                            throw new MadLispException('unknown attribute for meta');
                        }
                    } else {
                        throw new MadLispException('unknown entity for meta');
                    }
                } elseif ($symbolName == 'or') {
                    if ($astLength == 1) {
                        return false;
                    }

                    for ($i = 1; $i < $astLength - 1; $i++) {
                        $value = $this->eval($astData[$i], $env, $depth + 1);
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
                } elseif ($symbolName == 'quasiquote') {
                    if ($astLength != 2) {
                        throw new MadLispException("quasiquote requires exactly 1 argument");
                    }

                    $ast = $this->quasiquote($astData[1]);
                    continue; // tco
                } elseif ($symbolName == 'quasiquote-expand') {
                    if ($astLength != 2) {
                        throw new MadLispException("quasiquote-expand requires exactly 1 argument");
                    }

                    return $this->quasiquote($astData[1]);
                } elseif ($symbolName == 'try') {
                    if ($astLength != 3) {
                        throw new MadLispException("try requires exactly 2 arguments");
                    } elseif (!($astData[2] instanceof Seq)) {
                        throw new MadLispException("second argument to try is not seq");
                    }

                    $catch = $astData[2]->getData();

                    if (count($catch) == 3 && $catch[0] instanceof Symbol &&
                        $catch[0]->getName() == 'catch' && $catch[1] instanceof Symbol) {

                        try {
                            return $this->eval($astData[1], $env, $depth + 1);
                        } catch (\Throwable $ex) {
                            if ($ex instanceof MadLispUserException) {
                                $exVal = $ex->getValue();
                            } else {
                                // Return a hash-map for PHP exceptions
                                $exVal = new Hash([
                                    'type' => get_class($ex),
                                    'file' => $ex->getFile(),
                                    'line' => $ex->getLine(),
                                    'message' => $ex->getMessage()
                                ]);
                            }

                            $newEnv = new Env('catch', $env);
                            $newEnv->set($catch[1]->getName(), $exVal);

                            $env = $newEnv;
                            $ast = $catch[2];
                            continue; // tco
                        }
                    } else {
                        throw new MadLispException("invalid form for catch");
                    }
                } elseif ($symbolName == 'undef') {
                    if ($astLength != 2) {
                        throw new MadLispException("undef requires exactly 1 argument");
                    } elseif (!($astData[1] instanceof Symbol)) {
                        throw new MadLispException("first argument to undef is not symbol");
                    }

                    return $env->unset($astData[1]->getName());
                } elseif ($symbolName == 'while') {
                    if ($astLength < 3) {
                        throw new MadLispException("while at least 2 arguments");
                    }

                    $result = null;

                    $test = $this->eval($astData[1], $env, $depth + 1);
                    while ($test) {
                        for ($i = 2; $i < $astLength; $i++) {
                            $result = $this->eval($astData[$i], $env, $depth + 1);
                        }
                        $test = $this->eval($astData[1], $env, $depth + 1);
                    }

                    return $result;
                }
            }

            // Eval all items in list
            $newData = [];
            foreach ($astData as $a) {
                if ($a instanceof Symbol) {
                    $newData[] = $env->get($a->getName());
                } elseif ($a instanceof Collection) {
                    $newData[] = $this->eval($a, $env, $depth + 1);
                } else {
                    $newData[] = $a;
                }
            }

            // First item is function, rest are arguments
            $func = $newData[0];
            $args = array_slice($newData, 1);

            if ($func instanceof CoreFunc) {
                return $func->call($args);
            } elseif ($func instanceof UserFunc) {
                $ast = $func->getAst();
                $env = $func->getEnv($args);
                // tco
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

    private function macroexpand($ast, Env $env)
    {
        while ($ast instanceof MList) {
            $data = $ast->getData();
            if (count($data) > 0 && $data[0] instanceof Symbol) {
                $fn = $env->get($data[0]->getName(), false);
                if ($fn && $fn instanceof Func && $fn->isMacro()) {
                    $ast = $fn->call(array_slice($data, 1));
                    continue;
                }
            }
            break;
        }

        return $ast;
    }

    private function quasiquote($ast)
    {
        if ($ast instanceof MList) {
            $data = $ast->getData();

            // Check for unquote
            if (count($data) > 0 && $data[0] instanceof Symbol && $data[0]->getName() == 'unquote') {
                if (count($data) == 2) {
                    return $data[1];
                } else {
                    throw new MadLispException("unquote requires exactly 1 argument");
                }
            }

            return $this->quasiquoteLoop($data);
        } elseif ($ast instanceof Vector) {
            return new MList([
                new Symbol('ltov'),
                $this->quasiquoteLoop($ast->getData())
            ]);
        } elseif ($ast instanceof Symbol || $ast instanceof Hash) {
            // Quote other forms which are affected by evaluation
            return new MList([
                new Symbol('quote'),
                $ast
            ]);
        } else {
            return $ast;
        }
    }

    private function quasiquoteLoop(array $data): MList
    {
        $result = new MList();

        for ($i = count($data) - 1; $i >= 0; $i--) {
            $elt = $data[$i];

            if ($elt instanceof MList && count($elt->getData()) > 0 && $elt->get(0) instanceof Symbol && $elt->get(0)->getName() == 'unquote-splice') {
                if (count($elt->getData()) == 2) {
                    $result = new MList([
                        new Symbol('concat'),
                        $elt->get(1),
                        $result
                    ]);
                } else {
                    throw new MadLispException("unquote-splice requires exactly 1 argument");
                }
            } else {
                $result = new MList([
                    new Symbol('cons'),
                    $this->quasiquote($elt),
                    $result
                ]);
            }
        }

        return $result;
    }
}
