<?php

require_once('classes.php');

function ml_get_env(): MLEnv
{
    $env = new MLEnv();

    // basic arithmetic

    $env->set('+', function (...$args) {
        return array_sum($args);
    });

    $env->set('-', function (...$args) {
        $result = $args[0] ?? null;
        for ($i = 1; $i < count($args); $i++) {
            $result -= $args[$i];
        }
        return $result;
    });

    $env->set('*', function (...$args) {
        $result = $args[0] ?? null;
        for ($i = 1; $i < count($args); $i++) {
            $result *= $args[$i];
        }
        return $result;
    });

    $env->set('/', function (...$args) {
        $result = $args[0] ?? null;
        for ($i = 1; $i < count($args); $i++) {
            $result /= $args[$i];
        }
        return $result;
    });

    $env->set('%', function (...$args) {
        $result = $args[0] ?? null;
        for ($i = 1; $i < count($args); $i++) {
            $result %= $args[$i];
        }
        return $result;
    });

    // comparison

    $env->set('=', fn ($a, $b) => $a == $b);
    $env->set('<', fn ($a, $b) => $a < $b);
    $env->set('>', fn ($a, $b) => $a > $b);
    $env->set('<=', fn ($a, $b) => $a <= $b);
    $env->set('>=', fn ($a, $b) => $a >= $b);
    $env->set('!=', fn ($a, $b) => $a != $b);

    // types

    $env->set('type?', function ($a) {
        return gettype($a);
    });

    return $env;
}
