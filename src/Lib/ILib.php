<?php
namespace MadLisp\Lib;

use MadLisp\Env;

interface ILib
{
    public function register(Env $env): void;
}
