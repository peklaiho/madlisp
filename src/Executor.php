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
                        throw new MadLispException('exec: first item of list is not function');
                    }

                    $stack[] = $func->call($args);
                    break;

                case OpCode::CALL_CORE:
                    $coreFuncId = $code[$pc++];
                    $arity = $code[$pc++];
                    $args = $arity == 0 ? [] : array_splice($stack, -$arity);

                    switch ($coreFuncId) {
                        case CoreFuncId::ADD:
                            $result = 0;
                            foreach ($args as $arg) {
                                $result += $arg;
                            }
                            break;

                        case CoreFuncId::SUBTRACT:
                            if ($arity == 1) {
                                $result = -$args[0];
                            } else {
                                $result = $args[0];
                                for ($i = 1; $i < $arity; $i++) {
                                    $result -= $args[$i];
                                }
                            }
                            break;

                        case CoreFuncId::MULTIPLY:
                            $result = $args[0];
                            for ($i = 1; $i < $arity; $i++) {
                                $result *= $args[$i];
                            }
                            break;

                        case CoreFuncId::DIVIDE:
                            $result = $args[0];
                            for ($i = 1; $i < $arity; $i++) {
                                $result /= $args[$i];
                            }
                            break;

                        case CoreFuncId::INTDIV:
                            $result = $args[0];
                            for ($i = 1; $i < $arity; $i++) {
                                $result = intdiv($result, $args[$i]);
                            }
                            break;

                        case CoreFuncId::MODULO:
                            $result = $args[0];
                            for ($i = 1; $i < $arity; $i++) {
                                $result %= $args[$i];
                            }
                            break;

                        case CoreFuncId::INC:
                            $result = $args[0] + 1;
                            break;

                        case CoreFuncId::DEC:
                            $result = $args[0] - 1;
                            break;

                        case CoreFuncId::MAX:
                            $result = max(($args[0] instanceof Seq) ? $args[0]->getData() : $args);
                            break;

                        case CoreFuncId::MIN:
                            $result = min(($args[0] instanceof Seq) ? $args[0]->getData() : $args);
                            break;

                        default:
                            throw new MadLispException("unknown core function id $coreFuncId");
                    }

                    $stack[] = $result;
                    break;

                case OpCode::RETURN:
                    return array_pop($stack);
            }
        }
    }
}
