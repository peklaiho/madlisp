<?php
namespace MadLisp;

class Tokenizer
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
}
