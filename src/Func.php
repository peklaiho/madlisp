<?php
namespace MadLisp;

use Closure;

abstract class Func
{
    protected Closure $closure;
    protected ?string $doc;
    protected bool $macro;

    public function __construct(Closure $closure, ?string $doc = null, bool $macro = false)
    {
        $this->closure = $closure;
        $this->doc = $doc;
        $this->macro = $macro;
    }

    public function getClosure(): Closure
    {
        return $this->closure;
    }

    public function getDoc(): ?string
    {
        return $this->doc;
    }

    public function isMacro(): bool
    {
        return $this->macro;
    }

    public function setDoc(?string $val): void
    {
        $this->doc = $val;
    }

    abstract public function call(array $args);
}
