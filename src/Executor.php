<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Executor
{
    public function execute(CompiledProgram $program, Env $env)
    {
        $stack = [];
        $frames = [new ExecutionFrame($program, $env)];

        while ($frames) {
            $frame = $frames[array_key_last($frames)];
            $code = $frame->program->getCode();
            $constants = $frame->program->getConstants();
            $pc = $frame->pc;
            $env = $frame->env;

            if ($pc >= count($code)) {
                throw new MadLispException('exec: frame ended without returning');
            }

            $opcode = $code[$pc++];

            switch ($opcode) {
                case OpCode::LOAD_ENV:
                    $stack[] = $env;
                    break;

                case OpCode::UNDEF:
                    $nameIndex = $code[$pc++];
                    $stack[] = $env->unset($constants[$nameIndex]);
                    break;

                case OpCode::BUILD_VECTOR:
                    $valueCount = $code[$pc++];
                    $values = $valueCount == 0 ? [] : array_splice($stack, -$valueCount);
                    $stack[] = new Vector($values);
                    break;

                case OpCode::BUILD_HASH:
                    $keyIndex = $code[$pc++];
                    $valueCount = $code[$pc++];
                    $values = $valueCount == 0 ? [] : array_splice($stack, -$valueCount);
                    $keys = $constants[$keyIndex];
                    if (count($keys) != $valueCount) {
                        throw new MadLispException('exec: invalid hash key count');
                    }

                    $data = [];
                    foreach ($keys as $index => $key) {
                        $data[$key] = $values[$index];
                    }
                    $stack[] = new Hash($data);
                    break;

                case OpCode::LOAD_CONSTANT:
                    $constantIndex = $code[$pc++];
                    $stack[] = $constants[$constantIndex];
                    break;

                case OpCode::LOAD_GLOBAL:
                    $nameIndex = $code[$pc++];
                    $stack[] = $env->get($constants[$nameIndex]);
                    break;

                case OpCode::LOAD_LOCAL:
                    $localSlot = $code[$pc++];
                    if (!array_key_exists($localSlot, $frame->locals)) {
                        throw new MadLispException("exec: invalid local slot $localSlot");
                    }

                    $stack[] = $frame->locals[$localSlot];
                    break;

                case OpCode::LOAD_CAPTURE:
                    $captureIndex = $code[$pc++];
                    if (!array_key_exists($captureIndex, $frame->captures)) {
                        throw new MadLispException("exec: invalid capture slot $captureIndex");
                    }

                    $stack[] = $frame->captures[$captureIndex];
                    break;

                case OpCode::STORE_LOCAL:
                    $localSlot = $code[$pc++];
                    if (!array_key_exists($localSlot, $frame->locals)) {
                        throw new MadLispException("exec: invalid local slot $localSlot");
                    }

                    $frame->locals[$localSlot] = array_pop($stack);
                    break;

                case OpCode::STORE_GLOBAL:
                    $nameIndex = $code[$pc++];
                    $stack[] = $env->set($constants[$nameIndex], array_pop($stack));
                    break;

                case OpCode::POP:
                    array_pop($stack);
                    break;

                case OpCode::JUMP_IF_FALSE:
                    $target = $code[$pc++];
                    $condition = array_pop($stack);

                    if ($condition != true) {
                        $pc = $target;
                    }
                    break;

                case OpCode::JUMP_IF_FALSE_KEEP:
                    $target = $code[$pc++];
                    if ($stack[count($stack) - 1] != true) {
                        $pc = $target;
                    }
                    break;

                case OpCode::JUMP_IF_TRUE_KEEP:
                    $target = $code[$pc++];
                    if ($stack[count($stack) - 1] == true) {
                        $pc = $target;
                    }
                    break;

                case OpCode::JUMP:
                    $pc = $code[$pc];
                    break;

                case OpCode::CASE_COMPARE:
                    $right = array_pop($stack);
                    $left = array_pop($stack);
                    $stack[] = Util::valueForCompare($left) == Util::valueForCompare($right);
                    break;

                case OpCode::CASE_COMPARE_STRICT:
                    $right = array_pop($stack);
                    $left = array_pop($stack);
                    $stack[] = Util::valueForCompare($left) === Util::valueForCompare($right);
                    break;

                case OpCode::MAKE_FUNCTION:
                    $templateIndex = $code[$pc++];
                    $template = $constants[$templateIndex];
                    if (!($template instanceof CompiledFuncTemplate)) {
                        throw new MadLispException('exec: invalid compiled function template');
                    }

                    $captures = [];
                    foreach ($template->captureSources as $captureIndex => $source) {
                        if ($source['kind'] == 'local') {
                            $sourceIndex = $source['index'];
                            if (!array_key_exists($sourceIndex, $frame->locals)) {
                                throw new MadLispException("exec: invalid local slot $sourceIndex");
                            }

                            $captures[$captureIndex] = $frame->locals[$sourceIndex];
                        } elseif ($source['kind'] == 'capture') {
                            $sourceIndex = $source['index'];
                            if (!array_key_exists($sourceIndex, $frame->captures)) {
                                throw new MadLispException("exec: invalid capture slot $sourceIndex");
                            }

                            $captures[$captureIndex] = $frame->captures[$sourceIndex];
                        } else {
                            throw new MadLispException('exec: invalid capture source');
                        }
                    }

                    $stack[] = new CompiledFunc($template->program, $env, $template->arity, $captures);
                    break;

                case OpCode::TAIL_CALL:
                case OpCode::CALL:
                    $tailCall = $opcode == OpCode::TAIL_CALL;
                    $arity = $code[$pc++];
                    $args = $arity == 0 ? [] : array_splice($stack, -$arity);
                    $func = array_pop($stack);

                    if (!($func instanceof Func)) {
                        throw new MadLispException('exec: first item of list is not function');
                    }

                    if ($func instanceof CompiledFunc) {
                        if ($arity != $func->getArity()) {
                            throw new MadLispException(sprintf(
                                'exec: compiled function requires exactly %d argument%s',
                                $func->getArity(),
                                $func->getArity() == 1 ? '' : 's'
                            ));
                        }

                        $callerBase = count($stack);
                        $thisFrame = $frame;
                        $thisFrame->pc = $pc;
                        if ($tailCall) {
                            array_pop($frames);
                            $callerBase = $thisFrame->stackBase;
                        }
                        $callee = new ExecutionFrame(
                            $func->getProgram(),
                            $func->getEnv(),
                            $callerBase,
                            null,
                            $func->getCaptures()
                        );
                        foreach ($args as $index => $arg) {
                            $callee->locals[$index] = $arg;
                        }

                        $frames[] = $callee;
                        continue 2;
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
                            throw new MadLispException("exec: unknown core function id $coreFuncId");
                    }

                    $stack[] = $result;
                    break;

                case OpCode::RETURN:
                    if (count($stack) - $frame->stackBase !== 1) {
                        throw new MadLispException('exec: frame must return exactly one value');
                    }

                    $result = array_pop($stack);
                    array_pop($frames);
                    if (!$frames) {
                        return $result;
                    }

                    $stack[] = $result;
                    break;
            }

            $frame->pc = $pc;
        }
    }
}
