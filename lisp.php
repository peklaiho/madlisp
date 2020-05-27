<?php

require_once('classes.php');

// types
define("SYMBOL", "SYMBOL");
define("STRING", "STRING");
define("NUMBER", "NUMBER");
define("START", "START");
define("END", "END");

// special sign for symbols
define("MAGIC", "§");

function ml_tokenize(string $a): array
{
    $tokens = [];
    $current = '';
    $string = false;
    $parens = 0;

    $addCurrent = function ($string = false) use (&$tokens, &$current) {
        if ($current !== '' || $string) {
            if ($string) {
                $tokens[] = [STRING, $current];
            } elseif ($current == 'true') {
                $tokens[] = [true];
            } elseif ($current == 'false') {
                $tokens[] = [false];
            } elseif ($current == 'null') {
                $tokens[] = [null];
            } elseif (is_numeric($current)) {
                $tokens[] = [NUMBER, $current];
            } else {
                $tokens[] = [SYMBOL, $current];
            }
            $current = '';
        }
    };

    for ($i = 0; $i < strlen($a); $i++) {
        $c = substr($a, $i, 1);

        if ($c == '"') {
            if ($string) {
                // End of string
                $addCurrent(true);
                $string = false;
            } else {
                // Start of string
                $string = true;
            }
        } elseif ($c == ' ' || $c == "\t" || $c == "\n" || $c == "\r") {
            if ($string) {
                // Include whitespace only inside strings
                $current .= $c;
            } else {
                $addCurrent();
            }
        } elseif ($c == '(') {
            $addCurrent();
            $tokens[] = [START];
            $parens++;
        } elseif ($c == ')') {
            if ($parens == 0) {
                throw new MadLispException("unexpected closing parenthesis");
            }
            $addCurrent();
            $tokens[] = [END];
            $parens--;
        } else {
            // All other characters are included normally
            $current .= $c;
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
    $a = $tokens[$index];

    if ($a[0] == START) {
        return ml_read_list($tokens, $index);
    } else {
        return ml_read_atom($tokens, $index);
    }
}

function ml_read_list(array $tokens, int &$index): array
{
    $result = [];

    // start tag
    $index++;

    while ($tokens[$index][0] != END) {
        $result[] = ml_read_form($tokens, $index);
    }

    // end tag
    $index++;

    return $result;
}

function ml_read_atom(array $tokens, int &$index)
{
    $a = $tokens[$index++];

    if ($a[0] == STRING) {
        return $a[1];
    } elseif ($a[0] == SYMBOL) {
        return MAGIC . $a[1];
    } elseif ($a[0] == NUMBER) {
        if (filter_var($a[1], FILTER_VALIDATE_INT) !== false) {
            return intval($a[1]);
        } else {
            return floatval($a[1]);
        }
    } else {
        return $a[0];
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

function ml_is_symbol($a)
{
    return substr($a, 0, strlen(MAGIC)) === MAGIC;
}

function ml_strip_symbol($a)
{
    return substr($a, strlen(MAGIC));
}

function ml_read(string $code): array
{
    $tokens = ml_tokenize($code);

    $expressions = ml_parse($tokens);

    return $expressions;
}

function ml_eval($expr, Env $env)
{
    if (is_array($expr)) {
        // Evaluate list items
        $expr = array_map(fn ($a) => ml_eval($a, $env), $expr);

        // If the first item is a function, call it
        $fn = $expr[0] ?? null;
        if ($fn && $fn instanceof Closure) {
            $args = array_slice($expr, 1);
            return $fn(...$args);
        }
    } elseif (ml_is_symbol($expr)) {
        return $env->get(ml_strip_symbol($expr));
    }

    return $expr;
}

function ml_print($a): string
{
    if ($a instanceof Closure) {
        return '<function>';
    } elseif (is_array($a)) {
        return '(' . implode(' ', array_map('ml_print', $a)) . ')';
    } elseif ($a === true) {
        return 'true';
    } elseif ($a === false) {
        return 'false';
    } elseif ($a === null) {
        return 'null';
    } elseif (ml_is_symbol($a)) {
        return ml_strip_symbol($a);
    } elseif (is_string($a)) {
        return '"' . $a . '"';
    } else {
        return $a;
    }
}

function ml_rep(string $input, Env $env): string
{
    $expressions = ml_read($input);

    $results = array_map(fn ($expr) => ml_eval($expr, $env), $expressions);

    return implode(" ", array_map('ml_print', $results));
}
