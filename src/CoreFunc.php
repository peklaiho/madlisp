<?php
namespace MadLisp;

use Closure;

class CoreFunc extends Func
{
    protected string $name;
    protected int $minArgs;
    protected int $maxArgs;

    public function __construct(string $name, string $doc, int $minArgs, int $maxArgs, Closure $closure)
    {
        parent::__construct($closure, $doc);

        $this->name = $name;
        $this->minArgs = $minArgs;
        $this->maxArgs = $maxArgs;
    }

    public function call(array $args)
    {
        $this->validateArgs(count($args));

        return ($this->closure)(...$args);
    }

    private function validateArgs(int $count)
    {
        if ($this->minArgs == $this->maxArgs && $count != $this->minArgs) {
            throw new MadLispException(sprintf("%s requires exactly %s argument%s", $this->name, $this->minArgs,
                                               $this->minArgs == 1 ? '' : 's'));
        } elseif ($count < $this->minArgs) {
            throw new MadLispException(sprintf("%s requires at least %s argument%s", $this->name, $this->minArgs,
                                               $this->minArgs == 1 ? '' : 's'));
        } elseif ($this->maxArgs >= 0 && $count > $this->maxArgs) {
            throw new MadLispException(sprintf("%s requires at most %s argument%s", $this->name, $this->maxArgs,
                                               $this->maxArgs == 1 ? '' : 's'));
        }
    }
}
