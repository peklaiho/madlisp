<?php

require_once('classes.php');

function ml_tokenize(string $a): array
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

function ml_read_form(array $tokens, int &$index)
{
    if ($tokens[$index] == '(') {
        return ml_read_list($tokens, $index);
    } else {
        return ml_read_atom($tokens, $index);
    }
}

function ml_read_list(array $tokens, int &$index): MLList
{
    $result = [];

    // start tag
    $index++;

    while ($tokens[$index] != ')') {
        $result[] = ml_read_form($tokens, $index);
    }

    // end tag
    $index++;

    return new MLList($result);
}

function ml_read_atom(array $tokens, int &$index)
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
        return new MLSymbol($a);
    }
}

function ml_parse(array $tokens): array
{
    $result = [];
    $index = 0;

    while ($index < count($tokens)) {
        $result[] = ml_read_form($tokens, $index);
    }

    return $result;
}

function ml_read(string $code): array
{
    $tokens = ml_tokenize($code);

    $expressions = ml_parse($tokens);

    return $expressions;
}

function ml_eval($expr, MLEnv $env)
{
    if ($expr instanceof MLList && $expr->count() > 0) {
        // Evaluate list contents
        $results = array_map(fn ($a) => ml_eval($a, $env), $expr->getData());

        if ($results[0] instanceof Closure) {
            // If the first item is a function, call it
            $args = array_slice($results, 1);
            return ($results[0])(...$args);
        } else {
            // Otherwise return new list with evaluated contents
            return new MLList($results);
        }
    } elseif ($expr instanceof MLSymbol) {
        return $env->get($expr->name());
    }

    return $expr;
}

function ml_print($a): string
{
    if ($a instanceof Closure) {
        return '<function>';
    } elseif ($a instanceof MLList) {
        return '(' . implode(' ', array_map('ml_print', $a->getData())) . ')';
    } elseif ($a instanceof MLSymbol) {
        return $a->name();
    } elseif ($a === true) {
        return 'true';
    } elseif ($a === false) {
        return 'false';
    } elseif ($a === null) {
        return 'null';
    } elseif (is_string($a)) {
        return '"' . $a . '"';
    } else {
        return $a;
    }
}

function ml_rep(string $input, MLEnv $env): string
{
    $expressions = ml_read($input);

    $results = array_map(fn ($expr) => ml_eval($expr, $env), $expressions);

    return implode(" ", array_map('ml_print', $results));
}
