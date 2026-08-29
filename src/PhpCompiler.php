<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class PhpCompiler
{
    private const RESERVED_DEFINITION_NAMES = [
        'if', 'let', 'do', 'fn', 'quote', 'def',
        '+', '-', '*', '/', '==', '=', '<', '<=', '>', '>=',
        'inc', 'dec', 'not',
    ];

    private int $temporaryCount;
    private int $localCount;
    private array $scopes;
    private array $functionScopes;
    private array $functionCaptures;

    public function compile($ast): PhpCompiledProgram
    {
        $this->temporaryCount = 0;
        $this->localCount = 0;
        $this->scopes = [[]];
        $this->functionScopes = [];
        $this->functionCaptures = [];
        $body = [];
        $this->compileExpression($ast, $body, '$result', 1);

        if (count($this->scopes) !== 1 || $this->functionScopes || $this->functionCaptures) {
            throw new MadLispException('php compiler scope cleanup failed');
        }

        $source = implode("\n", array_merge(
            ['return static function (\\MadLisp\\Env $env) {'],
            $body,
            ['    return $result;', '};']
        ));

        /** @var \Closure $closure */
        $closure = eval($source);

        return new PhpCompiledProgram($closure, $source);
    }

    private function compileExpression($ast, array &$body, string $target, int $indent): void
    {
        if ($ast === null || is_bool($ast) || is_int($ast) || is_float($ast) || is_string($ast)) {
            $this->emit($body, $indent, "$target = " . var_export($ast, true) . ';');
            return;
        }

        if ($ast instanceof Symbol) {
            $local = $this->resolveLocal($ast->getName());
            if ($local !== null) {
                $this->emit($body, $indent, "$target = $local;");
            } else {
                $this->emit(
                    $body,
                    $indent,
                    "$target = \$env->get(" . var_export($ast->getName(), true) . ');'
                );
            }
            return;
        }

        if ($ast instanceof Vector) {
            $this->compileVector($ast, $body, $target, $indent);
            return;
        }

        if ($ast instanceof Hash) {
            $this->compileHash($ast, $body, $target, $indent);
            return;
        }

        if (!($ast instanceof MList)) {
            throw new MadLispException('php compiler does not support this value');
        }

        $data = $ast->getData();
        if (!$data) {
            throw new MadLispException('php compiler requires a supported operator');
        }

        if (!($data[0] instanceof Symbol)) {
            $this->compileCall($data, $body, $target, $indent);
            return;
        }

        $operator = $data[0]->getName();
        $arguments = array_slice($data, 1);

        if ($operator === 'and') {
            $this->compileAnd($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'or') {
            $this->compileOr($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'cond') {
            $this->compileCond($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'case' || $operator === 'case-strict') {
            $this->compileCase($operator, $arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'quote') {
            $this->compileQuote($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'def') {
            $this->compileDef($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'if') {
            $this->compileIf($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'let') {
            $this->compileLet($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'do') {
            $this->compileDo($arguments, $body, $target, $indent);
            return;
        }

        if ($operator === 'fn') {
            $this->compileFn($arguments, $body, $target, $indent);
            return;
        }

        if ($this->resolveLocal($operator) !== null) {
            $this->compileCall($data, $body, $target, $indent);
            return;
        }

        if (!in_array($operator, self::RESERVED_DEFINITION_NAMES, true)) {
            $this->compileDynamicCall($operator, $arguments, $body, $target, $indent);
            return;
        }

        $values = $this->compileArguments($arguments, $body, $indent);

        switch ($operator) {
            case '+':
                $this->compileArithmetic($values, '+', 1, false, $body, $target, $indent);
                return;
            case '-':
                $this->compileArithmetic($values, '-', 1, true, $body, $target, $indent);
                return;
            case '*':
                $this->compileArithmetic($values, '*', 1, false, $body, $target, $indent);
                return;
            case '/':
                $this->compileArithmetic($values, '/', 1, false, $body, $target, $indent);
                return;
            case '==':
                $this->compileBinary($values, '===', '== requires exactly 2 arguments', $body, $target, $indent);
                return;
            case '=':
                $this->compileBinary($values, '==', '= requires exactly 2 arguments', $body, $target, $indent);
                return;
            case '<':
                $this->compileBinary($values, '<', '< requires exactly 2 arguments', $body, $target, $indent);
                return;
            case '<=':
                $this->compileBinary($values, '<=', '<= requires exactly 2 arguments', $body, $target, $indent);
                return;
            case '>':
                $this->compileBinary($values, '>', '> requires exactly 2 arguments', $body, $target, $indent);
                return;
            case '>=':
                $this->compileBinary($values, '>=', '>= requires exactly 2 arguments', $body, $target, $indent);
                return;
            case 'inc':
                $this->compileUnary($values, '+ 1', 'inc requires exactly 1 argument', $body, $target, $indent);
                return;
            case 'dec':
                $this->compileUnary($values, '- 1', 'dec requires exactly 1 argument', $body, $target, $indent);
                return;
            case 'not':
                $this->compileUnary($values, '!', 'not requires exactly 1 argument', $body, $target, $indent, true);
                return;
            default:
                throw new MadLispException("php compiler does not support $operator");
        }
    }

    private function compileVector(Vector $vector, array &$body, string $target, int $indent): void
    {
        $values = $this->compileArguments($vector->getData(), $body, $indent);
        $this->emit(
            $body,
            $indent,
            "$target = new \\MadLisp\\Vector([" . implode(', ', $values) . ']);'
        );
    }

    private function compileHash(Hash $hash, array &$body, string $target, int $indent): void
    {
        $values = $this->compileArguments(array_values($hash->getData()), $body, $indent);
        $items = [];
        foreach (array_keys($hash->getData()) as $index => $key) {
            $items[] = var_export($key, true) . ' => ' . $values[$index];
        }

        $this->emit(
            $body,
            $indent,
            "$target = new \\MadLisp\\Hash([" . implode(', ', $items) . ']);'
        );
    }

    private function compileAnd(array $arguments, array &$body, string $target, int $indent): void
    {
        $this->compileShortCircuit($arguments, false, $body, $target, $indent);
    }

    private function compileOr(array $arguments, array &$body, string $target, int $indent): void
    {
        $this->compileShortCircuit($arguments, true, $body, $target, $indent);
    }

    private function compileShortCircuit(
        array $arguments,
        bool $or,
        array &$body,
        string $target,
        int $indent
    ): void {
        if (!$arguments) {
            $this->emit($body, $indent, "$target = " . ($or ? 'false' : 'true') . ';');
            return;
        }

        $openBlocks = 0;
        $last = count($arguments) - 1;
        foreach ($arguments as $index => $argument) {
            if ($index === $last) {
                $this->compileExpression($argument, $body, $target, $indent);
                break;
            }

            $condition = $this->temporary();
            $this->compileExpression($argument, $body, $condition, $indent);
            $match = $or ? 'true' : 'false';
            $this->emit($body, $indent, "if ($condition == $match) {");
            $this->emit($body, $indent + 1, "$target = $condition;");
            $this->emit($body, $indent, '} else {');
            $indent++;
            $openBlocks++;
        }

        while ($openBlocks-- > 0) {
            $indent--;
            $this->emit($body, $indent, '}');
        }
    }

    private function compileCond(array $arguments, array &$body, string $target, int $indent): void
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

    private function compileCondBranch(
        array $clauses,
        int $index,
        array &$body,
        string $target,
        int $indent
    ): void {
        if ($index === count($clauses)) {
            $this->emit($body, $indent, "$target = null;");
            return;
        }

        $clause = $clauses[$index];
        $test = $clause[0];
        if ($test instanceof Symbol && $test->getName() === 'else') {
            $this->compileDo(array_slice($clause, 1), $body, $target, $indent);
            return;
        }

        $condition = $this->temporary();
        $this->compileExpression($test, $body, $condition, $indent);
        $this->emit($body, $indent, "if ($condition) {");
        $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1);
        $this->emit($body, $indent, '} else {');
        $this->compileCondBranch($clauses, $index + 1, $body, $target, $indent + 1);
        $this->emit($body, $indent, '}');
    }

    private function compileCase(
        string $operator,
        array $arguments,
        array &$body,
        string $target,
        int $indent
    ): void {
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
        $comparison = $operator === 'case-strict' ? '===' : '==';
        $this->compileCaseBranch($clauses, 0, $value, $comparison, $body, $target, $indent);
    }

    private function compileCaseBranch(
        array $clauses,
        int $index,
        string $value,
        string $comparison,
        array &$body,
        string $target,
        int $indent
    ): void {
        if ($index === count($clauses)) {
            $this->emit($body, $indent, "$target = null;");
            return;
        }

        $clause = $clauses[$index];
        $test = $clause[0];
        if ($test instanceof Symbol && $test->getName() === 'else') {
            $this->compileDo(array_slice($clause, 1), $body, $target, $indent);
            return;
        }

        $match = $this->quotedValueExpression($test);
        $this->emit($body, $indent, "if ($value $comparison $match) {");
        $this->compileDo(array_slice($clause, 1), $body, $target, $indent + 1);
        $this->emit($body, $indent, '} else {');
        $this->compileCaseBranch($clauses, $index + 1, $value, $comparison, $body, $target, $indent + 1);
        $this->emit($body, $indent, '}');
    }

    private function compileQuote(array $arguments, array &$body, string $target, int $indent): void
    {
        if (count($arguments) !== 1) {
            throw new MadLispException('quote requires exactly 1 argument');
        }

        $this->emit($body, $indent, "$target = " . $this->quotedValueExpression($arguments[0]) . ';');
    }

    private function compileDef(array $arguments, array &$body, string $target, int $indent): void
    {
        if (count($arguments) !== 2) {
            throw new MadLispException('def requires exactly 2 arguments');
        }
        if (!($arguments[0] instanceof Symbol)) {
            throw new MadLispException('first argument to def is not symbol');
        }

        $name = $arguments[0]->getName();
        if ($name === '__FILE__' || $name === '__DIR__') {
            throw new MadLispException("cannot define reserved name $name");
        }
        if (in_array($name, self::RESERVED_DEFINITION_NAMES, true)) {
            throw new MadLispException("cannot redefine core operator $name");
        }

        $value = $this->temporary();
        $this->compileExpression($arguments[1], $body, $value, $indent);
        $this->emit($body, $indent, "$target = \$env->set(" . var_export($name, true) . ", $value);");
    }

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

        throw new MadLispException('php compiler does not support this quoted value');
    }

    private function compileLet(array $arguments, array &$body, string $target, int $indent): void
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
                $value = $this->temporary();
                $this->compileExpression($bindingData[$i + 1], $body, $value, $indent);
                $this->emit($body, $indent, "$local = $value;");
            }
            $this->scopes[array_key_last($this->scopes)][$bindingData[$i]->getName()] = $local;
        }

        $this->compileDo(array_slice($arguments, 1), $body, $target, $indent);
        array_pop($this->scopes);
    }

    private function compileDo(array $arguments, array &$body, string $target, int $indent): void
    {
        if (!$arguments) {
            $this->emit($body, $indent, "$target = null;");
            return;
        }

        $last = array_key_last($arguments);
        foreach ($arguments as $index => $argument) {
            $expressionTarget = $index === $last ? $target : $this->temporary();
            $this->compileExpression($argument, $body, $expressionTarget, $indent);
        }
    }

    private function compileFn(array $arguments, array &$body, string $target, int $indent): void
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
            }
            if (array_key_exists($name, $parameterNames)) {
                throw new MadLispException("duplicate parameter $name for fn");
            }

            $variable = '$v' . $this->localCount++;
            $parameterNames[$name] = $variable;
            $parameterVariables[] = $variable;
        }

        $this->scopes[] = $parameterNames;
        $this->functionScopes[] = count($this->scopes) - 1;
        $this->functionCaptures[] = [];

        $functionBody = [];
        $this->compileExpression($arguments[1], $functionBody, '$result', $indent + 1);
        $this->emit($functionBody, $indent + 1, 'return $result;');

        $captures = array_values($this->functionCaptures[array_key_last($this->functionCaptures)]);
        array_pop($this->functionCaptures);
        array_pop($this->functionScopes);
        array_pop($this->scopes);

        $use = array_merge(['$env'], $captures);
        $this->emit(
            $body,
            $indent,
            "$target = static function (" . implode(', ', $parameterVariables) . ") use (" . implode(', ', $use) . ') {'
        );
        array_push($body, ...$functionBody);
        $this->emit($body, $indent, '};');
    }

    private function compileIf(array $arguments, array &$body, string $target, int $indent): void
    {
        if (count($arguments) < 2 || count($arguments) > 3) {
            throw new MadLispException('if requires 2 or 3 arguments');
        }

        $condition = $this->temporary();
        $this->compileExpression($arguments[0], $body, $condition, $indent);
        $this->emit($body, $indent, "if ($condition) {");
        $this->compileExpression($arguments[1], $body, $target, $indent + 1);
        $this->emit($body, $indent, '} else {');

        if (isset($arguments[2])) {
            $this->compileExpression($arguments[2], $body, $target, $indent + 1);
        } else {
            $this->emit($body, $indent + 1, "$target = null;");
        }

        $this->emit($body, $indent, '}');
    }

    private function compileDynamicCall(
        string $name,
        array $arguments,
        array &$body,
        string $target,
        int $indent
    ): void {
        $function = $this->temporary();
        $this->emit($body, $indent, "$function = \$env->get(" . var_export($name, true) . ');');
        $values = $this->compileArguments($arguments, $body, $indent);
        $this->emit($body, $indent, "$target = $function(" . implode(', ', $values) . ');');
    }

    private function compileCall(array $data, array &$body, string $target, int $indent): void
    {
        $functionExpression = $data[0];
        if ($functionExpression instanceof Symbol) {
            $function = $this->resolveLocal($functionExpression->getName());
            if ($function === null) {
                throw new MadLispException(
                    "php compiler does not support calls to global function {$functionExpression->getName()}"
                );
            }
        } else {
            $function = $this->temporary();
            $this->compileExpression($functionExpression, $body, $function, $indent);
        }

        $values = $this->compileArguments(array_slice($data, 1), $body, $indent);
        $this->emit($body, $indent, "$target = $function(" . implode(', ', $values) . ');');
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

    private function compileArithmetic(
        array $values,
        string $operator,
        int $minimum,
        bool $allowUnary,
        array &$body,
        string $target,
        int $indent
    ): void {
        if (count($values) < $minimum || (!$allowUnary && !$values)) {
            throw new MadLispException("$operator requires at least $minimum argument");
        }

        if ($allowUnary && count($values) === 1) {
            $this->emit($body, $indent, "$target = -{$values[0]};");
            return;
        }

        $this->emit($body, $indent, "$target = " . implode(" $operator ", $values) . ';');
    }

    private function compileBinary(
        array $values,
        string $operator,
        string $error,
        array &$body,
        string $target,
        int $indent
    ): void {
        if (count($values) !== 2) {
            throw new MadLispException($error);
        }

        $this->emit($body, $indent, "$target = {$values[0]} $operator {$values[1]};");
    }

    private function compileUnary(
        array $values,
        string $operator,
        string $error,
        array &$body,
        string $target,
        int $indent,
        bool $prefix = false
    ): void {
        if (count($values) !== 1) {
            throw new MadLispException($error);
        }

        $expression = $prefix
            ? "$operator{$values[0]}"
            : "{$values[0]} $operator";
        $this->emit($body, $indent, "$target = $expression;");
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

    private function emit(array &$body, int $indent, string $statement): void
    {
        $body[] = str_repeat('    ', $indent) . $statement;
    }
}
