<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp\Lib;

use MadLisp\Collection;
use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Func;
use MadLisp\MadLispUserException;
use MadLisp\MList;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Symbol;
use MadLisp\Tokenizer;
use MadLisp\UserFunc;
use MadLisp\Vector;

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

        if (!$this->safemode) {
            $env->set('exit', new CoreFunc('exit', 'Terminate the script with given exit code.', 0, 1,
                function (int $status = 0) {
                    exit($status);
                }
            ));
        }

        $env->set('php-sapi', new CoreFunc('php-sapi', 'Get the name of the PHP SAPI.', 0, 0,
            fn () => php_sapi_name()
        ));

        $env->set('php-version', new CoreFunc('php-version', 'Get the PHP version.', 0, 0,
            fn () => phpversion()
        ));

        if (!$this->safemode) {
            $env->set('print', new CoreFunc('print', 'Print arguments.', 0, -1,
                function (...$args) {
                    foreach ($args as $a) {
                        $this->printer->print($a, false);
                    }
                    return null;
                }
            ));
        }

        if (!$this->safemode) {
            $env->set('printr', new CoreFunc('printr', 'Print argument in readable format.', 1, 1,
                function ($a) {
                    $this->printer->print($a, true);
                    return null;
                }
            ));
        }

        $env->set('prints', new CoreFunc('prints', 'Print argument in readable format to string.', 1, 1,
            function ($a) {
                return $this->printer->pstr($a, true);
            }
        ));

        $env->set('read', new CoreFunc('read', 'Read string as code.', 1, 1,
            fn (string $a) => $this->reader->read($this->tokenizer->tokenize($a))
        ));

        if (!$this->safemode) {
            $env->set('sleep', new CoreFunc('sleep', 'Sleep (wait) for the specified time in milliseconds.', 1, 1,
                function (int $time) {
                    usleep($time * 1000);
                    return null;
                }
            ));
        }

        if (!$this->safemode) {
            $env->set('system', new CoreFunc('system', 'Execute a command on the system.', 1, 1,
                function (string $command) {
                    // Use passthru to capture the raw output

                    ob_start();
                    passthru($command, $status);
                    $output = ob_get_contents();
                    ob_end_clean();

                    return new Vector([
                        $status,
                        $output
                    ]);
                }
            ));
        }

        $env->set('throw', new CoreFunc('throw', 'Throw an exception. Takes one argument which is passed to catch.', 1, 1,
            function ($error) {
                throw new MadLispUserException($error);
            }
        ));
    }
}
