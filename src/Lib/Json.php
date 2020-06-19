<?php
namespace MadLisp\Lib;

use MadLisp\Collection;
use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Func;
use MadLisp\Hash;
use MadLisp\MadLispException;
use MadLisp\Symbol;
use MadLisp\Vector;

class Json implements ILib
{
    public function register(Env $env): void
    {
        $env->set('to-json', new CoreFunc('to-json', 'Encode the argument as a JSON string.', 1, 1,
            fn ($a) => json_encode($this->getJsonData($a))
        ));

        $env->set('from-json', new CoreFunc('from-json', 'Decode the JSON string given as argument.', 1, 1,
            fn ($a) => $this->parseJsonData(json_decode($a))
        ));
    }

    private function getJsonData($a)
    {
        if ($a instanceof Func) {
            throw new MadLispException("unable to encode function as json");
        } elseif ($a instanceof Collection) {
            return array_map([$this, 'getJsonData'], $a->getData());
        } elseif ($a instanceof Symbol) {
            // Does this make sense, as we cannot reverse this operation?
            return $a->getName();
        } else {
            return $a;
        }
    }

    private function parseJsonData($a)
    {
        if (is_object($a)) {
            return new Hash(array_map([$this, 'parseJsonData'], (array) $a));
        } elseif (is_array($a)) {
            // We have to choose between a List and a Vector...
            return new Vector(array_map([$this, 'parseJsonData'], $a));
        } else {
            return $a;
        }
    }
}
