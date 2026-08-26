<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\IrCompiledProgram;
use MadLisp\Env;
use MadLisp\IrExecutionFrame;

class IrExecutionFrameTest extends TestCase
{
    public function testInitializesFrameStateFromProgram(): void
    {
        $program = new IrCompiledProgram([], [], 2);
        $env = new Env('root');
        $frame = new IrExecutionFrame($program, $env);

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
        $program = new IrCompiledProgram([], [], 1);
        $env = new Env('root');
        $frame = new IrExecutionFrame($program, $env, 4, 12, ['captured']);

        $this->assertSame(4, $frame->stackBase);
        $this->assertSame(12, $frame->returnPc);
        $this->assertSame(['captured'], $frame->captures);
        $this->assertSame([null], $frame->locals);
    }
}
