<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class CoreFuncId
{
    public const int ADD = 1;
    public const int SUBTRACT = 2;
    public const int MULTIPLY = 3;
    public const int DIVIDE = 4;
    public const int INTDIV = 5;
    public const int MODULO = 6;
    public const int INC = 7;
    public const int DEC = 8;
    public const int MAX = 9;
    public const int MIN = 10;
    public const int EQUAL = 11;
    public const int STRICT_EQUAL = 12;
    public const int NOT_EQUAL = 13;
    public const int STRICT_NOT_EQUAL = 14;
    public const int LESS = 15;
    public const int LESS_EQUAL = 16;
    public const int GREATER = 17;
    public const int GREATER_EQUAL = 18;

    public static function fromName(string $name): ?array
    {
        return match ($name) {
            '+' => [self::ADD, 1],
            '-' => [self::SUBTRACT, 1],
            '*' => [self::MULTIPLY, 2],
            '/' => [self::DIVIDE, 2],
            '//' => [self::INTDIV, 2],
            '%' => [self::MODULO, 2],
            'inc' => [self::INC, 1],
            'dec' => [self::DEC, 1],
            'max' => [self::MAX, 1],
            'min' => [self::MIN, 1],
            '=' => [self::EQUAL, 2],
            '==' => [self::STRICT_EQUAL, 2],
            '!=' => [self::NOT_EQUAL, 2],
            '!==' => [self::STRICT_NOT_EQUAL, 2],
            '<' => [self::LESS, 2],
            '<=' => [self::LESS_EQUAL, 2],
            '>' => [self::GREATER, 2],
            '>=' => [self::GREATER_EQUAL, 2],
            default => null,
        };
    }
}
