<?php

class MadLispException extends Exception {}

define("SYMBOL", "SYMBOL");
define("STRING", "STRING");
define("NUMBER", "NUMBER");
define("START", "START");
define("END", "END");

function ml_tokenize(string $a): array
{
    $tokens = [];
    $current = '';
    $string = false;
    $parens = 0;

    $addCurrent = function($addEmpty = false) use (&$string, &$tokens, &$current) {
        if ($current !== '' || $addEmpty) {
            $type = $string ? STRING : (is_numeric($current) ? NUMBER : SYMBOL);
            $tokens[] = [$type, $current];
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
    if (empty($tokens)) {
        throw new MadLispException("no input");
    } elseif ($tokens[0][0] !== START) {
        throw new MadLispException("missing opening parenthesis");
    } elseif ($parens != 0) {
        throw new MadLispException("missing closing parenthesis");
    } elseif ($string) {
        throw new MadLispException("unterminated string");
    }

    return $tokens;
}

function ml_parse_expressions($tokens, &$index)
{
    $result = [];

    for (; $index < count($tokens); ) {
        if ($token[0] == START) {

        }
        if ($token[0] == END) {
            break;
        }
    }

    return $result;
}

function ml_read($a)
{
    $tokens = ml_tokenize($a);

    $index = 0;
    $expressions = ml_parse_expressions($tokens, $index);

    return $expressions;
}

function ml_eval($a)
{
    return $a;
}

function ml_print($a)
{
    // print(implode('*', $a));
    print_r($a);
}

function ml_rep($a)
{
    ml_print(ml_eval(ml_read($a)));
}
