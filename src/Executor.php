<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

namespace MadLisp;

class Executor
{
    public function __construct(
        protected ?CompiledLoader $loader = null
    ) {

    }

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

                case OpCode::EXECUTE_PROGRAM:
                    $childProgram = array_pop($stack);
                    if (!($childProgram instanceof CompiledProgram)) {
                        throw new MadLispException('exec: execute requires a CompiledProgram');
                    }

                    $frame->pc = $pc;
                    $frames[] = new ExecutionFrame($childProgram, $env, count($stack));
                    continue 2;

                case OpCode::LOAD_FILE:
                    $filename = array_pop($stack);
                    if (!is_string($filename)) {
                        throw new MadLispException('exec: load filename is not string');
                    }
                    if ($this->loader === null) {
                        throw new MadLispException('exec: no compiled loader configured');
                    }

                    $childProgram = $this->loader->load($filename);
                    $frame->pc = $pc;
                    $frames[] = new ExecutionFrame($childProgram, $env, count($stack));
                    continue 2;

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

                        case CoreFuncId::EQUAL:
                            $result = Util::valueForCompare($args[0]) == Util::valueForCompare($args[1]);
                            break;

                        case CoreFuncId::STRICT_EQUAL:
                            $result = Util::valueForCompare($args[0]) === Util::valueForCompare($args[1]);
                            break;

                        case CoreFuncId::NOT_EQUAL:
                            $result = Util::valueForCompare($args[0]) != Util::valueForCompare($args[1]);
                            break;

                        case CoreFuncId::STRICT_NOT_EQUAL:
                            $result = Util::valueForCompare($args[0]) !== Util::valueForCompare($args[1]);
                            break;

                        case CoreFuncId::LESS:
                            $result = $args[0] < $args[1];
                            break;

                        case CoreFuncId::LESS_EQUAL:
                            $result = $args[0] <= $args[1];
                            break;

                        case CoreFuncId::GREATER:
                            $result = $args[0] > $args[1];
                            break;

                        case CoreFuncId::GREATER_EQUAL:
                            $result = $args[0] >= $args[1];
                            break;

                        case CoreFuncId::HASH:
                            $result = Util::makeHash($args);
                            break;

                        case CoreFuncId::LIST:
                            $result = new MList($args);
                            break;

                        case CoreFuncId::VECTOR:
                            $result = new Vector($args);
                            break;

                        case CoreFuncId::RANGE:
                            if ($arity == 1) {
                                $data = range(0, $args[0] - 1);
                            } else {
                                $data = range($args[0], $args[1] - 1, $args[2] ?? 1);
                            }
                            $result = new Vector($data);
                            break;

                        case CoreFuncId::LTOV:
                            $result = new Vector($args[0]->getData());
                            break;

                        case CoreFuncId::VTOL:
                            $result = new MList($args[0]->getData());
                            break;

                        case CoreFuncId::EMPTY:
                            if ($args[0] instanceof Collection) {
                                $result = $args[0]->count() === 0;
                            } elseif (is_string($args[0])) {
                                $result = $args[0] === '';
                            } else {
                                throw new MadLispException('argument to empty? is not collection or string');
                            }
                            break;

                        case CoreFuncId::CONTAINS:
                            $result = in_array($args[1], $args[0]->getData(), $args[2] ?? false);
                            break;

                        case CoreFuncId::GET:
                            $result = $args[0]->get($args[1]);
                            break;

                        case CoreFuncId::LEN:
                            if ($args[0] instanceof Collection) {
                                $result = $args[0]->count();
                            } elseif (is_string($args[0])) {
                                $result = strlen($args[0]);
                            } else {
                                throw new MadLispException('argument to len is not collection or string');
                            }
                            break;

                        case CoreFuncId::CAR:
                        case CoreFuncId::FIRST:
                            $result = $args[0]->getData()[0] ?? null;
                            break;

                        case CoreFuncId::LAST:
                            $result = $args[0]->getData()[$args[0]->count() - 1] ?? null;
                            break;

                        case CoreFuncId::HEAD:
                            $result = $args[0]::new(array_slice($args[0]->getData(), 0, $args[0]->count() - 1));
                            break;

                        case CoreFuncId::CDR:
                        case CoreFuncId::TAIL:
                            $result = $args[0]::new(array_slice($args[0]->getData(), 1));
                            break;

