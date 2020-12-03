<?php
namespace MadLisp;

use Closure;

class UserFunc extends Func
{
    protected $ast;
    protected Env $tempEnv;
    protected array $bindings;

    public function __construct(Closure $closure, $ast, Env $tempEnv, array $bindings)
    {
        parent::__construct($closure, null);

        $this->ast = $ast;
        $this->tempEnv = $tempEnv;
        $this->bindings = $bindings;
    }

    public function getAst()
    {
        return $this->ast;
    }

    public function getEnv(array $args)
    {
        $newEnv = new Env('apply', $this->tempEnv);

        for ($i = 0; $i < count($this->bindings); $i++) {
            $newEnv->set($this->bindings[$i]->getName(), $args[$i] ?? null);
        }

        return $newEnv;
    }

    public function call(array $args)
    {
        return ($this->closure)(...$args);
    }
}
