<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2020 Pekka Laiho
 */

namespace MadLisp;

use Closure;

class UserFunc extends Func
{
    protected $ast;
    protected Env $tempEnv;
    protected Seq $bindings;

    public function __construct(Closure $closure, $ast, Env $tempEnv, Seq $bindings, bool $macro = false)
    {
        parent::__construct($closure, null, $macro);

        $this->ast = $ast;
        $this->tempEnv = $tempEnv;
        $this->bindings = $bindings;
    }

    public function getAst()
    {
        return $this->ast;
    }

    public function getBindings(): Seq
    {
        return $this->bindings;
    }

    public function getEnv(array $args)
    {
        $newEnv = new Env('apply', $this->tempEnv);

        Util::bindArguments($newEnv, $this->bindings->getData(), $args);

        return $newEnv;
    }

    public function call(array $args)
    {
        return ($this->closure)(...$args);
    }
}
