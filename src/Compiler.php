<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Compiler
{
    public function compile($ast): CompiledProgram
    {
        $code = [];
        $constants = [];
        $scope = new CompileScope();

        $this->compileExpression($ast, $code, $constants, $scope);

        $code[] = OpCode::RETURN;

        return new CompiledProgram($code, $constants, $scope->getLocalCount());
    }

    private function compileExpression(
        $ast,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition = false
    ): void
    {
        // Resolve symbols as captures, locals, or global environment values
        if ($ast instanceof Symbol) {
            $captureIndex = $scope->resolveCapture($ast->getName());
            if ($captureIndex !== null) {
                $code[] = OpCode::LOAD_CAPTURE;
                $code[] = $captureIndex;
                return;
            }

            $localSlot = $scope->resolve($ast->getName());
            if ($localSlot !== null) {
                $code[] = OpCode::LOAD_LOCAL;
                $code[] = $localSlot;
                return;
            }

            $constants[] = $ast->getName();
            $code[] = OpCode::LOAD_GLOBAL;
            $code[] = count($constants) - 1;
            return;
        }

        // Not a collection is a constant (like number or string)
        if (!($ast instanceof Collection)) {
            $constants[] = $ast;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return;
        }

        // Compile Vector and Hash
        if ($ast instanceof Vector) {
            $this->compileVector($ast, $code, $constants, $scope);
            return;
        } elseif ($ast instanceof Hash) {
            $this->compileHash($ast, $code, $constants, $scope);
            return;
        }

        // If we get here, $ast must be instance of MList!
        // Therefore we are compiling either a special form
        // or a function application.

        $data = $ast->getData();
        $length = count($data);

        // Empty list in an error
        if ($length == 0) {
            throw new MadLispException('unquoted empty list');
        }

        // Compile supported special forms
        if ($data[0] instanceof Symbol) {
            switch ($data[0]->getName()) {
                case 'and':
                    $this->compileAnd($data, $length, $code, $constants, $scope, $tailPosition);
                    return;

                case 'case':
                case 'case-strict':
                    $this->compileCase($data, $length, $code, $constants, $scope, $tailPosition);
                    return;

                case 'cond':
                    $this->compileCond($data, $length, $code, $constants, $scope, $tailPosition);
                    return;

                case 'def':
                    $this->compileDef($data, $length, $code, $constants, $scope);
                    return;

                case 'do':
                    $this->compileDo($data, $length, $code, $constants, $scope, $tailPosition);
                    return;

                case 'env':
                    if ($length != 1) {
                        throw new MadLispException('env does not take arguments');
                    }
                    $code[] = OpCode::LOAD_ENV;
                    return;

                case 'execute':
                    $this->compileExecutorOperation($data, $length, $code, $constants, $scope, OpCode::EXECUTE_PROGRAM);
                    return;

                case 'fn':
                    $this->compileFn($data, $length, $code, $constants, $scope);
                    return;

                case 'if':
                    $this->compileIf($data, $length, $code, $constants, $scope, $tailPosition);
                    return;

                case 'let':
                    $this->compileLet($data, $length, $code, $constants, $scope, $tailPosition);
                    return;

                case 'load':
                    $this->compileExecutorOperation($data, $length, $code, $constants, $scope, OpCode::LOAD_FILE);
                    return;

                case 'or':
                    $this->compileOr($data, $length, $code, $constants, $scope, $tailPosition);
                    return;

                case 'quote':
                    $this->compileQuote($data, $length, $code, $constants);
                    return;

                case 'undef':
                    $this->compileUndef($data, $length, $code, $constants);
                    return;

                case 'while':
                    $this->compileWhile($data, $length, $code, $constants, $scope);
                    return;
            }
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
                $this->compileExpression($data[$i], $code, $constants, $scope);
            }

            $code[] = OpCode::CALL_CORE;
            $code[] = $coreFuncMetadata[0];
            $code[] = $argumentCount;
            return;
        }

        // Handle as normal function call
        foreach ($data as $item) {
            $this->compileExpression($item, $code, $constants, $scope);
        }

        $code[] = $tailPosition ? OpCode::TAIL_CALL : OpCode::CALL;
        $code[] = $length - 1;
    }

    // Private functions for compiling special forms, in alphabetical order

    private function compileAnd(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition
    ): void
    {
        if ($length == 1) {
            $constants[] = true;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return;
        }

        $jumps = [];
        for ($i = 1; $i < $length - 1; $i++) {
            $this->compileExpression($data[$i], $code, $constants, $scope);
            $jumps[] = count($code);
            $code[] = OpCode::JUMP_IF_FALSE_KEEP;
            $code[] = 0;
            $code[] = OpCode::POP;
        }

        $this->compileExpression($data[$length - 1], $code, $constants, $scope, $tailPosition);

        $end = count($code);
        foreach ($jumps as $jump) {
            $code[$jump + 1] = $end;
        }
    }

    private function compileCase(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition
    ): void
    {
        if ($length < 3) {
            throw new MadLispException('case requires at least 2 arguments');
        }

        $caseValueSlot = $scope->allocate();
        $this->compileExpression($data[1], $code, $constants, $scope);
        $code[] = OpCode::STORE_LOCAL;
        $code[] = $caseValueSlot;

        $endJumps = [];
        $pendingFalseJump = null;
        $formName = $data[0]->getName();
        $strict = $formName == 'case-strict';

        for ($i = 2; $i < $length; $i++) {
            if ($pendingFalseJump !== null) {
                $code[$pendingFalseJump + 1] = count($code);
                $pendingFalseJump = null;
            }

            if (!($data[$i] instanceof Seq)) {
                throw new MadLispException("argument to $formName is not seq");
            }

            $clause = $data[$i]->getData();
            if (count($clause) < 2) {
                throw new MadLispException("clause for $formName requires at least 2 arguments");
            }

            $isElse = $clause[0] instanceof Symbol && $clause[0]->getName() == 'else';
            if (!$isElse) {
                $code[] = OpCode::LOAD_LOCAL;
                $code[] = $caseValueSlot;
                $this->compileExpression($clause[0], $code, $constants, $scope);
                $code[] = $strict ? OpCode::CASE_COMPARE_STRICT : OpCode::CASE_COMPARE;
                $pendingFalseJump = count($code);
                $code[] = OpCode::JUMP_IF_FALSE;
                $code[] = 0;
            }

            for ($j = 1; $j < count($clause) - 1; $j++) {
                $this->compileExpression($clause[$j], $code, $constants, $scope);
                $code[] = OpCode::POP;
            }

            $this->compileExpression($clause[count($clause) - 1], $code, $constants, $scope, $tailPosition);

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
    }

    private function compileCond(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition
    ): void
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
                $this->compileExpression($clause[0], $code, $constants, $scope);
                $pendingFalseJump = count($code);
                $code[] = OpCode::JUMP_IF_FALSE;
                $code[] = 0;
            }

            for ($j = 1; $j < count($clause) - 1; $j++) {
                $this->compileExpression($clause[$j], $code, $constants, $scope);
                $code[] = OpCode::POP;
            }

            $this->compileExpression($clause[count($clause) - 1], $code, $constants, $scope, $tailPosition);

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
    }

    private function compileDef(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): void
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

        $this->compileExpression($data[2], $code, $constants, $scope);

        $constants[] = $name;
        $code[] = OpCode::STORE_GLOBAL;
        $code[] = count($constants) - 1;
    }

    private function compileDo(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition
    ): void
    {
        if ($length == 1) {
            $constants[] = null;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return;
        }

        for ($i = 1; $i < $length - 1; $i++) {
            $this->compileExpression($data[$i], $code, $constants, $scope);
            $code[] = OpCode::POP;
        }

        $this->compileExpression($data[$length - 1], $code, $constants, $scope, $tailPosition);
    }

    private function compileExecutorOperation(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        int $opcode
    ): void
    {
        if ($length != 2) {
            $name = $data[0]->getName();
            throw new MadLispException("$name requires exactly 1 argument");
        }

        $this->compileExpression($data[1], $code, $constants, $scope);
        $code[] = $opcode;
    }

    private function compileFn(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): void
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
        $this->compileExpression(
            $data[2],
            $functionCode,
            $functionConstants,
            $functionScope,
            true
        );

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
    }

    private function compileHash(
        Hash $hash,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): void
    {
        $values = $hash->getData();
        foreach ($values as $value) {
            $this->compileExpression($value, $code, $constants, $scope);
        }

        $constants[] = array_keys($values);
        $code[] = OpCode::BUILD_HASH;
        $code[] = count($constants) - 1;
        $code[] = count($values);
    }

    private function compileIf(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition
    ): void
    {
        if ($length < 3 || $length > 4) {
            throw new MadLispException('if requires 2 or 3 arguments');
        }

        $this->compileExpression($data[1], $code, $constants, $scope);

        $jumpIfFalse = count($code);
        $code[] = OpCode::JUMP_IF_FALSE;
        $code[] = 0;

        $this->compileExpression($data[2], $code, $constants, $scope, $tailPosition);

        $jumpToEnd = count($code);
        $code[] = OpCode::JUMP;
        $code[] = 0;

        $elseAddress = count($code);
        $code[$jumpIfFalse + 1] = $elseAddress;

        if ($length == 4) {
            $this->compileExpression($data[3], $code, $constants, $scope, $tailPosition);
        } else {
            $constants[] = null;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
        }

        $code[$jumpToEnd + 1] = count($code);
    }

    private function compileLet(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition
    ): void
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
            $this->compileExpression($bindings[$i + 1], $code, $constants, $bodyScope);
            $code[] = OpCode::STORE_LOCAL;
            $code[] = $slot;
            $bodyScope->bind($name, $slot);
        }

        for ($i = 2; $i < $length - 1; $i++) {
            $this->compileExpression($data[$i], $code, $constants, $bodyScope);
            $code[] = OpCode::POP;
        }

        $this->compileExpression($data[$length - 1], $code, $constants, $bodyScope, $tailPosition);
    }

    private function compileOr(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope,
        bool $tailPosition
    ): void
    {
        if ($length == 1) {
            $constants[] = false;
            $code[] = OpCode::LOAD_CONSTANT;
            $code[] = count($constants) - 1;
            return;
        }

        $jumps = [];
        for ($i = 1; $i < $length - 1; $i++) {
            $this->compileExpression($data[$i], $code, $constants, $scope);
            $jumps[] = count($code);
            $code[] = OpCode::JUMP_IF_TRUE_KEEP;
            $code[] = 0;
            $code[] = OpCode::POP;
        }

        $this->compileExpression($data[$length - 1], $code, $constants, $scope, $tailPosition);

        $end = count($code);
        foreach ($jumps as $jump) {
            $code[$jump + 1] = $end;
        }
    }

    private function compileQuote(
        array $data,
        int $length,
        array &$code,
        array &$constants
    ): void
    {
        if ($length != 2) {
            throw new MadLispException('quote requires exactly 1 argument');
        }

        $constants[] = $data[1];
        $code[] = OpCode::LOAD_CONSTANT;
        $code[] = count($constants) - 1;
    }

    private function compileUndef(
        array $data,
        int $length,
        array &$code,
        array &$constants
    ): void
    {
        if ($length != 2) {
            throw new MadLispException('undef requires exactly 1 argument');
        }

        if (!($data[1] instanceof Symbol)) {
            throw new MadLispException('first argument to undef is not symbol');
        }

        $constants[] = $data[1]->getName();
        $code[] = OpCode::UNDEF;
        $code[] = count($constants) - 1;
    }

    private function compileVector(
        Vector $vector,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): void
    {
        $values = $vector->getData();
        foreach ($values as $value) {
            $this->compileExpression($value, $code, $constants, $scope);
        }

        $code[] = OpCode::BUILD_VECTOR;
        $code[] = count($values);
    }

    private function compileWhile(
        array $data,
        int $length,
        array &$code,
        array &$constants,
        CompileScope $scope
    ): void
    {
        if ($length < 3) {
            throw new MadLispException('while requires at least 2 arguments');
        }

        $resultSlot = $scope->allocate();
        $constants[] = null;
        $code[] = OpCode::LOAD_CONSTANT;
        $code[] = count($constants) - 1;
        $code[] = OpCode::STORE_LOCAL;
        $code[] = $resultSlot;

        $loopStart = count($code);
        $this->compileExpression($data[1], $code, $constants, $scope);
        $code[] = OpCode::JUMP_IF_FALSE;
        $exitJump = count($code);
        $code[] = 0;

        for ($i = 2; $i < $length - 1; $i++) {
            $this->compileExpression($data[$i], $code, $constants, $scope);
            $code[] = OpCode::POP;
        }

        $this->compileExpression($data[$length - 1], $code, $constants, $scope);

        $code[] = OpCode::STORE_LOCAL;
        $code[] = $resultSlot;
        $code[] = OpCode::JUMP;
        $code[] = $loopStart;

        $code[$exitJump] = count($code);
        $code[] = OpCode::LOAD_LOCAL;
        $code[] = $resultSlot;
    }
}
