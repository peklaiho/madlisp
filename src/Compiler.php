<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Compiler
{
    public function compile($ast): ?CompiledProgram
    {
        $code = [];
        $constants = [];
        $scope = new CompileScope();

        if (!$this->compileExpression($ast, $code, $constants, $scope)) {
            return null;
        }

        $code[] = OpCode::RETURN;

        return new CompiledProgram($code, $constants, $scope->getLocalCount());
    }

    private function compileExpression(
        $ast,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        // Symbol is a load from Env
        if ($ast instanceof Symbol) {
            $captureIndex = $scope->resolveCapture($ast->getName());
            if ($captureIndex !== null) {
                $code[] = OpCode::LOAD_CAPTURE;
                $code[] = $captureIndex;
                return true;
            }

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
            return $this->compileIf($data, $length, $code, $constants, $scope);
        }

        // Special forms: and/or
        if ($data[0] instanceof Symbol && $data[0]->getName() == 'and') {
            return $this->compileAnd($data, $length, $code, $constants, $scope);
        }

        if ($data[0] instanceof Symbol && $data[0]->getName() == 'or') {
            return $this->compileOr($data, $length, $code, $constants, $scope);
        }

        // Special form: do
        if ($data[0] instanceof Symbol && $data[0]->getName() == 'do') {
            return $this->compileDo($data, $length, $code, $constants, $scope);
        }

        // Special form: let
        if ($data[0] instanceof Symbol && $data[0]->getName() == 'let') {
            return $this->compileLet($data, $length, $code, $constants, $scope);
        }

        // Special form: fn
        if ($data[0] instanceof Symbol && $data[0]->getName() == 'fn') {
            return $this->compileFn($data, $length, $code, $constants, $scope);
        }

        // Special form: cond
        if ($data[0] instanceof Symbol && $data[0]->getName() == 'cond') {
            return $this->compileCond($data, $length, $code, $constants, $scope);
        }

        // Special form: def
        if ($data[0] instanceof Symbol && $data[0]->getName() == 'def') {
            return $this->compileDef($data, $length, $code, $constants, $scope);
        }

        // Other special forms: not supported yet
        if ($this->isSpecialForm($data[0])) {
            return false;
        }

        // Check for a supported core function
        $coreFuncMetadata = null;
        if ($data[0] instanceof Symbol) {
            $operatorName = $data[0]->getName();
            if ($scope->resolve($operatorName) === null
                && $scope->resolveCapture($operatorName) === null
            ) {
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
                if (!$this->compileExpression($data[$i], $code, $constants, $scope)) {
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
            if (!$this->compileExpression($item, $code, $constants, $scope)) {
                return false;
            }
        }

        $code[] = OpCode::CALL;
        $code[] = $length - 1;
        return true;
    }

    private function compileAnd(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length == 1) {
            $constants[] = true;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return true;
        }

        $jumps = [];
        for ($i = 1; $i < $length - 1; $i++) {
            if (!$this->compileExpression($data[$i], $code, $constants, $scope)) {
                return false;
            }

            $jumps[] = count($code);
            $code[] = OpCode::JUMP_IF_FALSE_KEEP;
            $code[] = 0;
            $code[] = OpCode::POP;
        }

        if (!$this->compileExpression($data[$length - 1], $code, $constants, $scope)) {
            return false;
        }

        $end = count($code);
        foreach ($jumps as $jump) {
            $code[$jump + 1] = $end;
        }

        return true;
    }

    private function compileOr(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length == 1) {
            $constants[] = false;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return true;
        }

        $jumps = [];
        for ($i = 1; $i < $length - 1; $i++) {
            if (!$this->compileExpression($data[$i], $code, $constants, $scope)) {
                return false;
            }

            $jumps[] = count($code);
            $code[] = OpCode::JUMP_IF_TRUE_KEEP;
            $code[] = 0;
            $code[] = OpCode::POP;
        }

        if (!$this->compileExpression($data[$length - 1], $code, $constants, $scope)) {
            return false;
        }

        $end = count($code);
        foreach ($jumps as $jump) {
            $code[$jump + 1] = $end;
        }

        return true;
    }

    private function compileDo(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length == 1) {
            $constants[] = null;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return true;
        }

        for ($i = 1; $i < $length - 1; $i++) {
            if (!$this->compileExpression($data[$i], $code, $constants, $scope)) {
                return false;
            }

            $code[] = OpCode::POP;
        }

        return $this->compileExpression($data[$length - 1], $code, $constants, $scope);
    }

    private function compileLet(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length < 3) {
            throw new MadLispException('let requires at least 2 arguments');
        }

        if (!($data[1] instanceof Seq)) {
            throw new MadLispException('first argument to let is not seq');
        }

        $bindings = $data[1]->getData();
        if (count($bindings) % 2 == 1) {
            throw new MadLispException('uneven number of bindings for let');
        }

        $bodyScope = $scope->child();

        for ($i = 0; $i < count($bindings); $i += 2) {
            if (!($bindings[$i] instanceof Symbol)) {
                throw new MadLispException('binding key for let is not symbol');
            }

            $name = $bindings[$i]->getName();
            $slot = $bodyScope->allocate();

            if (!$this->compileExpression($bindings[$i + 1], $code, $constants, $bodyScope)) {
                return false;
            }

            $code[] = OpCode::STORE_LOCAL;
            $code[] = $slot;
            $bodyScope->bind($name, $slot);
        }

        for ($i = 2; $i < $length - 1; $i++) {
            if (!$this->compileExpression($data[$i], $code, $constants, $bodyScope)) {
                return false;
            }

            $code[] = OpCode::POP;
        }

        return $this->compileExpression($data[$length - 1], $code, $constants, $bodyScope);
    }

    private function compileCond(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length < 2) {
            throw new MadLispException('cond requires at least 1 argument');
        }

        $endJumps = [];
        $pendingFalseJump = null;

        for ($i = 1; $i < $length; $i++) {
            if ($pendingFalseJump !== null) {
                $code[$pendingFalseJump + 1] = count($code);
                $pendingFalseJump = null;
            }

            if (!($data[$i] instanceof Seq)) {
                throw new MadLispException('argument to cond is not seq');
            }

            $clause = $data[$i]->getData();
            if (count($clause) < 2) {
                throw new MadLispException('clause for cond requires at least 2 arguments');
            }

            $isElse = $clause[0] instanceof Symbol && $clause[0]->getName() == 'else';
            if (!$isElse) {
                if (!$this->compileExpression($clause[0], $code, $constants, $scope)) {
                    return false;
                }

                $pendingFalseJump = count($code);
                $code[] = OpCode::JUMP_IF_FALSE;
                $code[] = 0;
            }

            for ($j = 1; $j < count($clause) - 1; $j++) {
                if (!$this->compileExpression($clause[$j], $code, $constants, $scope)) {
                    return false;
                }

                $code[] = OpCode::POP;
            }

            if (!$this->compileExpression($clause[count($clause) - 1], $code, $constants, $scope)) {
                return false;
            }

            $endJumps[] = count($code);
            $code[] = OpCode::JUMP;
            $code[] = 0;

            if ($isElse) {
                break;
            }
        }

        $constants[] = null;
        $code[] = OpCode::LOAD_CONSTANT;
        $code[] = count($constants) - 1;

        if ($pendingFalseJump !== null) {
            $code[$pendingFalseJump + 1] = count($code) - 2;
        }

        $end = count($code);
        foreach ($endJumps as $jump) {
            $code[$jump + 1] = $end;
        }

        return true;
    }

    private function compileDef(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length != 3) {
            throw new MadLispException('def requires exactly 2 arguments');
        }

        if (!($data[1] instanceof Symbol)) {
            throw new MadLispException('first argument to def is not symbol');
        }

        $name = $data[1]->getName();
        if (in_array($name, ['__FILE__', '__DIR__'], true)) {
            throw new MadLispException("attempt to def reserved symbol $name");
        }

        if (CoreFuncId::fromName($name) !== null) {
            throw new MadLispException("attempt to def core function $name");
        }

        if (!$this->compileExpression($data[2], $code, $constants, $scope)) {
            return false;
        }

        $constants[] = $name;
        $code[] = OpCode::STORE_GLOBAL;
        $code[] = count($constants) - 1;

        return true;
    }

    private function compileFn(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length != 3) {
            throw new MadLispException('fn requires exactly 2 arguments');
        }

        if (!($data[1] instanceof Seq)) {
            throw new MadLispException('first argument to fn is not seq');
        }

        $functionScope = $scope->functionChild();
        $parameters = $data[1]->getData();
        $parameterCount = count($parameters);
        $seen = [];

        foreach ($parameters as $parameter) {
            if (!($parameter instanceof Symbol)) {
                throw new MadLispException('binding key for fn is not symbol');
            }

            $name = $parameter->getName();
            if ($name == '&') {
                throw new MadLispException('variadic parameters are not supported for compiled fn');
            }

            if (isset($seen[$name])) {
                throw new MadLispException("duplicate parameter $name for fn");
            }

            $seen[$name] = true;
            $functionScope->define($name);
        }

        $functionCode = [];
        $functionConstants = [];
        if (!$this->compileExpression(
            $data[2],
            $functionCode,
            $functionConstants,
            $functionScope
        )) {
            return false;
        }

        $functionCode[] = OpCode::RETURN;
        $functionProgram = new CompiledProgram(
            $functionCode,
            $functionConstants,
            $functionScope->getLocalCount()
        );

        $constants[] = new CompiledFuncTemplate(
            $functionProgram,
            $parameterCount,
            $functionScope->getCaptureSources()
        );
        $code[] = OpCode::MAKE_FUNCTION;
        $code[] = count($constants) - 1;

        return true;
    }

    private function compileIf(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): bool
    {
        if ($length < 3 || $length > 4) {
            return false;
        }

        if (!$this->compileExpression($data[1], $code, $constants, $scope)) {
            return false;
        }

        $jumpIfFalse = count($code);
        $code[] = OpCode::JUMP_IF_FALSE;
        $code[] = 0;

        if (!$this->compileExpression($data[2], $code, $constants, $scope)) {
            return false;
        }

        $jumpToEnd = count($code);
        $code[] = OpCode::JUMP;
        $code[] = 0;

        $elseAddress = count($code);
        $code[$jumpIfFalse + 1] = $elseAddress;

        if ($length == 4) {
            if (!$this->compileExpression($data[3], $code, $constants, $scope)) {
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
