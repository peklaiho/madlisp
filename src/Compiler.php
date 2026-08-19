<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Compiler
{
    public function compile($ast, Env $env): ?CompiledProgram
    {
        $code = [];
        $constants = [];

        if (!$this->compileExpression($ast, $code, $constants, $env)) {
            return null;
        }

        $code[] = OpCode::RETURN;

        return new CompiledProgram($code, $constants, 0);
    }

    private function compileExpression($ast, array &$code, array &$constants, Env $env): bool
    {
        if ($ast instanceof Symbol) {
            $constants[] = $ast->getName();
            $code[] = OpCode::LOAD_GLOBAL;
            $code[] = count($constants) - 1;

            return true;
        }

        if (!($ast instanceof Collection)) {
            $constants[] = $ast;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;

            return true;
        }

        if (!($ast instanceof MList)) {
            return false;
        }

        $data = $ast->getData();
        $length = count($data);

        if ($length == 0) {
            return false;
        }

        if ($data[0] instanceof Symbol && $data[0]->getName() == 'if') {
            return $this->compileIf($data, $length, $code, $constants, $env);
        }

        if ($this->isSpecialForm($data[0])) {
            return false;
        }

        if ($data[0] instanceof Symbol) {
            $operator = $env->get($data[0]->getName(), false);
            if ($operator instanceof Func && $operator->isMacro()) {
                return false;
            }
        }

        foreach ($data as $item) {
            if (!$this->compileExpression($item, $code, $constants, $env)) {
                return false;
            }
        }

        $code[] = OpCode::CALL;
        $code[] = $length - 1;

        return true;
    }

    private function compileIf(array $data, int $length, array &$code, array &$constants, Env $env): bool
    {
        if ($length < 3 || $length > 4) {
            return false;
        }

        if (!$this->compileExpression($data[1], $code, $constants, $env)) {
            return false;
        }

        $jumpIfFalse = count($code);
        $code[] = OpCode::JUMP_IF_FALSE;
        $code[] = 0;

        if (!$this->compileExpression($data[2], $code, $constants, $env)) {
            return false;
        }

        $jumpToEnd = count($code);
        $code[] = OpCode::JUMP;
        $code[] = 0;

        $elseAddress = count($code);
        $code[$jumpIfFalse + 1] = $elseAddress;

        if ($length == 4) {
            if (!$this->compileExpression($data[3], $code, $constants, $env)) {
                return false;
            }
        } else {
            $constants[] = null;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
        }

        $code[$jumpToEnd + 1] = count($code);

        return true;
    }

    private function isSpecialForm($operator): bool
    {
        if (!($operator instanceof Symbol)) {
            return false;
        }

        return in_array($operator->getName(), [
            'and', 'case', 'case-strict', 'cond', 'def', 'do', 'env', 'eval',
            'fn', 'if', 'let', 'load', 'macro', 'macroexpand', 'meta', 'or',
            'quote', 'quasiquote', 'quasiquote-expand', 'try', 'undef', 'while'
        ], true);
    }
}
