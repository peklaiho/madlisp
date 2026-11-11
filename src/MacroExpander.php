<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class MacroExpander
{
    private const SPECIAL_FORMS = [
        'and', 'case', 'case-strict', 'cond', 'def', 'do', 'env', 'fn', 'if',
        'let', 'macro', 'or', 'quote', 'quasiquote', 'try', 'undef', 'while',
    ];

    public function expand($ast, Env $env)
    {
        return $this->expandExpression($ast, $env, []);
    }

    private function expandExpression($ast, Env $env, array $bound)
    {
        if ($ast instanceof MList) {
            return $this->expandList($ast, $env, $bound);
        }

        if ($ast instanceof Vector) {
            return new Vector($this->expandData($ast->getData(), $env, $bound));
        }

        if ($ast instanceof Hash) {
            return new Hash($this->expandData($ast->getData(), $env, $bound));
        }

        return $ast;
    }

    private function expandList(MList $ast, Env $env, array $bound)
    {
        $expanded = $this->expandOuter($ast, $env, $bound);

        if (!($expanded instanceof MList)) {
            return $this->expandExpression($expanded, $env, $bound);
        }

        $data = $expanded->getData();
        $name = isset($data[0]) && $data[0] instanceof Symbol ? $data[0]->getName() : null;

        switch ($name) {
            case 'quote':
            case 'quasiquote':
                return $expanded;
            case 'fn':
            case 'macro':
                if (isset($data[1]) && $data[1] instanceof Seq) {
                    foreach ($data[1]->getData() as $parameter) {
                        if ($parameter instanceof Symbol) {
                            $bound[$parameter->getName()] = true;
                        }
                    }
                }
                return new MList($this->expandData($data, $env, $bound, 2));
            case 'let':
                return $this->expandLet($data, $env, $bound);
            case 'case':
            case 'case-strict':
            case 'cond':
                $isCase = $name !== 'cond';
                if ($isCase && array_key_exists(1, $data)) {
                    $data[1] = $this->expandExpression($data[1], $env, $bound);
                }
                for ($i = $isCase ? 2 : 1; $i < count($data); $i++) {
                    if ($data[$i] instanceof Seq) {
                        $clause = $data[$i];
                        $data[$i] = new ($clause::class)(
                            $this->expandData($clause->getData(), $env, $bound, $isCase ? 1 : 0)
                        );
                    }
                }
                return new MList($data);
            case 'try':
                if (array_key_exists(1, $data)) {
                    $data[1] = $this->expandExpression($data[1], $env, $bound);
                }
                if (isset($data[2]) && $data[2] instanceof Seq) {
                    $catch = $data[2];
                    $catchData = $catch->getData();
                    if (isset($catchData[1]) && $catchData[1] instanceof Symbol) {
                        $bound[$catchData[1]->getName()] = true;
                    }
                    $data[2] = new ($catch::class)($this->expandData($catchData, $env, $bound, 2));
                }
                return new MList($data);
            case 'def':
            case 'undef':
                return new MList($this->expandData($data, $env, $bound, 2));
        }

        return new MList($this->expandData($data, $env, $bound));
    }

    private function expandLet(array $data, Env $env, array $bound): MList
    {
        $isNamed = isset($data[1]) && $data[1] instanceof Symbol;
        $bindingIndex = $isNamed ? 2 : 1;

        if (isset($data[$bindingIndex]) && $data[$bindingIndex] instanceof Seq) {
            $bindings = $data[$bindingIndex];
            $bindingData = $bindings->getData();
            for ($i = 0; $i < count($bindingData); $i += 2) {
                if (array_key_exists($i + 1, $bindingData)) {
                    $bindingData[$i + 1] = $this->expandExpression($bindingData[$i + 1], $env, $bound);
                }
                if ($bindingData[$i] instanceof Symbol) {
                    $bound[$bindingData[$i]->getName()] = true;
                }
            }
            $data[$bindingIndex] = new ($bindings::class)($bindingData);
        }

        if ($isNamed) {
            $bound[$data[1]->getName()] = true;
        }

        return new MList($this->expandData($data, $env, $bound, $bindingIndex + 1));
    }

    private function expandData(array $data, Env $env, array $bound, int $start = 0): array
    {
        foreach ($data as $key => $value) {
            if ($start === 0 || $key >= $start) {
                $data[$key] = $this->expandExpression($value, $env, $bound);
            }
        }

        return $data;
    }

    private function expandOuter($ast, Env $env, array $bound)
    {
        while ($ast instanceof MList) {
            $data = $ast->getData();

            if (count($data) === 0 || !($data[0] instanceof Symbol)) {
                break;
            }

            $name = $data[0]->getName();
            if (isset($bound[$name]) || in_array($name, self::SPECIAL_FORMS, true)) {
                break;
            }

            $macro = $env->get($name, false);
            if (!($macro instanceof Func) || !$macro->isMacro()) {
                break;
            }

            $ast = $macro->call(array_slice($data, 1));
        }

        return $ast;
    }
}
