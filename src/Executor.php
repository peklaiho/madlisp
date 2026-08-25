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
        $stackPointer = 0;
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
                    $stack[$stackPointer++] = $env;
                    break;

                case OpCode::UNDEF:
                    $nameIndex = $code[$pc++];
                    $stack[$stackPointer++] = $env->unset($constants[$nameIndex]);
                    break;

                case OpCode::BUILD_VECTOR:
                    $valueCount = $code[$pc++];
                    $values = $valueCount == 0 ? [] : array_slice($stack, $stackPointer - $valueCount, $valueCount);
                    $stackPointer -= $valueCount;
                    $stack[$stackPointer++] = new Vector($values);
                    break;

                case OpCode::BUILD_HASH:
                    $keyIndex = $code[$pc++];
                    $valueCount = $code[$pc++];
                    $values = $valueCount == 0 ? [] : array_slice($stack, $stackPointer - $valueCount, $valueCount);
                    $stackPointer -= $valueCount;
                    $keys = $constants[$keyIndex];
                    if (count($keys) != $valueCount) {
                        throw new MadLispException('exec: invalid hash key count');
                    }

                    $data = [];
                    foreach ($keys as $index => $key) {
                        $data[$key] = $values[$index];
                    }
                    $stack[$stackPointer++] = new Hash($data);
                    break;

                case OpCode::LOAD_CONSTANT:
                    $constantIndex = $code[$pc++];
                    $stack[$stackPointer++] = $constants[$constantIndex];
                    break;

                case OpCode::EXECUTE_PROGRAM:
                    $childProgram = $stack[--$stackPointer];
                    if (!($childProgram instanceof CompiledProgram)) {
                        throw new MadLispException('exec: execute requires a CompiledProgram');
                    }

                    $frame->pc = $pc;
                    $frames[] = new ExecutionFrame($childProgram, $env, $stackPointer);
                    continue 2;

                case OpCode::LOAD_FILE:
                    $filename = $stack[--$stackPointer];
                    if (!is_string($filename)) {
                        throw new MadLispException('exec: load filename is not string');
                    }
                    if ($this->loader === null) {
                        throw new MadLispException('exec: no compiled loader configured');
                    }

                    $childProgram = $this->loader->load($filename);
                    $frame->pc = $pc;
                    $frames[] = new ExecutionFrame($childProgram, $env, $stackPointer);
                    continue 2;

                case OpCode::LOAD_GLOBAL:
                    $nameIndex = $code[$pc++];
                    $stack[$stackPointer++] = $env->get($constants[$nameIndex]);
                    break;

                case OpCode::LOAD_LOCAL:
                    $localSlot = $code[$pc++];
                    if (!array_key_exists($localSlot, $frame->locals)) {
                        throw new MadLispException("exec: invalid local slot $localSlot");
                    }

                    $stack[$stackPointer++] = $frame->locals[$localSlot];
                    break;

                case OpCode::LOAD_CAPTURE:
                    $captureIndex = $code[$pc++];
                    if (!array_key_exists($captureIndex, $frame->captures)) {
                        throw new MadLispException("exec: invalid capture slot $captureIndex");
                    }

                    $stack[$stackPointer++] = $frame->captures[$captureIndex];
                    break;

                case OpCode::STORE_LOCAL:
                    $localSlot = $code[$pc++];
                    if (!array_key_exists($localSlot, $frame->locals)) {
                        throw new MadLispException("exec: invalid local slot $localSlot");
                    }

                    $frame->locals[$localSlot] = $stack[--$stackPointer];
                    break;

                case OpCode::STORE_GLOBAL:
                    $nameIndex = $code[$pc++];
                    $value = $stack[--$stackPointer];
                    $stack[$stackPointer++] = $env->set($constants[$nameIndex], $value);
                    break;

                case OpCode::POP:
                    $stackPointer--;
                    break;

                case OpCode::JUMP_IF_FALSE:
                    $target = $code[$pc++];
                    $condition = $stack[--$stackPointer];

                    if ($condition != true) {
                        $pc = $target;
                    }
                    break;

                case OpCode::JUMP_IF_FALSE_KEEP:
                    $target = $code[$pc++];
                    if ($stack[$stackPointer - 1] != true) {
                        $pc = $target;
                    }
                    break;

                case OpCode::JUMP_IF_TRUE_KEEP:
                    $target = $code[$pc++];
                    if ($stack[$stackPointer - 1] == true) {
                        $pc = $target;
                    }
                    break;

                case OpCode::JUMP:
                    $pc = $code[$pc];
                    break;

                case OpCode::CASE_COMPARE:
                    $right = $stack[--$stackPointer];
                    $left = $stack[--$stackPointer];
                    $stack[$stackPointer++] = Util::valueForCompare($left) == Util::valueForCompare($right);
                    break;

                case OpCode::CASE_COMPARE_STRICT:
                    $right = $stack[--$stackPointer];
                    $left = $stack[--$stackPointer];
                    $stack[$stackPointer++] = Util::valueForCompare($left) === Util::valueForCompare($right);
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

                    $stack[$stackPointer++] = new CompiledFunc($template->program, $env, $template->arity, $captures);
                    break;

                case OpCode::TAIL_CALL:
                case OpCode::CALL:
                    $tailCall = $opcode == OpCode::TAIL_CALL;
                    $arity = $code[$pc++];
                    $args = $arity == 0 ? [] : array_slice($stack, $stackPointer - $arity, $arity);
                    $stackPointer -= $arity;
                    $func = $stack[--$stackPointer];

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

                        $callerBase = $stackPointer;
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

                    $stack[$stackPointer++] = $func->call($args);
                    break;

                case OpCode::MAP:
                case OpCode::REDUCE:
                    $arity = $code[$pc++];
                    $args = $arity == 0 ? [] : array_slice($stack, $stackPointer - $arity, $arity);
                    $stackPointer -= $arity;
                    $function = $args[0] ?? null;
                    $sequence = $args[1] ?? null;

                    if ($function instanceof CompiledFunc) {
                        $frame->pc = $pc;
                        if ($this->startCompiledCollectionOperation(
                            $opcode,
                            $frame,
                            $frames,
                            $stack,
                            $stackPointer,
                            $function,
                            $sequence,
                            $args
                        )) {
                            continue 2;
                        }
                        break;
                    }

                    if (!($function instanceof Func)) {
                        throw new MadLispException('exec: first argument of collection operation is not function');
                    }
                    if (!($sequence instanceof Seq)) {
                        throw new MadLispException('exec: argument to collection operation is not sequence');
                    }

                    if ($opcode === OpCode::MAP) {
                        $stack[$stackPointer++] = $sequence::new(
                            array_map($function->getClosure(), $sequence->getData())
                        );
                    } else {
                        $stack[$stackPointer++] = array_reduce(
                            $sequence->getData(),
                            $function->getClosure(),
                            $args[2] ?? null
                        );
                    }
                    break;

                case OpCode::CALL_CORE:
                    $coreFuncId = $code[$pc++];
                    $arity = $code[$pc++];
                    $args = $arity == 0 ? [] : array_slice($stack, $stackPointer - $arity, $arity);
                    $stackPointer -= $arity;

                    $continuationTypes = [
                        CoreFuncId::APPLY => 'apply',
                        CoreFuncId::MAP2 => 'map2',
                        CoreFuncId::FILTER => 'filter',
                        CoreFuncId::FILTERH => 'filterh',
                    ];

                    // Higher-order collection functions need two execution paths.
                    // A CompiledFunc cannot be passed to PHP array_* functions because
                    // it has no PHP closure, so compiled callbacks are suspended into
                    // the VM frame stack and resumed through a continuation below.
                    // Closure-backed Func instances use the normal path below.
                    if (isset($continuationTypes[$coreFuncId])
                        && $args[0] instanceof CompiledFunc
                    ) {
                        $type = $continuationTypes[$coreFuncId];
                        $sequence = $type === 'apply' ? $args[$arity - 1] : $args[1];
                        if ($type === 'filterh' ? !($sequence instanceof Hash) : !($sequence instanceof Seq)) {
                            throw new MadLispException('exec: argument to collection operation is not sequence');
                        }
                        if ($type === 'map2' && $sequence->count() != $args[2]->count()) {
                            throw new MadLispException('exec: map2 requires equal number of elements in both sequences');
                        }

                        $frame->pc = $pc;
                        if (!$this->startCollectionContinuation(
                            $type,
                            $frame,
                            $frames,
                            $stack,
                            $stackPointer,
                            $args[0],
                            $sequence,
                            $args
                        )) {
                            break;
                        }
                        continue 2;
                    }

                    // This path handles ordinary core calls, including the
                    // closure-backed implementations of map, map2, reduce, filter,
                    // and filterh. CompiledFunc callbacks have already been handled
                    // by the continuation path above.
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
                                throw new MadLispException('exec: argument to empty? is not collection or string');
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
                                throw new MadLispException('exec: argument to len is not collection or string');
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
                                throw new MadLispException('exec: first argument to apply is not function');
                            } elseif (!($seq instanceof Seq)) {
                                throw new MadLispException('exec: last argument to apply is not sequence');
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
                                throw new MadLispException('exec: last argument to cons is not sequence');
                            }
                            $data = array_slice($args, 0, -1);
                            $result = $seq::new(array_merge($data, $seq->getData()));
                            break;

                        case CoreFuncId::MAP:
                            $result = $args[1]::new(array_map($args[0]->getClosure(), $args[1]->getData()));
                            break;

                        case CoreFuncId::MAP2:
                            if ($args[1]->count() != $args[2]->count()) {
                                throw new MadLispException('exec: map2 requires equal number of elements in both sequences');
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
                                throw new MadLispException('exec: argument to reverse is not sequence or string');
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
                                throw new MadLispException('exec: zip requires equal number of keys and values');
                            }
                            $result = new Hash(array_combine($args[0]->getData(), $args[1]->getData()));
                            break;

                        case CoreFuncId::SORT:
                            $data = $args[0]->getData();
                            sort($data);
                            $result = $args[0]::new($data);
                            break;

                        case CoreFuncId::BOOL:
                            $result = boolval($args[0]);
                            break;

                        case CoreFuncId::FLOAT:
                            $result = floatval($args[0]);
                            break;

                        case CoreFuncId::INT:
                            $result = intval($args[0]);
                            break;

                        case CoreFuncId::STR:
                            $data = [];
                            foreach ($args as $arg) {
                                $data[] = $arg instanceof Symbol ? $arg->getName() : strval($arg);
                            }
                            $result = implode('', $data);
                            break;

                        case CoreFuncId::SYMBOL:
                            $result = new Symbol($args[0]);
                            break;

                        case CoreFuncId::NOT:
                            $result = !$args[0];
                            break;

                        case CoreFuncId::TYPE:
                            $value = $args[0];
                            if ($value instanceof Func) {
                                $result = $value->isMacro() ? 'macro' : 'function';
                            } elseif ($value instanceof MList) {
                                $result = 'list';
                            } elseif ($value instanceof Vector) {
                                $result = 'vector';
                            } elseif ($value instanceof Hash) {
                                $result = 'hash';
                            } elseif ($value instanceof Symbol) {
                                $result = 'symbol';
                            } elseif (is_object($value)) {
                                $result = 'object';
                            } elseif (is_resource($value)) {
                                $result = 'resource';
                            } elseif ($value === true || $value === false) {
                                $result = 'bool';
                            } elseif ($value === null) {
                                $result = 'null';
                            } elseif (is_int($value)) {
                                $result = 'int';
                            } elseif (is_float($value)) {
                                $result = 'float';
                            } else {
                                $result = 'string';
                            }
                            break;

                        case CoreFuncId::FUNCTION:
                            $result = $args[0] instanceof Func;
                            break;

                        case CoreFuncId::MACRO:
                            $result = $args[0] instanceof Func && $args[0]->isMacro();
                            break;

                        case CoreFuncId::LIST_TYPE:
                            $result = $args[0] instanceof MList;
                            break;

                        case CoreFuncId::VECTOR_TYPE:
                            $result = $args[0] instanceof Vector;
                            break;

                        case CoreFuncId::SEQ_TYPE:
                            $result = $args[0] instanceof Seq;
                            break;

                        case CoreFuncId::HASH_TYPE:
                            $result = $args[0] instanceof Hash;
                            break;

                        case CoreFuncId::SYMBOL_TYPE:
                            $result = $args[0] instanceof Symbol;
                            break;

                        case CoreFuncId::OBJECT_TYPE:
                            $value = $args[0];
                            $result = !($value instanceof Func || $value instanceof Collection || $value instanceof Symbol)
                                && is_object($value);
                            break;

                        case CoreFuncId::RESOURCE_TYPE:
                            $result = is_resource($args[0]);
                            break;

                        case CoreFuncId::BOOL_TYPE:
                            $result = $args[0] === true || $args[0] === false;
                            break;

                        case CoreFuncId::TRUE:
                            $result = $args[0] == true;
                            break;

                        case CoreFuncId::FALSE:
                            $result = $args[0] == false;
                            break;

                        case CoreFuncId::NULL_TYPE:
                            $result = $args[0] === null;
                            break;

                        case CoreFuncId::INT_TYPE:
                            $result = is_int($args[0]);
                            break;

                        case CoreFuncId::FLOAT_TYPE:
                            $result = is_float($args[0]);
                            break;

                        case CoreFuncId::STR_TYPE:
                            $result = is_string($args[0]);
                            break;

                        case CoreFuncId::ZERO:
                            $result = $args[0] === 0;
                            break;

                        case CoreFuncId::ONE:
                            $result = $args[0] === 1;
                            break;

                        case CoreFuncId::EVEN:
                            $result = $args[0] % 2 === 0;
                            break;

                        case CoreFuncId::ODD:
                            $result = $args[0] % 2 !== 0;
                            break;

                        case CoreFuncId::TRIM:
                            $result = $arity == 1 ? trim($args[0]) : trim($args[0], $args[1]);
                            break;

                        case CoreFuncId::LTRIM:
                            $result = $arity == 1 ? ltrim($args[0]) : ltrim($args[0], $args[1]);
                            break;

                        case CoreFuncId::RTRIM:
                            $result = $arity == 1 ? rtrim($args[0]) : rtrim($args[0], $args[1]);
                            break;

                        case CoreFuncId::UPCASE:
                            $result = strtoupper($args[0]);
                            break;

                        case CoreFuncId::LOWCASE:
                            $result = strtolower($args[0]);
                            break;

                        case CoreFuncId::STRPOS:
                            $result = $arity == 2
                                ? strpos($args[0], $args[1])
                                : strpos($args[0], $args[1], $args[2]);
                            break;

                        case CoreFuncId::STRIPOS:
                            $result = $arity == 2
                                ? stripos($args[0], $args[1])
                                : stripos($args[0], $args[1], $args[2]);
                            break;

                        case CoreFuncId::SUBSTR:
                            $result = $arity == 2
                                ? substr($args[0], $args[1])
                                : substr($args[0], $args[1], $args[2]);
                            break;

                        case CoreFuncId::REPLACE:
                            $result = str_replace($args[1], $args[2], $args[0]);
                            break;

                        case CoreFuncId::SPLIT:
                            $result = new Vector(explode($args[0], $args[1]));
                            break;

                        case CoreFuncId::JOIN:
                            $result = implode($args[0], array_slice($args, 1));
                            break;

                        case CoreFuncId::FORMAT:
                            $formatArgs = array_slice($args, 1);
                            $result = sprintf($args[0], ...$formatArgs);
                            break;

                        case CoreFuncId::PREFIX:
                            $result = substr($args[0], 0, strlen($args[1])) === $args[1];
                            break;

                        case CoreFuncId::SUFFIX:
                            $result = substr($args[0], strlen($args[0]) - strlen($args[1])) === $args[1];
                            break;

                        case CoreFuncId::STRCMP:
                            $result = strcmp($args[0], $args[1]);
                            break;

                        case CoreFuncId::STRCASECMP:
                            $result = strcasecmp($args[0], $args[1]);
                            break;

                        case CoreFuncId::STRNATCMP:
                            $result = strnatcmp($args[0], $args[1]);
                            break;

                        case CoreFuncId::STRNATCASECMP:
                            $result = strnatcasecmp($args[0], $args[1]);
                            break;

                        default:
                            throw new MadLispException("exec: unknown core function id $coreFuncId");
                    }

                    $stack[$stackPointer++] = $result;
                    break;

                case OpCode::RETURN:
                    if ($stackPointer - $frame->stackBase !== 1) {
                        throw new MadLispException('exec: frame must return exactly one value');
                    }

                    $result = $stack[--$stackPointer];
                    array_pop($frames);
                    if (!$frames) {
                        return $result;
                    }

                    // A callback frame may return to a dedicated compiled
                    // collection operation instead of directly to its caller.
                    $caller = $frames[array_key_last($frames)];
                    if ($caller->collectionOperation !== null) {
                        $state =& $caller->collectionOperation;
                        $index = $state['index'];
                        if ($state['opcode'] === OpCode::MAP) {
                            $state['result'][] = $result;
                        } else {
                            $state['carry'] = $result;
                        }

                        $index++;
                        $state['index'] = $index;
                        if ($index >= $state['count']) {
                            $final = $state['opcode'] === OpCode::REDUCE
                                ? $state['carry']
                                : $state['sequence']::new($state['result']);
                            $caller->collectionOperation = null;
                            $stack[$stackPointer++] = $final;
                            continue 2;
                        }

                        $function = $state['function'];
                        $frame->program = $function->getProgram();
                        $frame->env = $function->getEnv();
                        $frame->pc = 0;
                        $frame->stackBase = $stackPointer;
                        $frame->returnPc = null;
                        $frame->captures = $function->getCaptures();
                        $frame->continuation = null;

                        $localCount = $frame->program->getLocalCount();
                        if (count($frame->locals) !== $localCount) {
                            $frame->locals = array_fill(0, $localCount, null);
                        }
                        $frame->locals[0] = $state['opcode'] === OpCode::REDUCE
                            ? $state['carry']
                            : $state['values'][$index];
                        if ($state['opcode'] === OpCode::REDUCE) {
                            $frame->locals[1] = $state['values'][$index];
                        }

                        $frames[] = $frame;
                        continue 2;
                    }

                    // The remaining continuation path handles collection
                    // operations that still require suspended VM frames.
                    if ($this->resumeContinuation($caller, $frames, $stack, $stackPointer, $result)) {
                        continue 2;
                    }

                    $stack[$stackPointer++] = $result;
                    break;
            }

            $frame->pc = $pc;
        }
    }

    private function startCompiledCollectionOperation(
        int $opcode,
        ExecutionFrame $caller,
        array &$frames,
        array &$stack,
        int &$stackPointer,
        CompiledFunc $function,
        $sequence,
        array $args
    ): bool {
        if (!($sequence instanceof Seq)) {
            throw new MadLispException('exec: argument to collection operation is not sequence');
        }
        if ($opcode === OpCode::MAP && count($args) !== 2) {
            throw new MadLispException('exec: map requires exactly 2 arguments');
        }
        if ($opcode === OpCode::REDUCE && (count($args) < 2 || count($args) > 3)) {
            throw new MadLispException('exec: reduce requires 1 or 2 arguments');
        }

        $arity = $opcode === OpCode::MAP ? 1 : 2;
        if ($function->getArity() !== $arity) {
            throw new MadLispException(sprintf(
                'exec: compiled function requires exactly %d argument%s',
                $arity,
                $arity === 1 ? '' : 's'
            ));
        }

        $values = array_values($sequence->getData());
        $hasInitial = $opcode === OpCode::REDUCE && count($args) === 3;
        $index = $opcode === OpCode::REDUCE && !$hasInitial ? 1 : 0;
        $carry = $opcode === OpCode::REDUCE
            ? ($hasInitial ? $args[2] : ($values[0] ?? null))
            : null;

        $caller->collectionOperation = [
            'opcode' => $opcode,
            'function' => $function,
            'sequence' => $sequence,
            'values' => $values,
            'count' => count($values),
            'index' => $index,
            'result' => [],
            'carry' => $carry,
        ];

        if ($index >= count($values)) {
            $result = $opcode === OpCode::REDUCE ? $carry : $sequence::new([]);
            $caller->collectionOperation = null;
            $stack[$stackPointer++] = $result;
            return false;
        }

        $callbackFrame = new ExecutionFrame(
            $function->getProgram(),
            $function->getEnv(),
            $stackPointer,
            null,
            $function->getCaptures()
        );
        $this->bindCompiledCollectionCallback($callbackFrame, $caller->collectionOperation);
        $frames[] = $callbackFrame;
        return true;
    }

    private function bindCompiledCollectionCallback(ExecutionFrame $frame, array $state): void
    {
        $frame->locals[0] = $state['opcode'] === OpCode::REDUCE
            ? $state['carry']
            : $state['values'][$state['index']];
        if ($state['opcode'] === OpCode::REDUCE) {
            $frame->locals[1] = $state['values'][$state['index']];
        }
    }

    private function startCollectionContinuation(
        string $type,
        ExecutionFrame $caller,
        array &$frames,
        array &$stack,
        int &$stackPointer,
        CompiledFunc $function,
        Collection $sequence,
        array $args
    ): bool {
        $data = $sequence->getData();
        $index = 0;
        $caller->continuation = [
            'type' => $type,
            'function' => $function,
            'sequence' => $sequence,
            'data' => $data,
            'values' => array_values($data),
            'index' => $index,
            'result' => [],
            'prefix' => $type === 'apply' ? array_slice($args, 1, -1) : [],
            'secondData' => $type === 'map2' ? array_values($args[2]->getData()) : [],
            'keys' => $type === 'filterh' ? array_keys($data) : [],
        ];

        if ($type === 'apply') {
            $this->pushCallbackFrame(
                $frames,
                $stack,
                $stackPointer,
                $function,
                array_merge($caller->continuation['prefix'], $data)
            );
            return true;
        }

        if ($index >= count($data)) {
            $caller->continuation = null;
            $stack[$stackPointer++] = $type === 'filterh'
                ? new Hash([])
                : $sequence::new([]);
            return false;
        }

        $this->pushCallbackFrame($frames, $stack, $stackPointer, $function, $this->callbackArguments($caller->continuation, $index));
        return true;
    }

    private function resumeContinuation(
        ExecutionFrame $caller,
        array &$frames,
        array &$stack,
        int &$stackPointer,
        $result
    ): bool {
        if ($caller->continuation === null) {
            return false;
        }

        $state =& $caller->continuation;
        $index = $state['index'];
        switch ($state['type']) {
            case 'map2':
                $state['result'][] = $result;
                break;
            case 'filter':
                if ($result) {
                    $state['result'][] = $state['data'][$index];
                }
                break;
            case 'filterh':
                if ($result) {
                    $key = $state['keys'][$index];
                    $state['result'][$key] = $state['values'][$index];
                }
                break;
            case 'apply':
                $caller->continuation = null;
                $stack[$stackPointer++] = $result;
                return true;
        }
        $index++;
        $state['index'] = $index;

        if ($index < count($state['data'])) {
            $this->pushCallbackFrame($frames, $stack, $stackPointer, $state['function'], $this->callbackArguments($state, $index));
            return true;
        }

        $final = $state['type'] === 'filterh'
            ? new Hash($state['result'])
            : $state['sequence']::new($state['result']);
        $caller->continuation = null;
        $stack[$stackPointer++] = $final;
        return true;
    }

    private function callbackArguments(array $state, int $index): array
    {
        return match ($state['type']) {
            'map2' => [$state['data'][$index], $state['secondData'][$index]],
            'filter' => [$state['data'][$index]],
            'filterh' => [$state['values'][$index], $state['keys'][$index]],
            'apply' => array_merge($state['prefix'], [$state['data'][$index]]),
        };
    }

    private function pushCallbackFrame(
        array &$frames,
        array &$stack,
        int &$stackPointer,
        CompiledFunc $function,
        array $args
    ): void {
        $frame = new ExecutionFrame(
            $function->getProgram(),
            $function->getEnv(),
            $stackPointer,
            null,
            $function->getCaptures()
        );
        foreach ($args as $index => $arg) {
            $frame->locals[$index] = $arg;
        }
        $frames[] = $frame;
    }
}
