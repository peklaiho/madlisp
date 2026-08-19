<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\Compiler;
use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Executor;
use MadLisp\Lisp;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Tokenizer;
use MadLisp\Lib\Math;

class LispTest extends TestCase
{
    public function testGetEnv()
    {
        $env = new Env('env');

        $lisp = new Lisp(
            $this->createMock(Tokenizer::class),
            $this->createMock(Reader::class),
            $this->createMock(Compiler::class),
            $this->createMock(Executor::class),
            $this->createMock(Evaller::class),
            $this->createMock(Printer::class),
            $env
        );

        $this->assertSame($env, $lisp->getEnv());
    }

    public function testPrint()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $compiler = $this->createMock(Compiler::class);
        $executor = $this->createMock(Executor::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $printer->expects($this->once())
            ->method('print')
            ->with($this->equalTo("abc"), $this->equalTo(false));

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, $printer, new Env('env'));

        $lisp->print('abc', false);
    }

    public function testPstr()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $compiler = $this->createMock(Compiler::class);
        $executor = $this->createMock(Executor::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $printer->expects($this->once())
            ->method('pstr')
            ->with($this->equalTo('abc'), $this->equalTo(true))
            ->willReturn('"abc"');

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, $printer, new Env('env'));

        $this->assertSame('"abc"', $lisp->pstr('abc', true));
    }

    public function testReadEval()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $compiler = $this->createMock(Compiler::class);
        $executor = $this->createMock(Executor::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $tokenizer->expects($this->once())
            ->method('tokenize');

        $reader->expects($this->once())
            ->method('read');

        $evaller->expects($this->once())
            ->method('eval');

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, $printer, new Env('env'));

        $lisp->readEval('abc');
    }

    public function testReadEvalCompiledExecutesCompiledProgram(): void
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $compiler = $this->createMock(Compiler::class);
        $executor = $this->createMock(Executor::class);
        $evaller = $this->createMock(Evaller::class);
        $env = new Env('env');
        $program = new \MadLisp\CompiledProgram([], [], 0);

        $tokenizer->expects($this->once())
            ->method('tokenize')
            ->with('input')
            ->willReturn(['token']);

        $reader->expects($this->once())
            ->method('read')
            ->with(['token'])
            ->willReturn('ast');

        $compiler->expects($this->once())
            ->method('compile')
            ->with('ast', $env)
            ->willReturn($program);

        $executor->expects($this->once())
            ->method('execute')
            ->with($program, $env)
            ->willReturn('compiled result');

        $evaller->expects($this->never())
            ->method('eval');

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, new Printer(), $env);

        $this->assertSame('compiled result', $lisp->readEvalCompiled('input'));
    }

    public function testReadEvalCompiledFallsBackToEvaller(): void
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $compiler = $this->createMock(Compiler::class);
        $executor = $this->createMock(Executor::class);
        $evaller = $this->createMock(Evaller::class);
        $env = new Env('env');

        $tokenizer->expects($this->once())
            ->method('tokenize')
            ->with('input')
            ->willReturn(['token']);

        $reader->expects($this->once())
            ->method('read')
            ->with(['token'])
            ->willReturn('ast');

        $compiler->expects($this->once())
            ->method('compile')
            ->with('ast', $env)
            ->willReturn(null);

        $executor->expects($this->never())
            ->method('execute');

        $evaller->expects($this->once())
            ->method('eval')
            ->with('ast', $env)
            ->willReturn('legacy result');

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, new Printer(), $env);

        $this->assertSame('legacy result', $lisp->readEvalCompiled('input'));
    }

    public function testReadEvalCustomEnv()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $compiler = $this->createMock(Compiler::class);
        $executor = $this->createMock(Executor::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $defaultEnv = new Env('default');
        $customEnv = new Env('custom');

        $tokens = ['token'];
        $ast = 'ast';

        $tokenizer->expects($this->once())
            ->method('tokenize')
            ->with('input')
            ->willReturn($tokens);

        $reader->expects($this->once())
            ->method('read')
            ->with($tokens)
            ->willReturn($ast);

        $evaller->expects($this->once())
            ->method('eval')
            ->with($ast, $customEnv)
            ->willReturn('result');

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, $printer, $defaultEnv);

        $this->assertSame('result', $lisp->readEval('input', $customEnv));
    }

    public function repProvider(): array
    {
        // This tests all main components together:
        // Tokenizer, Reader, Evaller, Printer

        return [
            ['[(- (+ 2 3) 4) (- (* 2 3) 4)]', false, '[1 2]'],

            ['"string"', false, 'string'],
            ['"string"', true, '"string"'],
        ];
    }

    /**
     * @dataProvider repProvider
     */
    public function testRep(string $input, bool $readable, string $expected)
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $compiler = new Compiler();
        $executor = new Executor();
        $printer = new Printer();
        $evaller = new Evaller($tokenizer, $reader, $printer, false);

        // Define some math functions for testing
        $env = new Env('env');
        $lib = new Math();
        $lib->register($env);

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, $printer, $env);

        ob_start();
        $lisp->rep($input, $readable);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame($expected, $result);
    }

    public function testSetDebug()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $compiler = $this->createMock(Compiler::class);
        $executor = $this->createMock(Executor::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $evaller->expects($this->once())
            ->method('setDebug')
            ->with($this->equalTo(true));

        $lisp = new Lisp($tokenizer, $reader, $compiler, $executor, $evaller, $printer, new Env('env'));

        $lisp->setDebug(true);
    }

    public function testSetEnv()
    {
        $oldEnv = new Env('old');
        $newEnv = new Env('new');

        $lisp = new Lisp(
            $this->createMock(Tokenizer::class),
            $this->createMock(Reader::class),
            $this->createMock(Compiler::class),
            $this->createMock(Executor::class),
            $this->createMock(Evaller::class),
            $this->createMock(Printer::class),
            $oldEnv
        );

        $lisp->setEnv($newEnv);

        $this->assertSame($newEnv, $lisp->getEnv());
    }

    public function testSetEnvValue()
    {
        $env = new Env('env');

        $lisp = new Lisp(
            $this->createMock(Tokenizer::class),
            $this->createMock(Reader::class),
            $this->createMock(Compiler::class),
            $this->createMock(Executor::class),
            $this->createMock(Evaller::class),
            $this->createMock(Printer::class),
            $env
        );

        $lisp->setEnvValue('answer', 42);

        $this->assertSame(42, $env->get('answer'));
    }
}
