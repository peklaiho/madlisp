<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Executor
{
    public function execute(CompiledProgram $ir, Env $env)
    {
        $code = $ir->getCode();
        $constants = $ir->getConstants();
        $stack = [];
        $pc = 0;
        $codeLength = count($code);

        while ($pc < $codeLength) {
            $opcode = $code[$pc++];

            switch ($opcode) {
                case OpCode::LOAD_CONSTANT:
                    $constantIndex = $code[$pc++];
                    $stack[] = $constants[$constantIndex];
                    break;

                case OpCode::LOAD_GLOBAL:
                    $nameIndex = $code[$pc++];
                    $stack[] = $env->get($constants[$nameIndex]);
                    break;

                case OpCode::JUMP_IF_FALSE:
                    $target = $code[$pc++];
                    $condition = array_pop($stack);

                    if ($condition != true) {
                        $pc = $target;
                    }
                    break;

                case OpCode::JUMP:
                    $pc = $code[$pc];
                    break;

                case OpCode::CALL:
                    $arity = $code[$pc++];
                    $args = $arity == 0 ? [] : array_splice($stack, -$arity);
                    $func = array_pop($stack);

                    if (!($func instanceof Func)) {
                        throw new MadLispException('eval: first item of list is not function');
                    }

                    $stack[] = $func->call($args);
                    break;

                case OpCode::RETURN:
                    return array_pop($stack);
            }
        }
    }
}
