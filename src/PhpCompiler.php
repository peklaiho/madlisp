<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class PhpCompiler
{
    // Counts generated temporary variables so each emitted name is unique.
    private int $temporaryCount;

    // Counts generated local variables so lexical bindings have unique names.
    private int $localCount;

    // Tracks lexical scopes so symbols can resolve to generated local variables.
    private array $scopes;

    // Tracks function boundaries so captured locals are identified correctly.
    private array $functionScopes;

    // Stores captured outer locals needed by generated nested closures.
    private array $functionCaptures;

    // Tracks named function contexts so direct self-calls avoid environment lookups.
    private array $functionSelfContexts;

    public function compile($ast): PhpCompiledProgram
    {
        // Reset internal state
        $this->temporaryCount = 0;
        $this->localCount = 0;
        $this->scopes = [[]];
        $this->functionScopes = [];
        $this->functionCaptures = [];
        $this->functionSelfContexts = [];

        // Collection of expressions
        $body = [];

        // Compile top-level do forms separately so the generated
        // source has a blank line between forms.
        if ($ast instanceof MList && count($ast->getData()) > 1 &&
            $ast->getData()[0] instanceof Symbol && $ast->getData()[0]->getName() === 'do') {
            $forms = array_slice($ast->getData(), 1);

            foreach ($forms as $index => $form) {
                if ($index === (count($forms) - 1)) {
                    $this->compileExpression($form, $body, '$result', 1);
                } else {
                    $bodyLength = count($body);
                    $this->compileExpression($form, $body, null, 1);
                    if (count($body) > $bodyLength) {
                        $body[] = '';
                    }
                }
            }
        } else {
            // Compile the outer expression
            $this->compileExpression($ast, $body, '$result', 1);
        }

        // Check that everything was cleaned up properly
        if (count($this->scopes) !== 1 || $this->functionScopes || $this->functionCaptures) {
            throw new MadLispException('compiler scope cleanup failed');
        }

        // Build the source code
        $source = implode(PHP_EOL, array_merge(
            ['return static function (\\MadLisp\\Env $env) {'],
            $body,
            ['    return $result;', '};']
        ));

        // Eval it to create a PHP closure
        $closure = eval($source);

        return new PhpCompiledProgram($closure, $source);
    }

    private function compileExpression($ast, array &$body, ?string $target, int $indent,
        array $metadata = []): void
    {
        // Simple PHP values are emitted with var_export
        if ($ast === null || is_bool($ast) || is_int($ast) || is_float($ast) || is_string($ast)) {
            if ($target !== null) {
                $this->emit($body, $indent, "$target = " . var_export($ast, true) . ';');
            }
            return;
        }

        // Symbol is a local or global lookup
        if ($ast instanceof Symbol) {
            $local = $this->resolveLocal($ast->getName());
            if ($local !== null) {
                if ($target !== null) {
                    $this->emit($body, $indent, "$target = $local;");
                }
            } else {
                $expression = "\$env->get(" . var_export($ast->getName(), true) . ')';
                $this->emitResult($body, $indent, $target, $expression);
            }
            return;
        }

        // Compilation of Vector, Hash
        if ($ast instanceof Vector) {
            $this->compileVector($ast, $body, $target, $indent);
            return;
        } elseif ($ast instanceof Hash) {
            $this->compileHash($ast, $body, $target, $indent);
            return;
        }

        // We should have a list if we get here
        if (!($ast instanceof MList)) {
            throw new MadLispException('compiler does not support value: ' . $this->typeToString($ast));
        }

        $data = $ast->getData();

        // Unquoted empty list is an error
        if (!$data) {
            throw new MadLispException('attempt to compile empty unquoted list');
        }

        // A non-symbol in function position is an expression
        // that evaluates to a callable.
        if (!($data[0] instanceof Symbol)) {
            $this->compileCallExpression($data, $body, $target, $indent);
            return;
        }

        // If we get here, we have a non-empty list with a symbol
        // as the first item. We are dealing with either a special
        // form or a function call!

        $operator = $data[0]->getName();
        $arguments = array_slice($data, 1);

        // Handle special forms first: They are syntax and always
        // take precedence over local and global bindings.
        switch ($operator) {
            case 'and':
                $this->compileAndOr($arguments, $body, $target, $indent, false);
                return;
            case 'case':
            case 'case-strict':
                $this->compileCase($operator, $arguments, $body, $target, $indent);
                return;
            case 'cond':
                $this->compileCond($arguments, $body, $target, $indent);
                return;
            case 'def':
                $this->compileDef($arguments, $body, $target, $indent);
                return;
            case 'do':
                $this->compileDo($arguments, $body, $target, $indent);
                return;
            case 'env':
                $this->compileEnv($arguments, $body, $target, $indent);
                return;
            case 'fn':
                $this->compileFn($arguments, $body, $target, $indent, $metadata);
                return;
            case 'if':
                $this->compileIf($arguments, $body, $target, $indent);
                return;
            case 'let':
                $this->compileLet($arguments, $body, $target, $indent);
                return;
            case 'or':
                $this->compileAndOr($arguments, $body, $target, $indent, true);
                return;
            case 'quote':
                $this->compileQuote($arguments, $body, $target, $indent);
                return;
            case 'try':
                $this->compileTry($arguments, $body, $target, $indent);
                return;
            case 'undef':
                $this->compileUndef($arguments, $body, $target, $indent);
                return;
            case 'while':
                $this->compileWhile($arguments, $body, $target, $indent);
                return;
        }

        // Resolve local functions next so local bindings can
        // shadow fast-path functions
        if ($this->resolveLocal($operator) !== null) {
            $this->compileCallLocal($data, $body, $target, $indent);
            return;
        }

        // Use optimized implementations for recognized built-in operations
        switch ($operator) {
            // Math operators
            case '+':
                $this->compileArithmetic($arguments, '+', 1, $body, $target, $indent);
                return;
            case '-':
                $this->compileArithmetic($arguments, '-', 1, $body, $target, $indent);
                return;
            case '*':
                $this->compileArithmetic($arguments, '*', 2, $body, $target, $indent);
                return;
            case '/':
                $this->compileArithmetic($arguments, '/', 2, $body, $target, $indent);
                return;
            case '//':
                $this->compileCallNative($arguments, ['intdiv'], 2, '//', $body, $target, $indent);
                return;
            case '%':
                $this->compileArithmetic($arguments, '%', 2, $body, $target, $indent);
                return;
            case 'inc':
                $this->compileFormattedExpression($arguments, '%s + 1', 1, 'inc', $body, $target, $indent);
                return;
            case 'dec':
                $this->compileFormattedExpression($arguments, '%s - 1', 1, 'dec', $body, $target, $indent);
                return;

            // Math other
            case 'not':
                $this->compileFormattedExpression($arguments, '!%s', 1, 'not', $body, $target, $indent);
                return;
            case 'abs':
                $this->compileCallNative($arguments, ['abs'], 1, 'abs', $body, $target, $indent);
                return;
            case 'floor':
                $this->compileCallNative($arguments, ['intval', 'floor'], 1, 'floor', $body, $target, $indent);
                return;
            case 'ceil':
                $this->compileCallNative($arguments, ['intval', 'ceil'], 1, 'ceil', $body, $target, $indent);
                return;
            case 'pow':
                $this->compileCallNative($arguments, ['pow'], 2, 'pow', $body, $target, $indent);
                return;
            case 'sqrt':
                $this->compileCallNative($arguments, ['sqrt'], 1, 'sqrt', $body, $target, $indent);
                return;

            // Comparisons
            case '==':
                $this->compileFormattedExpression($arguments, '%s === %s', 2, '==', $body, $target, $indent);
                return;
            case '=':
                $this->compileFormattedExpression($arguments, '%s == %s', 2, '=', $body, $target, $indent);
                return;
            case '!=':
                $this->compileFormattedExpression($arguments, '%s != %s', 2, '!=', $body, $target, $indent);
                return;
            case '!==':
                $this->compileFormattedExpression($arguments, '%s !== %s', 2, '!==', $body, $target, $indent);
                return;
            case '<':
                $this->compileFormattedExpression($arguments, '%s < %s', 2, '<', $body, $target, $indent);
                return;
            case '<=':
                $this->compileFormattedExpression($arguments, '%s <= %s', 2, '<=', $body, $target, $indent);
                return;
            case '>':
                $this->compileFormattedExpression($arguments, '%s > %s', 2, '>', $body, $target, $indent);
                return;
            case '>=':
                $this->compileFormattedExpression($arguments, '%s >= %s', 2, '>=', $body, $target, $indent);
                return;

            // Predicates
            case 'zero?':
                $this->compileFormattedExpression($arguments, '%s === 0', 1, 'zero?', $body, $target, $indent);
                return;
            case 'one?':
                $this->compileFormattedExpression($arguments, '%s === 1', 1, 'one?', $body, $target, $indent);
                return;
            case 'even?':
                $this->compileFormattedExpression($arguments, '%s %% 2 === 0', 1, 'even?', $body, $target, $indent);
                return;
            case 'odd?':
                $this->compileFormattedExpression($arguments, '%s %% 2 !== 0', 1, 'odd?', $body, $target, $indent);
                return;

            // Collections
            case 'empty?':
                $this->compileFormattedExpression($arguments, '%s->count() === 0', 1, 'empty?', $body, $target, $indent);
                return;
            case 'len':
                $this->compileFormattedExpression($arguments, '%s->count()', 1, 'len', $body, $target, $indent);
                return;
            case 'car':
                $this->compileFormattedExpression($arguments, '%s->getData()[0]', 1, 'car', $body, $target, $indent);
                return;
            case 'cdr':
                // Positional sprintf placeholder reuses the argument without compiling it twice.
                $this->compileFormattedExpression($arguments, '%1$s::new(array_slice(%1$s->getData(), 1))', 1, 'cdr', $body, $target, $indent);
                return;
            case 'cons':
                $this->compileCons($arguments, $body, $target, $indent);
                return;
            case 'last':
                // Positional sprintf placeholder reuses the argument without compiling it twice.
                $this->compileFormattedExpression($arguments, '%1$s->getData()[%1$s->count() - 1]', 1, 'last', $body, $target, $indent);
                return;
            case 'get':
                $this->compileFormattedExpression($arguments, '%s->get(%s)', 2, 'get', $body, $target, $indent);
                return;
            case 'key?':
                $this->compileFormattedExpression($arguments, '%s->has(%s)', 2, 'key?', $body, $target, $indent);
                return;

            // Strings
            case 'strlen':
                $this->compileCallNative($arguments, ['strlen'], 1, 'strlen', $body, $target, $indent);
                return;
        }

        // No special form, local function, or fast path matched
        // Resolve the function from the global environment
        $this->compileCallGlobal($operator, $arguments, $body, $target, $indent);
    }

    // ---
    // Helpers for Vector and Hash
    // ---

    private function compileVector(Vector $vector, array &$body, ?string $target, int $indent): void
    {
        $values = $this->compileArguments($vector->getData(), $body, $indent);

        $this->emitResult(
            $body,
            $indent,
            $target,
            "new \\MadLisp\\Vector([" . implode(', ', $values) . '])'
        );
    }

    private function compileHash(Hash $hash, array &$body, ?string $target, int $indent): void
    {
        $values = $this->compileArguments(array_values($hash->getData()), $body, $indent);

        $items = [];
        foreach (array_keys($hash->getData()) as $index => $key) {
            $items[] = var_export($key, true) . ' => ' . $values[$index];
        }

        $this->emitResult(
            $body,
            $indent,
            $target,
            "new \\MadLisp\\Hash([" . implode(', ', $items) . '])'
        );
    }

    // ---
    // Helpers for special forms in alphabetical order
    // ---

    private function compileAndOr(array $arguments, array &$body, ?string $target,
        int $indent, bool $or): void
    {
        if (!$arguments) {
            if ($target !== null) {
                $this->emit($body, $indent, $target . ' = ' . ($or ? 'false' : 'true') . ';');
            }
            return;
        }

        $openBlocks = 0;

        foreach ($arguments as $index => $argument) {
            if ($index === (count($arguments) - 1)) {
                $this->compileExpression($argument, $body, $target, $indent);
                break;
            }

            $condition = $this->temporary();
            $this->compileExpression($argument, $body, $condition, $indent);
            $match = $or ? 'true' : 'false';
            $this->emit($body, $indent, "if ($condition == $match) {");
            if ($target !== null) {
                $this->emit($body, $indent + 1, "$target = $condition;");
            }
            $this->emit($body, $indent, '} else {');
            $indent++;
            $openBlocks++;
        }

        while ($openBlocks-- > 0) {
            $indent--;
            $this->emit($body, $indent, '}');
        }
    }

    private function compileCase(string $operator, array $arguments, array &$body,
        ?string $target, int $indent): void
    {
        if (count($arguments) < 2) {
            throw new MadLispException("$operator requires at least 2 arguments");
        }

        $clauses = [];
        foreach (array_slice($arguments, 1) as $argument) {
            if (!($argument instanceof Seq)) {
                throw new MadLispException("argument to $operator is not seq");
            }

            $data = $argument->getData();
            if (count($data) < 2) {
                throw new MadLispException("clause for $operator requires at least 2 arguments");
            }

            $clauses[] = $data;
        }

        $value = $this->temporary();
        $this->compileExpression($arguments[0], $body, $value, $indent);
        $comparison = ($operator === 'case-strict') ? '===' : '==';
        $first = true;

        foreach ($clauses as $clause) {
            $test = $clause[0];

            if ($test instanceof Symbol && $test->getName() === 'else') {
                if (!$first) {
                    $this->emit($body, $indent, '} else {');
                    $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1);
                    $this->emit($body, $indent, '}');
                } else {
                    $this->compileDo(array_slice($clause, 1), $body, $target, $indent);
                }
                return;
            }

            $match = $this->quotedValueExpression($test);
            $keyword = $first ? 'if' : '} elseif';
            $this->emit($body, $indent, "$keyword ($value $comparison $match) {");
            $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1);
            $first = false;
        }

        $this->emit($body, $indent, '} else {');
        if ($target !== null) {
            $this->emit($body, $indent + 1, "$target = null;");
        }
        $this->emit($body, $indent, '}');
    }

    private function compileCond(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (!$arguments) {
            throw new MadLispException('cond requires at least 1 argument');
        }

        $clauses = [];

        foreach ($arguments as $argument) {
            if (!($argument instanceof Seq)) {
                throw new MadLispException('argument to cond is not seq');
            }

            $data = $argument->getData();
            if (count($data) < 2) {
                throw new MadLispException('clause for cond requires at least 2 arguments');
            }

            $clauses[] = $data;
        }

        $this->compileCondBranch($clauses, 0, $body, $target, $indent);
    }

    private function compileCondBranch(array $clauses, int $index, array &$body,
        ?string $target, int $indent): void
    {
        if ($index === count($clauses)) {
            if ($target !== null) {
                $this->emit($body, $indent, "$target = null;");
            }
            return;
        }

        $clause = $clauses[$index];
        $test = $clause[0];

        if ($test instanceof Symbol && $test->getName() === 'else') {
            $this->compileDo(array_slice($clause, 1), $body, $target, $indent);
            return;
        }

        $condition = $this->compileSimpleExpression($test);
        if ($condition === null) {
            $condition = $this->temporary();
            $this->compileExpression($test, $body, $condition, $indent);
        }
        $this->emit($body, $indent, "if ($condition) {");
        $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1);
        $this->emit($body, $indent, '} else {');
        $this->compileCondBranch($clauses, $index + 1, $body, $target, $indent + 1);
        $this->emit($body, $indent, '}');
    }

    private function compileDef(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) !== 2) {
            throw new MadLispException('def requires exactly 2 arguments');
        } elseif (!($arguments[0] instanceof Symbol)) {
            throw new MadLispException('first argument to def is not symbol');
        }

        $name = $arguments[0]->getName();

        if ($name === '__FILE__' || $name === '__DIR__') {
            throw new MadLispException("cannot define reserved name $name");
        }

        $value = $this->temporary();
        $this->compileExpression($arguments[1], $body, $value, $indent, [
            'definitionName' => $name,
        ]);
        $this->emitResult(
            $body,
            $indent,
            $target,
            "\$env->set(" . var_export($name, true) . ", $value)"
        );
    }

    private function compileDo(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (!$arguments) {
            if ($target !== null) {
                $this->emit($body, $indent, "$target = null;");
            }
            return;
        }

        foreach ($arguments as $index => $argument) {
            $this->compileExpression(
                $argument,
                $body,
                ($index === (count($arguments) - 1)) ? $target : null,
                $indent
            );
        }
    }

    private function compileEnv(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if ($arguments) {
            throw new MadLispException('env does not take arguments');
        }

        if ($target !== null) {
            $this->emit($body, $indent, "$target = \$env;");
        }
    }

    private function compileFn(array $arguments, array &$body, ?string $target, int $indent,
        array $metadata = []): void
    {
        if (count($arguments) !== 2) {
            throw new MadLispException('fn requires exactly 2 arguments');
        }

        $parameters = $arguments[0];

        if (!($parameters instanceof Seq)) {
            throw new MadLispException('first argument to fn is not seq');
        }

        $parameterNames = [];
        $parameterVariables = [];

        foreach ($parameters->getData() as $parameter) {
            if (!($parameter instanceof Symbol)) {
                throw new MadLispException('parameter for fn is not symbol');
            }

            $name = $parameter->getName();

            if ($name === '&') {
                throw new MadLispException('variadic parameters are not supported for compiled fn');
            } elseif (array_key_exists($name, $parameterNames)) {
                throw new MadLispException("duplicate parameter $name for fn");
            }

            $variable = '$v' . $this->localCount++;
            $parameterNames[$name] = $variable;
            $parameterVariables[] = $variable;
        }

        $this->scopes[] = $parameterNames;
        $this->functionScopes[] = count($this->scopes) - 1;
        $this->functionCaptures[] = [];

        // Keep nested function contexts separate. A named function gets a
        // possible self-reference, but it is captured only if its body uses it.
        $definitionName = $metadata['definitionName'] ?? null;
        $closureTarget = $target;
        $this->functionSelfContexts[] = [
            'name' => $definitionName,
            'variable' => ($definitionName !== null) ? $closureTarget : null,
            'used' => false,
        ];

        $functionBody = [];

        $this->compileExpression($arguments[1], $functionBody, '$result', $indent + 1);
        $this->emit($functionBody, $indent + 1, 'return $result;');

        $functionIndex = array_key_last($this->functionSelfContexts);
        $selfContext = $this->functionSelfContexts[$functionIndex];
        $selfUsed = $selfContext['used'];
        $selfVariable = $selfContext['variable'];
        $captures = array_values($this->functionCaptures[array_key_last($this->functionCaptures)]);

        array_pop($this->functionSelfContexts);
        array_pop($this->functionCaptures);
        array_pop($this->functionScopes);
        array_pop($this->scopes);

        // A discarded anonymous function cannot be called or registered.
        // Its body was still compiled above so compile-time validation occurs.
        if ($target === null) {
            return;
        }

        $use = ['$env'];
        // The closure must capture its generated variable by reference so the
        // variable can be assigned the closure immediately after construction.
        if ($selfUsed) {
            $this->emit($body, $indent, "$selfVariable = null;");
            $use[] = '&' . $selfVariable;
        }
        $use = array_merge($use, $captures);
        $this->emit(
            $body,
            $indent,
            "$closureTarget = static function (" . implode(', ', $parameterVariables) . ") use (" . implode(', ', $use) . ') {'
        );
        array_push($body, ...$functionBody);
        $this->emit($body, $indent, '};');
    }

    private function compileIf(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) < 2 || count($arguments) > 3) {
            throw new MadLispException('if requires 2 or 3 arguments');
        }

        $condition = $this->compileSimpleExpression($arguments[0]);
        if ($condition === null) {
            $condition = $this->temporary();
            $this->compileExpression($arguments[0], $body, $condition, $indent);
        }
        $this->emit($body, $indent, "if ($condition) {");
        $this->compileExpression($arguments[1], $body, $target, $indent + 1);
        $this->emit($body, $indent, '} else {');

        if (isset($arguments[2])) {
            $this->compileExpression($arguments[2], $body, $target, $indent + 1);
        } elseif ($target !== null) {
            $this->emit($body, $indent + 1, "$target = null;");
        }

        $this->emit($body, $indent, '}');
    }

    private function compileLet(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) < 2) {
            throw new MadLispException('let requires at least 2 arguments');
        }

        $bindings = $arguments[0];
        if (!($bindings instanceof Seq)) {
            throw new MadLispException('first argument to let is not seq');
        }

        $bindingData = $bindings->getData();
        if (count($bindingData) % 2 !== 0) {
            throw new MadLispException('uneven number of bindings for let');
        }

        $this->scopes[] = [];

        for ($i = 0; $i < count($bindingData); $i += 2) {
            if (!($bindingData[$i] instanceof Symbol)) {
                throw new MadLispException('binding key for let is not symbol');
            }

            // Compile the value before adding the name to the scope.
            $local = '$v' . $this->localCount++;
            $simple = $this->compileSimpleExpression($bindingData[$i + 1]);

            if ($simple !== null) {
                $this->emit($body, $indent, "$local = $simple;");
            } else {
                // The binding is not visible until after its initializer has
                // been compiled, so the final local is a safe destination.
                $this->compileExpression($bindingData[$i + 1], $body, $local, $indent);
            }

            $this->scopes[array_key_last($this->scopes)][$bindingData[$i]->getName()] = $local;
        }

        $this->compileDo(array_slice($arguments, 1), $body, $target, $indent);
        array_pop($this->scopes);
    }

    private function compileQuote(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) !== 1) {
            throw new MadLispException('quote requires exactly 1 argument');
        }

        $this->emitResult($body, $indent, $target, $this->quotedValueExpression($arguments[0]));
    }

    private function compileTry(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) !== 2) {
            throw new MadLispException('try requires exactly 2 arguments');
        } elseif (!($arguments[1] instanceof Seq)) {
            throw new MadLispException('second argument to try is not seq');
        }

        $catch = $arguments[1]->getData();

        if (count($catch) !== 3 || !($catch[0] instanceof Symbol) ||
            $catch[0]->getName() !== 'catch' || !($catch[1] instanceof Symbol)) {
            throw new MadLispException('invalid form for catch');
        }

        $this->emit($body, $indent, 'try {');
        $this->compileExpression($arguments[0], $body, $target, $indent + 1);
        $this->emit($body, $indent, '} catch (\\Throwable $exception) {');

        $exceptionVariable = '$v' . $this->localCount++;
        $this->scopes[] = [$catch[1]->getName() => $exceptionVariable];
        $this->emit($body, $indent + 1, "$exceptionVariable = \$exception;");
        $this->compileExpression($catch[2], $body, $target, $indent + 1);
        array_pop($this->scopes);

        $this->emit($body, $indent, '}');
    }

    private function compileUndef(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) !== 1) {
            throw new MadLispException('undef requires exactly 1 argument');
        } elseif (!($arguments[0] instanceof Symbol)) {
            throw new MadLispException('first argument to undef is not symbol');
        }

        $this->emitResult(
            $body,
            $indent,
            $target,
            "\$env->unset(" . var_export($arguments[0]->getName(), true) . ')'
        );
    }

    private function compileWhile(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) < 2) {
            throw new MadLispException('while requires at least 2 arguments');
        }

        if ($target !== null) {
            $this->emit($body, $indent, "$target = null;");
        }
        $this->emit($body, $indent, 'while (true) {');

        $condition = $this->compileSimpleExpression($arguments[0]);
        if ($condition === null) {
            $condition = $this->temporary();
            $this->compileExpression($arguments[0], $body, $condition, $indent + 1);
        }
        $this->emit($body, $indent + 1, "if (!$condition) {");
        $this->emit($body, $indent + 2, 'break;');
        $this->emit($body, $indent + 1, '}');
        $this->compileDo(array_slice($arguments, 1), $body, $target, $indent + 1);
        $this->emit($body, $indent, '}');
    }

    // ---
    // Other helpers
    // ---

    private function quotedValueExpression($value): string
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
            return var_export($value, true);
        }
        if ($value instanceof Symbol) {
            return 'new \\MadLisp\\Symbol(' . var_export($value->getName(), true) . ')';
        }
        if ($value instanceof MList) {
            return 'new \\MadLisp\\MList([' . implode(', ', array_map(
                fn ($item) => $this->quotedValueExpression($item),
                $value->getData()
            )) . '])';
        }
        if ($value instanceof Vector) {
            return 'new \\MadLisp\\Vector([' . implode(', ', array_map(
                fn ($item) => $this->quotedValueExpression($item),
                $value->getData()
            )) . '])';
        }
        if ($value instanceof Hash) {
            $items = [];
            foreach ($value->getData() as $key => $item) {
                $items[] = var_export($key, true) . ' => ' . $this->quotedValueExpression($item);
            }
            return 'new \\MadLisp\\Hash([' . implode(', ', $items) . '])';
        }

        throw new MadLispException('compiler encountered invalid quoted value: ' . $this->typeToString($value));
    }

    private function compileCallExpression(array $data, array &$body, ?string $target, int $indent): void
    {
        $functionExpression = $data[0];
        $function = $this->temporary();
        $this->compileExpression($functionExpression, $body, $function, $indent);
        $values = $this->compileArguments(array_slice($data, 1), $body, $indent);
        $this->emitResult($body, $indent, $target, "$function(" . implode(', ', $values) . ')');
    }

    private function compileCallLocal(array $data, array &$body, ?string $target, int $indent): void
    {
        $functionExpression = $data[0];
        $function = $this->resolveLocal($functionExpression->getName());
        $values = $this->compileArguments(array_slice($data, 1), $body, $indent);
        $this->emitResult($body, $indent, $target, "$function(" . implode(', ', $values) . ')');
    }

    private function compileCallGlobal(string $name, array $arguments, array &$body, ?string $target,
        int $indent): void
    {
        // Direct self-calls use the captured closure variable. All other
        // global calls retain their dynamic environment lookup.
        $functionIndex = array_key_last($this->functionSelfContexts);
        if ($functionIndex !== null && $this->functionSelfContexts[$functionIndex]['name'] === $name) {
            $this->functionSelfContexts[$functionIndex]['used'] = true;
            $values = $this->compileArguments($arguments, $body, $indent);
            $selfVariable = $this->functionSelfContexts[$functionIndex]['variable'];
            $this->emitResult($body, $indent, $target, "$selfVariable(" . implode(', ', $values) . ')');
            return;
        }

        $function = $this->temporary();
        $this->emit($body, $indent, "$function = \$env->get(" . var_export($name, true) . ');');
        $values = $this->compileArguments($arguments, $body, $indent);
        $this->emitResult($body, $indent, $target, "$function(" . implode(', ', $values) . ')');
    }

    private function compileCallNative(array $arguments, array $functions, int $arity, string $lispName,
        array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) !== $arity) {
            throw new MadLispException("$lispName requires exactly $arity argument" .
                (($arity === 1) ? '' : 's'));
        }

        $values = $this->compileArguments($arguments, $body, $indent);
        $expression = implode(', ', $values);
        foreach (array_reverse($functions) as $function) {
            $expression = "$function($expression)";
        }

        $this->emitResult($body, $indent, $target, $expression);
    }

    private function compileArguments(array $arguments, array &$body, int $indent): array
    {
        $values = [];
        foreach ($arguments as $argument) {
            $simple = $this->compileSimpleExpression($argument);
            if ($simple !== null) {
                $values[] = $simple;
                continue;
            }

            $value = $this->temporary();
            $this->compileExpression($argument, $body, $value, $indent);
            $values[] = $value;
        }

        return $values;
    }

    private function compileSimpleExpression($ast): ?string
    {
        if ($ast === null || is_bool($ast) || is_int($ast) || is_float($ast) || is_string($ast)) {
            return var_export($ast, true);
        }

        if ($ast instanceof Symbol) {
            return $this->resolveLocal($ast->getName());
        }

        return null;
    }

    private function compileArithmetic(array $arguments, string $operator, int $minArgs,
        array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) < $minArgs) {
            throw new MadLispException("$operator requires at least $minArgs argument" .
                (($minArgs === 1) ? '' : 's'));
        }

        $values = $this->compileArguments($arguments, $body, $indent);

        if ($operator === '-' && count($values) === 1) {
            $this->emitResult($body, $indent, $target, "-{$values[0]}");
            return;
        }

        $this->emitResult($body, $indent, $target, implode(" $operator ", $values));
    }

    private function compileFormattedExpression(array $arguments, string $template, int $arity,
        string $lispName, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) !== $arity) {
            throw new MadLispException("$lispName requires exactly $arity argument" .
                (($arity === 1) ? '' : 's'));
        }

        $values = $this->compileArguments($arguments, $body, $indent);
        $expression = sprintf($template, ...$values);
        $this->emitResult($body, $indent, $target, $expression);
    }

    private function compileCons(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) < 2) {
            throw new MadLispException('cons requires at least 2 arguments');
        }

        $values = $this->compileArguments($arguments, $body, $indent);
        $sequence = $values[count($values) - 1];
        $prefix = array_slice($values, 0, -1);
        $data = implode(', ', $prefix);

        $this->emitResult(
            $body,
            $indent,
            $target,
            "{$sequence}::new(array_merge([$data], {$sequence}->getData()))"
        );
    }

    private function temporary(): string
    {
        return '$t' . $this->temporaryCount++;
    }

    private function resolveLocal(string $name): ?string
    {
        for ($i = count($this->scopes) - 1; $i >= 0; $i--) {
            if (!array_key_exists($name, $this->scopes[$i])) {
                continue;
            }

            $local = $this->scopes[$i][$name];
            foreach ($this->functionScopes as $functionIndex => $functionScope) {
                if ($i < $functionScope) {
                    $this->functionCaptures[$functionIndex][$name] = $local;
                }
            }

            return $local;
        }

        return null;
    }

    private function typeToString($value): string
    {
        if (is_object($value)) {
            $class = get_class($value);
            return "<object<$class>>";
        }

        return gettype($value);
    }

    private function emitResult(array &$body, int $indent, ?string $target, string $expression): void
    {
        $this->emit($body, $indent, ($target === null ? '' : "$target = ") . $expression . ';');
    }

    private function emit(array &$body, int $indent, string $statement): void
    {
        $body[] = str_repeat('    ', $indent) . $statement;
    }
}