                        case CoreFuncId::SLICE:
                            $result = $args[0]::new(array_slice($args[0]->getData(), $args[1], $args[2] ?? null));
                            break;

                        case CoreFuncId::APPLY:
                            $func = $args[0];
                            $seq = $args[$arity - 1];
                            if (!($func instanceof Func)) {
                                throw new MadLispException('first argument to apply is not function');
                            } elseif (!($seq instanceof Seq)) {
                                throw new MadLispException('last argument to apply is not sequence');
                            }
                            $applyArgs = array_slice($args, 1, -1);
                            foreach ($seq->getData() as $arg) {
                                $applyArgs[] = $arg;
                            }
                            $result = $func->call($applyArgs);
                            break;

                        case CoreFuncId::CHUNK:
                            $chunks = array_chunk($args[0]->getData(), $args[1]);
                            $data = [];
                            foreach ($chunks as $chunk) {
                                $data[] = $args[0]::new($chunk);
                            }
                            $result = $args[0]::new($data);
                            break;

                        case CoreFuncId::CONCAT:
                            $data = [];
                            foreach ($args as $arg) {
                                $data[] = $arg->getData();
                            }
                            $result = new MList(array_merge(...$data));
                            break;

                        case CoreFuncId::PUSH:
                            $data = $args[0]->getData();
                            for ($i = 1; $i < $arity; $i++) {
                                $data[] = $args[$i];
                            }
                            $result = $args[0]::new($data);
                            break;

                        case CoreFuncId::CONS:
                            $seq = $args[$arity - 1];
                            if (!($seq instanceof Seq)) {
                                throw new MadLispException('last argument to cons is not sequence');
                            }
                            $data = array_slice($args, 0, -1);
                            $result = $seq::new(array_merge($data, $seq->getData()));
                            break;

                        case CoreFuncId::MAP:
                            $result = $args[1]::new(array_map($args[0]->getClosure(), $args[1]->getData()));
                            break;

                        case CoreFuncId::MAP2:
                            if ($args[1]->count() != $args[2]->count()) {
                                throw new MadLispException('map2 requires equal number of elements in both sequences');
                            }
                            $result = $args[1]::new(array_map($args[0]->getClosure(), $args[1]->getData(), $args[2]->getData()));
                            break;

                        case CoreFuncId::REDUCE:
                            $result = array_reduce($args[1]->getData(), $args[0]->getClosure(), $args[2] ?? null);
                            break;

                        case CoreFuncId::FILTER:
                            $result = $args[1]::new(array_values(array_filter($args[1]->getData(), $args[0]->getClosure())));
                            break;

                        case CoreFuncId::FILTERH:
                            $result = new Hash(array_filter($args[1]->getData(), $args[0]->getClosure(), ARRAY_FILTER_USE_BOTH));
                            break;

                        case CoreFuncId::REVERSE:
                            if ($args[0] instanceof Seq) {
                                $result = $args[0]::new(array_reverse($args[0]->getData()));
                            } elseif (is_string($args[0])) {
                                $result = strrev($args[0]);
                            } else {
                                throw new MadLispException('argument to reverse is not sequence or string');
                            }
                            break;

                        case CoreFuncId::KEY:
                            $result = $args[0]->has($args[1]);
                            break;

                        case CoreFuncId::SET:
                            $hash = new Hash($args[0]->getData());
                            $hash->set($args[1], $args[2]);
                            $result = $hash;
                            break;

                        case CoreFuncId::SET_MUTATE:
                            $result = $args[0]->set($args[1], $args[2]);
                            break;

                        case CoreFuncId::UNSET:
                            $data = $args[0]->getData();
                            unset($data[$args[1]]);
                            $result = new Hash($data);
                            break;

                        case CoreFuncId::UNSET_MUTATE:
                            $result = $args[0]->unset($args[1]);
                            break;

                        case CoreFuncId::KEYS:
                            $result = new MList(array_keys($args[0]->getData()));
                            break;

                        case CoreFuncId::VALUES:
                            $result = new MList(array_values($args[0]->getData()));
                            break;

                        case CoreFuncId::ZIP:
                            if ($args[0]->count() != $args[1]->count()) {
                                throw new MadLispException('zip requires equal number of keys and values');
                            }
                            $result = new Hash(array_combine($args[0]->getData(), $args[1]->getData()));
                            break;

                        case CoreFuncId::SORT:
                            $data = $args[0]->getData();
                            sort($data);
                            $result = $args[0]::new($data);
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
