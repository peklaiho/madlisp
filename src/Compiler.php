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
        $scope = new CompileScope();

        if (!$this->compileExpression($ast, $code, $constants, $env, $scope)) {
            return null;
        }

        $code[] = OpCode::RETURN;

        return new CompiledProgram($code, $constants, $scope->getLocalCount());
    }

    private function compileExpression(
        $ast,
        array &$code,
        array &$constants,
        Env $env,
        CompileScope $scope
    ): bool
    {
        // Symbol is a load from Env
        if ($ast instanceof Symbol) {
            $localSlot = $scope->resolve($ast->getName());
            if ($localSlot !== null) {
                $code[] = OpCode::LOAD_LOCAL;
                $code[] = $localSlot;
                return true;
            }

            $constants[] = $ast->getName();
            $code[] = OpCode::LOAD_GLOBAL;
            $code[] = count($constants) - 1;
            return true;
        }

        // Not a collection is a constant (like number or string)
        if (!($ast instanceof Collection)) {
            $constants[] = $ast;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return true;
        }

        // Not a list: not supported yet
        if (!($ast instanceof MList)) {
            return false;
        }

        $data = $ast->getData();
        $length = count($data);

        if ($length == 0) {
            return false;
        }

        // Special form: if
        if ($data[0] instanceof Symbol && $data[0]->getName() == 'if') {
            return $this->compileIf($data, $length, $code, $constants, $env, $scope);
        }

        // Other special forms: not supported yet
        if ($this->isSpecialForm($data[0])) {
            return false;
        }

        // Check for a supported core function
        $coreFuncMetadata = null;
        if ($data[0] instanceof Symbol) {
            $operatorName = $data[0]->getName();
            $operator = $env->get($operatorName, false);
            if ($operator instanceof Func && $operator->isMacro()) {
                return false;
            }

            if ($operator instanceof CoreFunc) {
                $coreFuncMetadata = CoreFuncId::fromName($operatorName);
            }
        }

        // Found core function
        if ($coreFuncMetadata !== null) {
            $argumentCount = $length - 1;
            $minimumArguments = $coreFuncMetadata[1];
            if ($argumentCount < $minimumArguments) {
                throw new MadLispException(sprintf(
                    "%s requires at least %s argument%s",
                    $operatorName,
                    $minimumArguments,
                    $minimumArguments == 1 ? '' : 's'
                ));
            }

            for ($i = 1; $i < $length; $i++) {
                if (!$this->compileExpression($data[$i], $code, $constants, $env, $scope)) {
                    return false;
                }
            }

            $code[] = OpCode::CALL_CORE;
            $code[] = $coreFuncMetadata[0];
            $code[] = $argumentCount;
            return true;
        }

        // Handle as normal function call
        foreach ($data as $item) {
            if (!$this->compileExpression($item, $code, $constants, $env, $scope)) {
                return false;
            }
        }

        $code[] = OpCode::CALL;
        $code[] = $length - 1;
        return true;
    }

    private function compileIf(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        Env $env,
        CompileScope $scope
    ): bool
    {
        if ($length < 3 || $length > 4) {
            return false;
        }

        if (!$this->compileExpression($data[1], $code, $constants, $env, $scope)) {
            return false;
        }

        $jumpIfFalse = count($code);
        $code[] = OpCode::JUMP_IF_FALSE;
        $code[] = 0;

        if (!$this->compileExpression($data[2], $code, $constants, $env, $scope)) {
            return false;
        }

        $jumpToEnd = count($code);
        $code[] = OpCode::JUMP;
        $code[] = 0;

        $elseAddress = count($code);
        $code[$jumpIfFalse + 1] = $elseAddress;

        if ($length == 4) {
            if (!$this->compileExpression($data[3], $code, $constants, $env, $scope)) {
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
