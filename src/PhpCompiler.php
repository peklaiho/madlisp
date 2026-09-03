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
    // Tracks active function and named-let restart targets.
    private array $tailContexts;

    public function __construct(
        protected Options $options
    ) {

    }

    public function compile($ast): PhpCompiledProgram
    {
        // Reset internal state
        $this->temporaryCount = 0;
        $this->localCount = 0;
        $this->scopes = [[]];
        $this->functionScopes = [];
        $this->functionCaptures = [];
        $this->tailContexts = [];

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
        bool $tailPosition = false, array $metadata = []): void
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
                $this->compileAndOr($arguments, $body, $target, $indent, false, $tailPosition);
                return;
            case 'case':
            case 'case-strict':
                $this->compileCase($operator, $arguments, $body, $target, $indent, $tailPosition);
                return;
            case 'cond':
                $this->compileCond($arguments, $body, $target, $indent, $tailPosition);
                return;
            case 'def':
                $this->compileDef($arguments, $body, $target, $indent);
                return;
            case 'do':
                $this->compileDo($arguments, $body, $target, $indent, $tailPosition);
                return;
            case 'env':
                $this->compileEnv($arguments, $body, $target, $indent);
                return;
            case 'fn':
                $this->compileFn($arguments, $body, $target, $indent, $metadata);
                return;
            case 'if':
                $this->compileIf($arguments, $body, $target, $indent, $tailPosition);
                return;
            case 'let':
                $this->compileLet($arguments, $body, $target, $indent, $tailPosition);
                return;
            case 'or':
                $this->compileAndOr($arguments, $body, $target, $indent, true, $tailPosition);
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
                $this->compileComparisonExpression($arguments, '===', 2, '==', $body, $target, $indent);
                return;
            case '=':
                $this->compileComparisonExpression($arguments, '==', 2, '=', $body, $target, $indent);
                return;
            case '!=':
                $this->compileComparisonExpression($arguments, '!=', 2, '!=', $body, $target, $indent);
                return;
            case '!==':
                $this->compileComparisonExpression($arguments, '!==', 2, '!==', $body, $target, $indent);
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
        $this->compileCallGlobal($operator, $arguments, $body, $target, $indent, $tailPosition);
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
        int $indent, bool $or, bool $tailPosition = false): void
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
                $this->compileExpression($argument, $body, $target, $indent, $tailPosition);
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
        ?string $target, int $indent, bool $tailPosition = false): void
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
                    $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1, $tailPosition);
                    $this->emit($body, $indent, '}');
                } else {
                    $this->compileDo(array_slice($clause, 1), $body, $target, $indent, $tailPosition);
                }
                return;
            }

            $match = $this->quotedValueExpression($test);
            $keyword = $first ? 'if' : '} elseif';

            if ($this->options->compileSimpleComparisons) {
                $testExpr = "$value $comparison $match";
            } else {
                $testExpr = "\\MadLisp\\Util::valueForCompare($value) $comparison \\MadLisp\\Util::valueForCompare($match)";
            }

            $this->emit($body, $indent, "$keyword ($testExpr) {");
            $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1, $tailPosition);
            $first = false;
        }

        $this->emit($body, $indent, '} else {');
        if ($target !== null) {
            $this->emit($body, $indent + 1, "$target = null;");
        }
        $this->emit($body, $indent, '}');
    }

    private function compileCond(array $arguments, array &$body, ?string $target, int $indent,
        bool $tailPosition = false): void
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

        $this->compileCondBranch($clauses, 0, $body, $target, $indent, $tailPosition);
    }

    private function compileCondBranch(array $clauses, int $index, array &$body,
        ?string $target, int $indent, bool $tailPosition = false): void
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
            $this->compileDo(array_slice($clause, 1), $body, $target, $indent, $tailPosition);
            return;
        }

        $condition = $this->compileConditionExpression($test, $body, $indent);
        $this->emit($body, $indent, "if ($condition) {");
        $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1, $tailPosition);
        $this->emit($body, $indent, '} else {');
        $this->compileCondBranch($clauses, $index + 1, $body, $target, $indent + 1, $tailPosition);
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
        $this->compileExpression($arguments[1], $body, $value, $indent, false, [
            'definitionName' => $name,
        ]);
        $this->emitResult(
            $body,
            $indent,
            $target,
            "\$env->set(" . var_export($name, true) . ", $value)"
        );
    }

    private function compileDo(array $arguments, array &$body, ?string $target, int $indent,
        bool $tailPosition = false): void
    {
        if (!$arguments) {
            if ($target !== null) {
                $this->emit($body, $indent, "$target = null;");
            }
            return;
        }

        foreach ($arguments as $index => $argument) {
            $last = $index === (count($arguments) - 1);
            $this->compileExpression(
                $argument,
                $body,
                $last ? $target : null,
                $indent,
                $last && $tailPosition
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
        $this->tailContexts[] = [
            'kind' => 'function',
            'name' => $definitionName,
            'variable' => ($definitionName !== null) ? $closureTarget : null,
            'used' => false,
            'tailUsed' => false,
            'parameterVariables' => $parameterVariables,
        ];

        $functionBody = [];

        // Compile the function body in tail position so direct self-calls can
        // become parameter updates and continue instead of closure calls.
        $this->compileExpression($arguments[1], $functionBody, '$result', $indent + 1, true);

        $functionIndex = array_key_last($this->tailContexts);
        $selfContext = $this->tailContexts[$functionIndex];
        if ($selfContext['tailUsed']) {
            $functionBody = $this->wrapTailLoop($functionBody, $indent + 1);
        }
        $this->emit($functionBody, $indent + 1, 'return $result;');

        $functionIndex = array_key_last($this->tailContexts);
        $selfContext = $this->tailContexts[$functionIndex];
        $selfUsed = $selfContext['used'];
        $selfVariable = $selfContext['variable'];
        $captures = array_values($this->functionCaptures[array_key_last($this->functionCaptures)]);

        array_pop($this->tailContexts);
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

    private function compileIf(array $arguments, array &$body, ?string $target, int $indent,
        bool $tailPosition = false): void
    {
        if (count($arguments) < 2 || count($arguments) > 3) {
            throw new MadLispException('if requires 2 or 3 arguments');
        }

        $condition = $this->compileConditionExpression($arguments[0], $body, $indent);
        $this->emit($body, $indent, "if ($condition) {");
        $this->compileExpression($arguments[1], $body, $target, $indent + 1, $tailPosition);
        $this->emit($body, $indent, '} else {');

        if (isset($arguments[2])) {
            $this->compileExpression($arguments[2], $body, $target, $indent + 1, $tailPosition);
        } elseif ($target !== null) {
            $this->emit($body, $indent + 1, "$target = null;");
        }

        $this->emit($body, $indent, '}');
    }

    private function compileLet(array $arguments, array &$body, ?string $target, int $indent,
        bool $tailPosition = false): void
    {
        if (isset($arguments[0]) && $arguments[0] instanceof Symbol) {
            $this->compileNamedLet($arguments, $body, $target, $indent);
            return;
        }

        if (count($arguments) < 2) {
            throw new MadLispException('let requires at least 2 arguments');
        }

        $this->scopes[] = [];
        $this->compileLetBindings($arguments[0], 'let', $body, $indent);

        $this->compileDo(array_slice($arguments, 1), $body, $target, $indent, $tailPosition);
        array_pop($this->scopes);
    }

    private function compileNamedLet(array $arguments, array &$body, ?string $target, int $indent): void
    {
        if (count($arguments) < 3) {
            throw new MadLispException('named let requires name, bindings and body');
        }

        $name = $arguments[0]->getName();

        $this->scopes[] = [];
        $parameterVariables = $this->compileLetBindings($arguments[1], 'named let', $body, $indent);

        $this->tailContexts[] = [
            'kind' => 'named-let',
            'name' => $name,
            'variable' => null,
            'used' => false,
            'tailUsed' => false,
            'parameterVariables' => $parameterVariables,
        ];

        $loopBody = [];
        $this->compileDo(array_slice($arguments, 2), $loopBody, $target, $indent + 1, true);
        $contextIndex = array_key_last($this->tailContexts);
        $context = $this->tailContexts[$contextIndex];
        if ($context['tailUsed']) {
            $loopBody = $this->wrapTailLoop($loopBody, $indent);
        }
        array_push($body, ...$loopBody);

        array_pop($this->tailContexts);
        array_pop($this->scopes);
    }

    private function compileLetBindings($bindings, string $formName, array &$body, int $indent): array
    {
        if (!($bindings instanceof Seq)) {
            $errorArg = ($formName === 'let') ? 'first' : 'second';
            throw new MadLispException("$errorArg argument to $formName is not seq");
        }

        $bindingData = $bindings->getData();
        if (count($bindingData) % 2 !== 0) {
            throw new MadLispException("uneven number of bindings for $formName");
        }

        $variables = [];
        foreach ($bindingData as $index => $binding) {
            if ($index % 2 !== 0) {
                continue;
            }
            if (!($binding instanceof Symbol)) {
                throw new MadLispException("binding key for $formName is not symbol");
            }

            // Compile the value before adding the name to the scope.
            $local = '$v' . $this->localCount++;
            $value = $bindingData[$index + 1];
            $simple = $this->compileSimpleExpression($value);
            if ($simple !== null) {
                $this->emit($body, $indent, "$local = $simple;");
            } else {
                // The binding is not visible until after its initializer has
                // been compiled, so the final local is a safe destination.
                $this->compileExpression($value, $body, $local, $indent);
            }

            $this->scopes[array_key_last($this->scopes)][$binding->getName()] = $local;
            $variables[] = $local;
        }

        return $variables;
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

        $condition = $this->compileConditionExpression($arguments[0], $body, $indent + 1);
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

    private function wrapTailLoop(array $loopBody, int $indent): array
    {
        // The body is emitted one level inside the restart loop. A normal
        // completion falls through to break; a tail transition emits continue.
        $loopBody = array_map(fn ($line) => '    ' . $line, $loopBody);
        array_unshift($loopBody, str_repeat('    ', $indent) . 'while (true) {');
        $loopBody[] = str_repeat('    ', $indent + 1) . 'break;';
        $loopBody[] = str_repeat('    ', $indent) . '}';
        return $loopBody;
    }

    private function compileCallGlobal(string $name, array $arguments, array &$body, ?string $target,
        int $indent, bool $tailPosition = false): void
    {
        // Resolve the innermost named-let target or current function target.
        $contextIndex = null;
        for ($i = count($this->tailContexts) - 1; $i >= 0; $i--) {
            if ($this->tailContexts[$i]['kind'] === 'named-let' &&
                $this->tailContexts[$i]['name'] === $name) {
                $contextIndex = $i;
                break;
            }
            if ($this->tailContexts[$i]['kind'] === 'function') {
                if ($this->tailContexts[$i]['name'] === $name) {
                    $contextIndex = $i;
                }
                break;
            }
        }

        if ($contextIndex !== null) {
            $context = &$this->tailContexts[$contextIndex];

            // Compile every argument before changing any parameter. Besides
            // preserving evaluation order, this makes argument swapping safe.
            $values = $this->compileArguments($arguments, $body, $indent);

            if ($tailPosition) {
                if (count($values) !== count($context['parameterVariables'])) {
                    throw new MadLispException("$name requires exactly " .
                        count($context['parameterVariables']) . ' argument' .
                        (count($context['parameterVariables']) === 1 ? '' : 's'));
                }

                $context['tailUsed'] = true;

                // Evaluate all arguments before changing any parameters. The
                // resulting values are then assigned in a dependency-safe
                // order, using an extra temporary only for cycles such as a
                // two-parameter swap.
                $this->compileTailAssignments(
                    $values,
                    $context['parameterVariables'],
                    $body,
                    $indent
                );
                $this->emit($body, $indent, 'continue;');
                unset($context);
                return;
            }

            if ($context['kind'] === 'named-let') {
                unset($context);
                throw new MadLispException("named let $name can only be called in tail position");
            }

            $context['used'] = true;
            $selfVariable = $context['variable'];
            $this->emitResult($body, $indent, $target, "$selfVariable(" . implode(', ', $values) . ')');
            unset($context);
            return;
        }

        $function = $this->temporary();
        $this->emit($body, $indent, "$function = \$env->get(" . var_export($name, true) . ');');
        $values = $this->compileArguments($arguments, $body, $indent);
        $this->emitResult($body, $indent, $target, "$function(" . implode(', ', $values) . ')');
    }

    private function compileTailAssignments(array $values, array $parameters, array &$body, int $indent): void
    {
        $pending = [];
        foreach ($parameters as $index => $parameter) {
            if ($values[$index] !== $parameter) {
                $pending[$index] = [$parameter, $values[$index]];
            }
        }

        while ($pending) {
            $sources = array_column($pending, 1);
            $progress = false;

            foreach ($pending as $index => [$parameter, $value]) {
                // A parameter may be assigned now if no remaining assignment
                // still needs its old value as a source.
                if (!in_array($parameter, $sources, true)) {
                    $this->emit($body, $indent, "$parameter = $value;");
                    unset($pending[$index]);
                    $progress = true;
                    break;
                }
            }

            if ($progress) {
                continue;
            }

            // The remaining assignments form a cycle. Preserve one source,
            // then the dependency ordering above can finish the cycle.
            $index = array_key_first($pending);
            [$parameter, $value] = $pending[$index];
            $temporary = $this->temporary();
            $this->emit($body, $indent, "$temporary = $value;");
            $pending[$index][1] = $temporary;
        }
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

    private function compileConditionExpression($ast, array &$body, int $indent): string
    {
        $condition = $this->compileInlineExpression($ast);
        if ($condition !== null) {
            return $condition;
        }

        $condition = $this->temporary();
        $this->compileExpression($ast, $body, $condition, $indent);
        return $condition;
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

    private function compileInlineExpression($ast): ?string
    {
        $simple = $this->compileSimpleExpression($ast);
        if ($simple !== null) {
            return $simple;
        }

        if (!($ast instanceof MList)) {
            return null;
        }

        $data = $ast->getData();
        if (!$data || !($data[0] instanceof Symbol)) {
            return null;
        }

        $operator = $data[0]->getName();
        $arguments = array_slice($data, 1);
        $values = [];
        foreach ($arguments as $argument) {
            $value = $this->compileInlineExpression($argument);
            if ($value === null) {
                return null;
            }
            // Parenthesize nested forms so PHP operator precedence cannot
            // change the meaning of the generated expression.
            $values[] = $argument instanceof MList ? "($value)" : $value;
        }

        $binaryOperators = [
            '+' => [1, '+'],
            '-' => [1, '-'],
            '*' => [2, '*'],
            '/' => [2, '/'],
            '%' => [2, '%'],
            '<' => [2, '<'],
            '<=' => [2, '<='],
            '>' => [2, '>'],
            '>=' => [2, '>='],
        ];
        if (isset($binaryOperators[$operator])) {
            [$minimum, $phpOperator] = $binaryOperators[$operator];
            if (count($values) < $minimum) {
                return null;
            }
            if ($operator === '-' && count($values) === 1) {
                return "-{$values[0]}";
            }
            return implode(" $phpOperator ", $values);
        }

        $comparisons = [
            '=' => '==',
            '==' => '===',
            '!=' => '!=',
            '!==' => '!==',
        ];
        if (isset($comparisons[$operator])) {
            if (count($values) !== 2) {
                return null;
            }
            $comparison = $comparisons[$operator];
            if ($this->options->compileSimpleComparisons) {
                return "$values[0] $comparison $values[1]";
            }
            return "\\MadLisp\\Util::valueForCompare($values[0]) $comparison " .
                "\\MadLisp\\Util::valueForCompare($values[1])";
        }

        $formatted = [
            'inc' => [1, '%s + 1'],
            'dec' => [1, '%s - 1'],
            'not' => [1, '!%s'],
            'zero?' => [1, '%s === 0'],
            'one?' => [1, '%s === 1'],
            '//' => [2, 'intdiv(%s, %s)'],
        ];
        if (!isset($formatted[$operator]) || count($values) !== $formatted[$operator][0]) {
            return null;
        }

        return sprintf($formatted[$operator][1], ...$values);
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

    private function compileComparisonExpression(array $arguments, string $phpOperator, int $arity,
        string $lispName, array &$body, ?string $target, int $indent): void
    {
        if ($this->options->compileSimpleComparisons) {
            $template = "%s $phpOperator %s";
        } else {
            $template = "\\MadLisp\\Util::valueForCompare(%s) $phpOperator \\MadLisp\\Util::valueForCompare(%s)";
        }

        $this->compileFormattedExpression($arguments, $template, $arity, $lispName, $body, $target, $indent);
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
