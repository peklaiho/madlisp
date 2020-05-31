<?php
namespace MadLisp;

use Closure;

abstract class Func
{
    protected Closure $closure;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    public function getClosure(): Closure
    {
        return $this->closure;
    }

    public function call(array $args)
    {
        return ($this->closure)(...$args);
    }
}
