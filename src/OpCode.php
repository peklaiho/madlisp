<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class OpCode
{
    public const int LOAD_CONSTANT = 1;
    public const int LOAD_GLOBAL = 2;
    public const int JUMP_IF_FALSE = 3;
    public const int JUMP = 4;
    public const int RETURN = 5;
    public const int CALL = 6;
    public const int CALL_CORE = 7;
    public const int LOAD_LOCAL = 8;
    public const int STORE_LOCAL = 9;
    public const int POP = 10;
    public const int MAKE_FUNCTION = 11;
    public const int LOAD_CAPTURE = 12;
    public const int STORE_GLOBAL = 13;
    public const int JUMP_IF_FALSE_KEEP = 14;
    public const int JUMP_IF_TRUE_KEEP = 15;
    public const int CASE_COMPARE = 16;
    public const int CASE_COMPARE_STRICT = 17;
    public const int TAIL_CALL = 18;
    public const int LOAD_ENV = 19;
    public const int UNDEF = 20;
    public const int BUILD_VECTOR = 21;
    public const int BUILD_HASH = 22;
    public const int EXECUTE_PROGRAM = 23;
    public const int LOAD_FILE = 24;
}
