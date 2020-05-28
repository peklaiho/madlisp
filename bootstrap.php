<?php
require('vendor/autoload.php');

function ml_get_env(): MadLisp\Env
{
    $env = new MadLisp\Env();

    $core = new MadLisp\Lib\Core();
    $core->register($env);

    return $env;
}
