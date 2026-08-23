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
                    $args = $arity == 0 ? [] : array_fill(0, $arity, null);
                    for ($index = $arity - 1; $index >= 0; $index--) {
                        $args[$index] = array_pop($stack);
                    }
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
                    $args = $arity == 0 ? [] : array_fill(0, $arity, null);
                    for ($index = $arity - 1; $index >= 0; $index--) {
                        $args[$index] = array_pop($stack);
                    }

                    $continuationTypes = [
                        CoreFuncId::APPLY => 'apply',
                        CoreFuncId::MAP => 'map',
                        CoreFuncId::MAP2 => 'map2',
                        CoreFuncId::REDUCE => 'reduce',
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

                    // A callback frame may return to a suspended higher-order
                    // collection operation instead of directly to its caller.
                    if ($this->resumeContinuation($frames[array_key_last($frames)], $frames, $stack, $result)) {
                        continue 2;
                    }

                    $stack[] = $result;
                    break;
            }

            $frame->pc = $pc;
        }
    }

    private function startCollectionContinuation(
        string $type,
        ExecutionFrame $caller,
        array &$frames,
        array &$stack,
        CompiledFunc $function,
        Collection $sequence,
        array $args
    ): bool {
        $data = $sequence->getData();
        $hasInitial = $type === 'reduce' && count($args) > 2;
        $index = $type === 'reduce' && !$hasInitial ? 1 : 0;
        $carry = $type === 'reduce' && !$hasInitial ? $data[0] : ($args[2] ?? null);
        $caller->continuation = [
            'type' => $type,
            'function' => $function,
            'sequence' => $sequence,
            'data' => $data,
            'values' => array_values($data),
            'index' => $index,
            'result' => [],
            'carry' => $carry,
            'prefix' => $type === 'apply' ? array_slice($args, 1, -1) : [],
            'secondData' => $type === 'map2' ? array_values($args[2]->getData()) : [],
            'keys' => $type === 'filterh' ? array_keys($data) : [],
        ];

        if ($type === 'apply') {
            $this->pushCallbackFrame(
                $frames,
                $stack,
                $function,
                array_merge($caller->continuation['prefix'], $data)
            );
            return true;
        }

        if ($index >= count($data)) {
            $caller->continuation = null;
            $stack[] = match ($type) {
                'reduce' => $carry,
                'filterh' => new Hash([]),
                default => $sequence::new([]),
            };
            return false;
        }

        $this->pushCallbackFrame($frames, $stack, $function, $this->callbackArguments($caller->continuation, $index));
        return true;
    }

    private function resumeContinuation(
        ExecutionFrame $caller,
        array &$frames,
        array &$stack,
        $result
    ): bool {
        if ($caller->continuation === null) {
            return false;
        }

        $state =& $caller->continuation;
        $index = $state['index'];
        switch ($state['type']) {
            case 'map':
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
            case 'reduce':
                $state['carry'] = $result;
                break;
            case 'apply':
                $caller->continuation = null;
                $stack[] = $result;
                return true;
        }
        $index++;
        $state['index'] = $index;

        if ($index < count($state['data'])) {
            $this->pushCallbackFrame($frames, $stack, $state['function'], $this->callbackArguments($state, $index));
            return true;
        }

        $final = $state['type'] === 'reduce'
            ? $state['carry']
            : ($state['type'] === 'filterh' ? new Hash($state['result']) : $state['sequence']::new($state['result']));
        $caller->continuation = null;
        $stack[] = $final;
        return true;
    }

    private function callbackArguments(array $state, int $index): array
    {
        return match ($state['type']) {
            'map', 'filter' => [$state['data'][$index]],
            'map2' => [$state['data'][$index], $state['secondData'][$index]],
            'reduce' => [$state['carry'], $state['data'][$index]],
            'filterh' => [$state['values'][$index], $state['keys'][$index]],
            'apply' => array_merge($state['prefix'], [$state['data'][$index]]),
        };
    }

    private function pushCallbackFrame(
        array &$frames,
        array &$stack,
        CompiledFunc $function,
        array $args
    ): void {
        $frame = new ExecutionFrame(
            $function->getProgram(),
            $function->getEnv(),
            count($stack),
            null,
            $function->getCaptures()
        );
        foreach ($args as $index => $arg) {
            $frame->locals[$index] = $arg;
        }
        $frames[] = $frame;
    }
}
