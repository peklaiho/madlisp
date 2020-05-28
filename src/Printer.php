<?php
namespace MadLisp;

use Closure;

class Printer
{
    public function print(array $items): void
    {
        for ($i = 0; $i < count($items); $i++) {
            if ($i > 0) {
                print(' ');
            }
            $this->doPrint($items[$i]);
        }
    }

    private function doPrint($a): void
    {
        if ($a instanceof Closure) {
            print('<function>');
        } elseif ($a instanceof MList) {
            print('(');
            for ($i = 0; $i < $a->count(); $i++) {
                if ($i > 0) {
                    print(' ');
                }
                $this->doPrint($a->get($i));
            }
            print(')');
        } elseif ($a instanceof Hash) {
            print('{');
            $keys = array_keys($a->getData());
            for ($i = 0; $i < count($keys); $i++) {
                if ($i > 0) {
                    print(' ');
                }
                $this->doPrint($keys[$i]);
                print(':');
                $this->doPrint($a->get($keys[$i]));
            }
            print('}');
        } elseif ($a instanceof Symbol) {
            print($a->getName());
        } elseif ($a === true) {
            print('true');
        } elseif ($a === false) {
            print('false');
        } elseif ($a === null) {
            print('null');
        } elseif (is_string($a)) {
            print('"' . $a . '"');
        } else {
            print($a);
        }
    }
}
