<?php
namespace MadLisp;

use Closure;

abstract class Func
{
    protected Closure $closure;
    protected ?string $doc;

    public function __construct(Closure $closure, ?string $doc = null)
    {
        $this->closure = $closure;
        $this->doc = $doc;
    }

    public function getClosure(): Closure
    {
        return $this->closure;
    }

    public function getDoc(): ?string
    {
        return $this->doc;
    }

    abstract public function call(array $args);
}
