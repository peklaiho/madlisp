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
    protected bool $safemode;

    public function __construct(Tokenizer $tokenizer, Reader $reader, Printer $printer, Evaller $evaller, bool $safemode)
    {
        $this->tokenizer = $tokenizer;
        $this->reader = $reader;
        $this->printer = $printer;
        $this->evaller = $evaller;
        $this->safemode = $safemode;
    }

    public function register(Env $env): void
    {
        // Register special constants
        if (!$this->safemode) {
            $env->set('__FILE__', null);
            $env->set('__DIR__', null);
        }

        if (!$this->safemode) {
            $env->set('debug', new CoreFunc('debug', 'Toggle debug mode.', 0, 0,
                function () {
                    $val = !$this->evaller->getDebug();
                    $this->evaller->setDebug($val);
                    return $val;
                }
            ));
        }

        $env->set('doc', new CoreFunc('doc', 'Get or set documentation string for a function.', 1, 2,
            function (Func $a, ?string $str = null) {
                if (func_num_args() == 1) {
                    return $a->getDoc();
                } else {
                    $a->setDoc($str);
                    return $str;
                }
            }
        ));

        // This is allowed in safe-mode, because the evaluation should be wrapped in a try-catch in embedded use.
        $env->set('error', new CoreFunc('error', 'Throw an exception using argument (string) as message.', 1, 1,
            function (string $error) {
                // We should probably use another exception type to distinguish user-thrown errors from built-in errors.
                throw new MadLispException($error);
            }
        ));

        if (!$this->safemode) {
            $env->set('exit', new CoreFunc('exit', 'Terminate the script with given exit code.', 0, 1,
                function (int $status = 0) {
                    exit($status);
                }
            ));
        }

        $env->set('loop', new CoreFunc('loop', 'Call the given function repeatedly in a loop until it returns false.', 1, -1,
            function (Func $f, ...$args) {
                do {
                    $result = $f->call($args);
                } while ($result);
                return $result;
            }
        ));

        if (!$this->safemode) {
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
                            $name = $obj->isMacro() ? 'macro' : 'fn';
                            return new MList([new Symbol($name), $obj->getBindings(), $obj->getAst()]);
                        } else {
                            throw new MadLispException('unknown attribute for meta');
                        }
                    } else {
                        throw new MadLispException('unknown entity for meta');
                    }
                }
            ));
        }

        if (!$this->safemode) {
            $env->set('print', new CoreFunc('print', 'Print argument. Give second argument as true to show strings in readable format.', 1, 2,
                function ($a, bool $readable = false) {
                    $this->printer->print($a, $readable);
                    return null;
                }
            ));
        }

        if (!$this->safemode) {
            $env->set('read', new CoreFunc('read', 'Read string as code.', 1, 1,
                fn (string $a) => $this->reader->read($this->tokenizer->tokenize($a))
            ));
        }

        if (!$this->safemode) {
            $env->set('sleep', new CoreFunc('sleep', 'Sleep (wait) for the specified time in milliseconds.', 1, 1,
                function (int $time) {
                    usleep($time * 1000);
                    return null;
                }
            ));
        }

        if (!$this->safemode) {
            $env->set('timer', new CoreFunc('timer', 'Measure the execution time of a function and return it in seconds.', 1, -1,
                function (Func $f, ...$args) {
                    $start = microtime(true);
                    $f->call($args);
                    $end = microtime(true);
                    return $end - $start;
                }
            ));
        }
    }
}
