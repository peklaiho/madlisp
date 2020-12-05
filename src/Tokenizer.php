<?php
namespace MadLisp;

class Tokenizer
{
    public function tokenize(string $a): array
    {
        $tokens = [];
        $current = '';

        $isString = false;
        $isComment = false;

        $parens = [0, 0, 0];
        $parenIndexes = ['(' => 0, ')' => 0, '[' => 1, ']' => 1, '{' => 2, '}' => 2];

        $addCurrent = function () use (&$tokens, &$current) {
            if ($current !== '') {
                $tokens[] = $current;
                $current = '';
            }
        };

        for ($i = 0; $i < strlen($a); $i++) {
            $c = substr($a, $i, 1);

            if ($isString) {
                // Inside string, add all characters
                $current .= $c;

                // Stop at first double quote
                if ($c == '"') {
                    // If previous character is not a backslash
                    if (strlen($current) < 2 || substr($current, -2, 1) != "\\") {
                        $addCurrent();
                        $isString = false;
                    }
                }
            } elseif ($isComment) {
                // Comments stop at first newline
                if ($c == "\n" || $c == "\r") {
                    $isComment = false;
                }
            } else {
                // Not inside string or comment

                if ($c == '"') {
                    // Start of string
                    $addCurrent();
                    $current .= $c;
                    $isString = true;
                } elseif ($c == ';') {
                    // Start of comment
                    $addCurrent();
                    $isComment = true;
                } elseif ($c == ' ' || $c == "\t" || $c == "\n" || $c == "\r" || $c == ':') {
                    // Whitespace and colon are ignored
                    $addCurrent();
                } elseif ($c == '(' || $c == '[' || $c == '{') {
                    // Start of collection
                    $addCurrent();
                    $tokens[] = $c;
                    $parens[$parenIndexes[$c]]++;
                } elseif ($c == ')' || $c == ']' || $c == '}') {
                    // End of collection
                    if ($parens[$parenIndexes[$c]] == 0) {
                        throw new MadLispException("unexpected closing $c");
                    }
                    $addCurrent();
                    $tokens[] = $c;
                    $parens[$parenIndexes[$c]]--;
                } elseif ($c == "'") {
                    // Other special characters
                    $addCurrent();
                    $tokens[] = $c;
                } else {
                    // All other characters
                    $current .= $c;
                }
            }
        }

        // Add last token
        $addCurrent();

        // Check for errors
        if ($isString) {
            throw new MadLispException("unterminated string");
        } elseif ($parens[0] != 0) {
            throw new MadLispException("missing closing )");
        } elseif ($parens[1] != 0) {
            throw new MadLispException("missing closing ]");
        } elseif ($parens[2] != 0) {
            throw new MadLispException("missing closing }");
        }

        return $tokens;
    }
}
