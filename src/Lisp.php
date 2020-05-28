<?php
namespace MadLisp;

use Closure;

class Lisp
{
    public function tokenize(string $a): array
    {
        $tokens = [];
        $current = '';
        $string = false;
        $parens = 0;

        $addCurrent = function () use (&$tokens, &$current) {
            if ($current !== '') {
                $tokens[] = $current;
                $current = '';
            }
        };

        for ($i = 0; $i < strlen($a); $i++) {
            $c = substr($a, $i, 1);

            if ($string) {
                // Inside string, add all characters
                $current .= $c;

                // Stop at "
                if ($c == '"') {
                    $addCurrent();
                    $string = false;
                }
            } else {
                // Not inside string

                if ($c == '"') {
                    // Start of string
                    $addCurrent();
                    $current .= $c;
                    $string = true;
                } elseif ($c == ' ' || $c == "\t" || $c == "\n" || $c == "\r") {
                    // Whitespace is ignored
                    $addCurrent();
                } elseif ($c == '(') {
                    // Start of list
                    $addCurrent();
                    $tokens[] = '(';
                    $parens++;
                } elseif ($c == ')') {
                    // End of list
                    if ($parens == 0) {
                        throw new MadLispException("unexpected closing parenthesis");
                    }
                    $addCurrent();
                    $tokens[] = ')';
                    $parens--;
                } else {
                    // All other characters
                    $current .= $c;
                }
            }
        }

        // Add last also
        $addCurrent();

        // Check for errors
        if ($parens != 0) {
            throw new MadLispException("missing closing parenthesis");
        } elseif ($string) {
            throw new MadLispException("unterminated string");
        }

        return $tokens;
    }

    public function parse(array $tokens): array
    {
        $result = [];
        $index = 0;

        while ($index < count($tokens)) {
            $result[] = $this->readForm($tokens, $index);
        }

        return $result;
    }

    public function read(string $code): array
    {
        $tokens = $this->tokenize($code);

        $expressions = $this->parse($tokens);

        return $expressions;
    }

    public function eval($expr, Env $env)
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
                }

                // Normal symbol, fetch from env
                $first = $env->get($first->getName());
            }

            if (!($first instanceof Closure)) {
                throw new MadLispException("first argument of list is not function");
            }

            $args = array_slice($expr->getData(), 1);

            // Evaluate args
            $args = array_map(fn ($a) => $this->eval($a, $env), $args);

            // Call func and return result
            return $first(...$args);
        } elseif ($expr instanceof Hash) {
            // Hash: return new hash with all items evaluated
            $items = [];
            foreach ($expr->getData() as $key => $val) {
                $items[$key] = $this->eval($val, $env);
            }
            return new Hash($items);
        } elseif ($expr instanceof Symbol) {
            return $env->get($expr->getName());
        }

        // Return the expression unchanged
        return $expr;
    }

    public function print($a): string
    {
        $result = $a;

        if ($a instanceof Closure) {
            $result = '<function>';
        } elseif ($a instanceof MList) {
            $items = [];
            foreach ($a->getData() as $val) {
                $items[] = $this->print($val);
            }
            $result = '(' . implode(' ', $items) . ')';
        } elseif ($a instanceof Hash) {
            $items = [];
            foreach ($a->getData() as $key => $val) {
                $items[] = $this->print($key) . ':' . $this->print($val);
            }
            $result = '{' . implode(' ', $items) . '}';
        } elseif ($a instanceof Symbol) {
            $result = $a->getName();
        } elseif ($a === true) {
            $result = 'true';
        } elseif ($a === false) {
            $result = 'false';
        } elseif ($a === null) {
            $result = 'null';
        } elseif (is_string($a)) {
            $result = '"' . $a . '"';
        }

        return $result;
    }

    public function rep(string $input, Env $env): void
    {
        $expressions = $this->read($input);

        $results = array_map(fn ($expr) => $this->eval($expr, $env), $expressions);

        $output = array_map(fn ($res) => $this->print($res), $results);

        print(implode(' ', $output));
    }

    private function readForm(array $tokens, int &$index)
    {
        if ($tokens[$index] == '(') {
            return $this->readList($tokens, $index);
        } else {
            return $this->readAtom($tokens, $index);
        }
    }

    private function readList(array $tokens, int &$index): MList
    {
        $result = [];

        // start tag
        $index++;

        while ($tokens[$index] != ')') {
            $result[] = $this->readForm($tokens, $index);
        }

        // end tag
        $index++;

        return new MList($result);
    }

    private function readAtom(array $tokens, int &$index)
    {
        $a = $tokens[$index++];

        if ($a === 'true') {
            return true;
        } elseif ($a === 'false') {
            return false;
        } elseif ($a === 'null') {
            return null;
        } elseif (substr($a, 0, 1) === '"') {
            // string
            return substr($a, 1, strlen($a) - 2);
        } elseif (is_numeric($a)) {
            if (filter_var($a, FILTER_VALIDATE_INT) !== false) {
                return intval($a);
            } else {
                return floatval($a);
            }
        } else {
            return new Symbol($a);
        }
    }
}
