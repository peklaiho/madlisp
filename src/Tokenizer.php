<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

class Tokenizer
{
    public function tokenize(string $a): array
    {
        $tokens = [];
        $current = '';

        $isString = false;
        $isComment = false;
        $isEscape = false;

        $parens = [0, 0, 0];
        $parenIndexes = ['(' => 0, ')' => 0, '[' => 1, ']' => 1, '{' => 2, '}' => 2];

        $addCurrent = function () use (&$tokens, &$current) {
            if ($current !== '') {
                $tokens[] = $current;
                $current = '';
            }
        };

        // Use mbstring extension if available to support Unicode characters
        if (extension_loaded('mbstring')) {
            $lenfn = 'mb_strlen';
            $subfn = 'mb_substr';
        } else {
            $lenfn = 'strlen';
            $subfn = 'substr';
        }

        for ($i = 0; $i < $lenfn($a); $i++) {
            $c = $subfn($a, $i, 1);

            if ($isString) {
                if ($isEscape) {
                    if ($c == 'n') {
                        $current .= "\n";
                    } elseif ($c == 'r') {
                        $current .= "\r";
                    } elseif ($c == 't') {
                        $current .= "\t";
                    } elseif ($c == 'v') {
                        $current .= "\v";
                    } elseif ($c == '0') {
                        $current .= "\0";
                    } elseif ($c == "\\" || $c == '"') {
                        $current .= $c;
                    } else {
                        throw new MadLispException("invalid escape sequence \\$c");
                    }
                    $isEscape = false;
                } elseif ($c == "\\") {
                    $isEscape = true;
                } else {
                    // Not handling escape sequence
                    $current .= $c;
                    if ($c == '"') {
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
                    $isEscape = false;
                } elseif ($c == ';') {
                    // Start of comment
                    $addCurrent();
                    $isComment = true;
                } elseif ($c == ' ' || $c == "\t" || $c == "\n" || $c == "\r" || $c == "\v" || $c == "\0" || $c == ':') {
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
                } elseif ($c == "'" || $c == "`" || $c == "~") {
                    // Other special characters
                    $addCurrent();
                    $tokens[] = $c;
                } elseif ($c == '@') {
                    // If the last token was ~ then add @ to it
                    if (count($tokens) > 0 && $tokens[count($tokens) - 1] == '~') {
                        $tokens[count($tokens) - 1] .= $c;
                    } else {
                        // Otherwise treat it like normal character
                        $current .= $c;
                    }
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
