<?php

require_once('classes.php');

function ml_get_env(): Env
{
    $env = new Env();

    // basic arithmetic
    $env->set('+', fn (...$args) => array_sum($args));
    $env->set('-', fn ($a, $b) => $a - $b);
    $env->set('*', fn ($a, $b) => $a * $b);
    $env->set('/', fn ($a, $b) => $a / $b);
    $env->set('%', fn ($a, $b) => $a % $b);

    return $env;
}
