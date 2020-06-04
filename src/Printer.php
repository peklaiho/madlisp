<?php
namespace MadLisp;

class Printer
{
    public function print($ast): void
    {
        print($this->doPrint($ast));
    }

    private function doPrint($a): string
    {
        if ($a instanceof Func) {
            return '<function>';
        } elseif ($a instanceof MList) {
            return '(' . implode(' ', array_map(fn ($b) => $this->doPrint($b), $a->getData())) . ')';
        } elseif ($a instanceof Vector) {
            return '[' . implode(' ', array_map(fn ($b) => $this->doPrint($b), $a->getData())) . ']';
        } elseif ($a instanceof Hash) {
            return '{' . implode(' ', array_map(fn ($key, $val) => $this->doPrint($key) . ':' . $this->doPrint($val),
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
            return '"' . $a . '"';
        } else {
            return $a;
        }
    }
}
