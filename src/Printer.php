<?php
namespace MadLisp;

class Printer
{
    public function print($ast, bool $readable = true): void
    {
        print($this->doPrint($ast, $readable));
    }

    private function doPrint($a, bool $readable): string
    {
        if ($a instanceof Func) {
            return '<function>';
        } elseif ($a instanceof MList) {
            return '(' . implode(' ', array_map(fn ($b) => $this->doPrint($b, $readable), $a->getData())) . ')';
        } elseif ($a instanceof Vector) {
            return '[' . implode(' ', array_map(fn ($b) => $this->doPrint($b, $readable), $a->getData())) . ']';
        } elseif ($a instanceof Hash) {
            return '{' . implode(' ', array_map(fn ($key, $val) => $this->doPrint($key, $readable) . ':' . $this->doPrint($val, $readable),
                                                array_keys($a->getData()), array_values($a->getData()))) . '}';
        } elseif ($a instanceof Symbol) {
            return $a->getName();
        } elseif ($a === true) {
            return 'true';
        } elseif ($a === false) {
            return 'false';
        } elseif ($a === null) {
            return 'null';
        } elseif (is_string($a)) {
            if ($readable) {
                $a = str_replace("\\", "\\\\", $a);
                $a = str_replace("\n", "\\n", $a);
                $a = str_replace("\r", "\\r", $a);
                $a = str_replace("\"", "\\\"", $a);
                return '"' . $a . '"';
            } else {
                return $a;
            }
        } else {
            return $a;
        }
    }
}
