<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CompiledProgram;
use MadLisp\Env;
use MadLisp\ExecutionFrame;

class ExecutionFrameTest extends TestCase
{
    public function testInitializesFrameStateFromProgram(): void
    {
        $program = new CompiledProgram([], [], 2);
        $env = new Env('root');
        $frame = new ExecutionFrame($program, $env);

        $this->assertSame($program, $frame->program);
        $this->assertSame($env, $frame->env);
        $this->assertSame(0, $frame->pc);
        $this->assertSame([], $frame->captures);
        $this->assertSame(0, $frame->stackBase);
        $this->assertNull($frame->returnPc);
        $this->assertSame([null, null], $frame->locals);
    }

    public function testAcceptsStackReturnAndCaptureState(): void
    {
        $program = new CompiledProgram([], [], 1);
        $env = new Env('root');
        $frame = new ExecutionFrame($program, $env, 4, 12, ['captured']);

        $this->assertSame(4, $frame->stackBase);
        $this->assertSame(12, $frame->returnPc);
        $this->assertSame(['captured'], $frame->captures);
        $this->assertSame([null], $frame->locals);
    }
}
