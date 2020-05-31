<?php
namespace MadLisp;

use Closure;

class CoreFunc extends Func
{
    protected string $name;
    protected string $doc;
    protected int $minArgs;
    protected int $maxArgs;

    public function __construct(string $name, string $doc, int $minArgs, int $maxArgs, Closure $closure)
    {
        $this->name = $name;
        $this->doc = $doc;
        $this->minArgs = $minArgs;
        $this->maxArgs = $maxArgs;
        $this->closure = $closure;
    }

    public function call(array $args)
    {
        $this->validateArgs(count($args));

        return parent::call($args);
    }

    private function validateArgs(int $count)
    {
        if ($this->minArgs >= 0 && $count < $this->minArgs) {
            if ($this->minArgs == $this->maxArgs) {
                throw new MadLispException(sprintf("%s requires exactly %s argument%s", $this->name, $this->minArgs,
                                                   $this->minArgs == 1 ? '' : 's'));
            } else {
                throw new MadLispException(sprintf("%s requires at least %s argument%s", $this->name, $this->minArgs,
                                                   $this->minArgs == 1 ? '' : 's'));
            }
        } elseif ($this->maxArgs >= 0 && $count > $this->maxArgs) {
                throw new MadLispException(sprintf("%s requires at most %s argument%s", $this->name, $this->maxArgs,
                                                   $this->maxArgs == 1 ? '' : 's'));
        }
    }
}
