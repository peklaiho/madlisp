<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class IrCoreFuncId
{
    // Math functions
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

    // Comparison functions
    public const int EQUAL = 11;
    public const int STRICT_EQUAL = 12;
    public const int NOT_EQUAL = 13;
    public const int STRICT_NOT_EQUAL = 14;
    public const int LESS = 15;
    public const int LESS_EQUAL = 16;
    public const int GREATER = 17;
    public const int GREATER_EQUAL = 18;

    // Collection functions
    public const int HASH = 19;
    public const int LIST = 20;
    public const int VECTOR = 21;
    public const int RANGE = 22;
    public const int LTOV = 23;
    public const int VTOL = 24;
    public const int EMPTY = 25;
    public const int CONTAINS = 26;
    public const int GET = 27;
    public const int LEN = 28;
    public const int CAR = 29;
    public const int FIRST = 30;
    public const int LAST = 31;
    public const int HEAD = 32;
    public const int CDR = 33;
    public const int TAIL = 34;
    public const int SLICE = 35;
    public const int APPLY = 36;
    public const int CHUNK = 37;
    public const int CONCAT = 38;
    public const int PUSH = 39;
    public const int CONS = 40;
    public const int MAP = 41;
    public const int MAP2 = 42;
    public const int REDUCE = 43;
    public const int FILTER = 44;
    public const int FILTERH = 45;
    public const int REVERSE = 46;
    public const int KEY = 47;
    public const int SET = 48;
    public const int SET_MUTATE = 49;
    public const int UNSET = 50;
    public const int UNSET_MUTATE = 51;
    public const int KEYS = 52;
    public const int VALUES = 53;
    public const int ZIP = 54;
    public const int SORT = 55;

    // Type functions
    public const int BOOL = 56;
    public const int FLOAT = 57;
    public const int INT = 58;
    public const int STR = 59;
    public const int SYMBOL = 60;
    public const int NOT = 61;
    public const int TYPE = 62;
    public const int FUNCTION = 63;
    public const int MACRO = 64;
    public const int LIST_TYPE = 65;
    public const int VECTOR_TYPE = 66;
    public const int SEQ_TYPE = 67;
    public const int HASH_TYPE = 68;
    public const int SYMBOL_TYPE = 69;
    public const int OBJECT_TYPE = 70;
    public const int RESOURCE_TYPE = 71;
    public const int BOOL_TYPE = 72;
    public const int TRUE = 73;
    public const int FALSE = 74;
    public const int NULL_TYPE = 75;
    public const int INT_TYPE = 76;
    public const int FLOAT_TYPE = 77;
    public const int STR_TYPE = 78;
    public const int ZERO = 79;
    public const int ONE = 80;
    public const int EVEN = 81;
    public const int ODD = 82;

    // String functions
    public const int TRIM = 83;
    public const int LTRIM = 84;
    public const int RTRIM = 85;
    public const int UPCASE = 86;
    public const int LOWCASE = 87;
    public const int STRPOS = 88;
    public const int STRIPOS = 89;
    public const int SUBSTR = 90;
    public const int REPLACE = 91;
    public const int SPLIT = 92;
    public const int JOIN = 93;
    public const int FORMAT = 94;
    public const int PREFIX = 95;
    public const int SUFFIX = 96;
    public const int STRCMP = 97;
    public const int STRCASECMP = 98;
    public const int STRNATCMP = 99;
    public const int STRNATCASECMP = 100;

    public static function fromName(string $name): ?array
    {
        return match ($name) {
            // Math functions
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

            // Comparison functions
            '=' => [self::EQUAL, 2],
            '==' => [self::STRICT_EQUAL, 2],
            '!=' => [self::NOT_EQUAL, 2],
            '!==' => [self::STRICT_NOT_EQUAL, 2],
            '<' => [self::LESS, 2],
            '<=' => [self::LESS_EQUAL, 2],
            '>' => [self::GREATER, 2],
            '>=' => [self::GREATER_EQUAL, 2],

            // Collection functions
            'hash' => [self::HASH, 0],
            'list' => [self::LIST, 0],
            'vector' => [self::VECTOR, 0],
            'range' => [self::RANGE, 1],
            'ltov' => [self::LTOV, 1],
            'vtol' => [self::VTOL, 1],
            'empty?' => [self::EMPTY, 1],
            'contains?' => [self::CONTAINS, 2],
            'get' => [self::GET, 2],
            'len' => [self::LEN, 1],
            'car' => [self::CAR, 1],
            'first' => [self::FIRST, 1],
            'last' => [self::LAST, 1],
            'head' => [self::HEAD, 1],
            'cdr' => [self::CDR, 1],
            'tail' => [self::TAIL, 1],
            'slice' => [self::SLICE, 2],
            'apply' => [self::APPLY, 2],
            'chunk' => [self::CHUNK, 2],
            'concat' => [self::CONCAT, 1],
            'push' => [self::PUSH, 2],
            'cons' => [self::CONS, 2],
            'map' => [self::MAP, 2],
            'map2' => [self::MAP2, 3],
            'reduce' => [self::REDUCE, 2],
            'filter' => [self::FILTER, 2],
            'filterh' => [self::FILTERH, 2],
            'reverse' => [self::REVERSE, 1],
            'key?' => [self::KEY, 2],
            'set' => [self::SET, 3],
            'set!' => [self::SET_MUTATE, 3],
            'unset' => [self::UNSET, 2],
            'unset!' => [self::UNSET_MUTATE, 2],
            'keys' => [self::KEYS, 1],
            'values' => [self::VALUES, 1],
            'zip' => [self::ZIP, 2],
            'sort' => [self::SORT, 1],

            // Type functions
            'bool' => [self::BOOL, 1],
            'float' => [self::FLOAT, 1],
            'int' => [self::INT, 1],
            'str' => [self::STR, 0],
            'symbol' => [self::SYMBOL, 1],
            'not' => [self::NOT, 1],
            'type' => [self::TYPE, 1],
            'fn?' => [self::FUNCTION, 1],
            'macro?' => [self::MACRO, 1],
            'list?' => [self::LIST_TYPE, 1],
            'vector?' => [self::VECTOR_TYPE, 1],
            'seq?' => [self::SEQ_TYPE, 1],
            'hash?' => [self::HASH_TYPE, 1],
            'symbol?' => [self::SYMBOL_TYPE, 1],
            'object?' => [self::OBJECT_TYPE, 1],
            'resource?' => [self::RESOURCE_TYPE, 1],
            'bool?' => [self::BOOL_TYPE, 1],
            'true?' => [self::TRUE, 1],
            'false?' => [self::FALSE, 1],
            'null?' => [self::NULL_TYPE, 1],
            'int?' => [self::INT_TYPE, 1],
            'float?' => [self::FLOAT_TYPE, 1],
            'str?' => [self::STR_TYPE, 1],
            'zero?' => [self::ZERO, 1],
            'one?' => [self::ONE, 1],
            'even?' => [self::EVEN, 1],
            'odd?' => [self::ODD, 1],

            // String functions
            'trim' => [self::TRIM, 1],
            'ltrim' => [self::LTRIM, 1],
            'rtrim' => [self::RTRIM, 1],
            'upcase' => [self::UPCASE, 1],
            'lowcase' => [self::LOWCASE, 1],
            'strpos' => [self::STRPOS, 2],
            'stripos' => [self::STRIPOS, 2],
            'substr' => [self::SUBSTR, 2],
            'replace' => [self::REPLACE, 3],
            'split' => [self::SPLIT, 2],
            'join' => [self::JOIN, 1],
            'format' => [self::FORMAT, 1],
            'prefix?' => [self::PREFIX, 2],
            'suffix?' => [self::SUFFIX, 2],
            'strcmp' => [self::STRCMP, 2],
            'strcasecmp' => [self::STRCASECMP, 2],
            'strnatcmp' => [self::STRNATCMP, 2],
            'strnatcasecmp' => [self::STRNATCASECMP, 2],

            default => null,
        };
    }
}
