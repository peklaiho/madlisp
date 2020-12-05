<?php
namespace MadLisp\Lib;

use MadLisp\Collection;
use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Func;
use MadLisp\MadLispException;
use MadLisp\MList;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Symbol;
use MadLisp\Tokenizer;
use MadLisp\UserFunc;

class Core implements ILib
{
    protected Tokenizer $tokenizer;
    protected Reader $reader;
    protected Printer $printer;
    protected Evaller $evaller;

    public function __construct(Tokenizer $tokenizer, Reader $reader, Printer $printer, Evaller $evaller)
    {
        $this->tokenizer = $tokenizer;
        $this->reader = $reader;
        $this->printer = $printer;
        $this->evaller = $evaller;
    }

    public function register(Env $env): void
    {
        // Register special constants
        $env->set('__FILE__', null);
        $env->set('__DIR__', null);

        $env->set('debug', new CoreFunc('debug', 'Toggle debug mode.', 0, 0,
            function () {
                $val = !$this->evaller->getDebug();
                $this->evaller->setDebug($val);
                return $val;
            }
        ));

        $env->set('doc', new CoreFunc('doc', 'Get documentation for a function.', 1, 1,
            function ($a) {
                if ($a instanceof Func) {
                    return $a->getDoc();
                }

                throw new MadLispException('first argument to doc is not function');
            }
        ));

        $env->set('loop', new CoreFunc('loop', 'Call the given function repeatedly in a loop until it returns false.', 1, -1,
            function (Func $f, ...$args) {
                do {
                    $result = $f->call($args);
                } while ($result);
                return $result;
            }
        ));

        $env->set('meta', new CoreFunc('meta', 'Read meta information of an entity.', 2, 2,
            function ($obj, $attribute) {
                if ($obj instanceof Env) {
                    if ($attribute == 'name') {
                        return $obj->getFullName();
                    } elseif ($attribute == 'parent') {
                        return $obj->getParent();
                    } else {
                        throw new MadLispException('unknown attribute for meta');
                    }
                } elseif ($obj instanceof UserFunc) {
                    if ($attribute == 'args') {
                        return $obj->getBindings();
                    } elseif ($attribute == 'body') {
                        return $obj->getAst();
                    } elseif ($attribute == 'code') {
                        return new MList([new Symbol('fn'), $obj->getBindings(), $obj->getAst()]);
                    } else {
                        throw new MadLispException('unknown attribute for meta');
                    }
                } else {
                    throw new MadLispException('unknown entity for meta');
                }
            }
        ));

        $env->set('read', new CoreFunc('read', 'Read string as code.', 1, 1,
            fn (string $a) => $this->reader->read($this->tokenizer->tokenize($a))
        ));

        $env->set('print', new CoreFunc('print', 'Print argument. Give second argument as true to show strings in readable format.', 1, 2,
            function ($a, bool $readable = false) {
                $this->printer->print($a, $readable);
                return null;
            }
        ));

        $env->set('error', new CoreFunc('error', 'Throw an exception using argument (string) as message.', 1, 1,
            function (string $error) {
                throw new MadLispException($error);
            }
        ));

        $env->set('exit', new CoreFunc('exit', 'Terminate the script with given exit code.', 0, 1,
            function (int $status = 0) {
                exit($status);
            }
        ));
    }
}
