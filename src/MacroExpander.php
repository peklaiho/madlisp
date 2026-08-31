<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class MacroExpander
{
    public function expand($ast, Env $env)
    {
        if ($ast instanceof MList) {
            return $this->expandList($ast, $env);
        }

        if ($ast instanceof Vector) {
            return new Vector($this->expandData($ast->getData(), $env));
        }

        if ($ast instanceof Hash) {
            $data = [];
            foreach ($ast->getData() as $key => $value) {
                $data[$key] = $this->expand($value, $env);
            }

            return new Hash($data);
        }

        return $ast;
    }

    private function expandList(MList $ast, Env $env)
    {
        $expanded = $this->expandOuter($ast, $env);

        if (!($expanded instanceof MList)) {
            return $this->expand($expanded, $env);
        }

        $data = $expanded->getData();

        if ($this->isQuotedForm($data)) {
            return $expanded;
        }

        return new MList($this->expandData($data, $env));
    }

    private function expandData(array $data, Env $env): array
    {
        foreach ($data as $key => $value) {
            $data[$key] = $this->expand($value, $env);
        }

        return $data;
    }

    private function expandOuter($ast, Env $env)
    {
        while ($ast instanceof MList) {
            $data = $ast->getData();

            if (count($data) === 0 || !($data[0] instanceof Symbol)) {
                break;
            }

            $macro = $env->get($data[0]->getName(), false);
            if (!($macro instanceof Func) || !$macro->isMacro()) {
                break;
            }

            $ast = $macro->call(array_slice($data, 1));
        }

        return $ast;
    }

    private function isQuotedForm(array $data): bool
    {
        if (count($data) === 0 || !($data[0] instanceof Symbol)) {
            return false;
        }

        $name = $data[0]->getName();
        return $name === 'quote' || $name === 'quasiquote';
    }
}
