<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\PhpCompiler;
use MadLisp\Options;
use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Lisp;
use MadLisp\MadLispException;
use MadLisp\MacroExpander;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Tokenizer;
use MadLisp\Lib\Math;
use MadLisp\PhpCompiledProgram;

class LispTest extends TestCase
{
    public function testGetEnv()
    {
        $env = new Env('env');

        $lisp = new Lisp(
            $this->createStub(Tokenizer::class),
            $this->createStub(Reader::class),
            $this->createStub(MacroExpander::class),
            $this->createStub(PhpCompiler::class),
            $this->createStub(Evaller::class),
            $this->createStub(Printer::class),
            $env
        );

        $this->assertSame($env, $lisp->getEnv());
    }

    public function testCompile(): void
    {
        $macroExpander = $this->createStub(MacroExpander::class);
        $macroExpander->method('expand')->willReturnArgument(0);
        $compiler = $this->createMock(PhpCompiler::class);
        $program = new \MadLisp\PhpCompiledProgram(static fn (Env $env) => 'result', '');
        $ast = 'ast';

        $compiler->expects($this->once())
            ->method('compile')
            ->with($ast)
            ->willReturn($program);

        $lisp = new Lisp(
            $this->createStub(Tokenizer::class),
            $this->createStub(Reader::class),
            $macroExpander,
            $compiler,
            $this->createStub(Evaller::class),
            $this->createStub(Printer::class),
            new Env('env')
        );

        $this->assertSame($program, $lisp->compile($ast));
    }

    public function testExecute(): void
    {
        $program = new \MadLisp\PhpCompiledProgram(static fn (Env $env) => 'result', '');
        $env = new Env('env');

        $lisp = new Lisp(
            $this->createStub(Tokenizer::class),
            $this->createStub(Reader::class),
            $this->createStub(MacroExpander::class),
            $this->createStub(PhpCompiler::class),
            $this->createStub(Evaller::class),
            $this->createStub(Printer::class),
            new Env('default')
        );

        $this->assertSame('result', $lisp->execute($program, $env));
    }

    public function testPrint()
    {
        $tokenizer = $this->createStub(Tokenizer::class);
        $reader = $this->createStub(Reader::class);
        $macroExpander = $this->createStub(MacroExpander::class);
        $compiler = $this->createStub(PhpCompiler::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createStub(Evaller::class);

        $printer->expects($this->once())
            ->method('print')
            ->with($this->equalTo("abc"), $this->equalTo(false));

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, $printer, new Env('env'));

        $lisp->print('abc', false);
    }

    public function testPstr()
    {
        $tokenizer = $this->createStub(Tokenizer::class);
        $reader = $this->createStub(Reader::class);
        $macroExpander = $this->createStub(MacroExpander::class);
        $compiler = $this->createStub(PhpCompiler::class);
        $printer = $this->createMock(Printer::class);
        $evaller = $this->createStub(Evaller::class);

        $printer->expects($this->once())
            ->method('pstr')
            ->with($this->equalTo('abc'), $this->equalTo(true))
            ->willReturn('"abc"');

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, $printer, new Env('env'));

        $this->assertSame('"abc"', $lisp->pstr('abc', true));
    }

    public function testReadEval()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $macroExpander = $this->createStub(MacroExpander::class);
        $compiler = $this->createStub(PhpCompiler::class);
        $printer = $this->createStub(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $tokenizer->expects($this->once())
            ->method('tokenize');

        $reader->expects($this->once())
            ->method('read');

        $evaller->expects($this->once())
            ->method('eval');

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, $printer, new Env('env'));

        $lisp->readEval('abc');
    }

    public function testReadEvalCompiledExecutesCompiledProgram(): void
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $macroExpander = $this->createStub(MacroExpander::class);
        $macroExpander->method('expand')->willReturnArgument(0);
        $compiler = $this->createMock(PhpCompiler::class);
        $evaller = $this->createMock(Evaller::class);
        $env = new Env('env');
        $program = new \MadLisp\PhpCompiledProgram(static fn (Env $env) => 'result', '');

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
            ->with('ast')
            ->willReturn($program);

        $evaller->expects($this->never())
            ->method('eval');

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, new Printer(), $env);

        $this->assertSame('result', $lisp->readEvalCompiled('input'));
    }

    public function testReadEvalCompiledPropagatesCompilationFailure(): void
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $macroExpander = $this->createStub(MacroExpander::class);
        $macroExpander->method('expand')->willReturnArgument(0);
        $compiler = $this->createMock(PhpCompiler::class);
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
            ->with('ast')
            ->willThrowException(new MadLispException('expression is not supported by compiler'));

        $evaller->expects($this->never())
            ->method('eval');

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, new Printer(), $env);

        $this->expectException(MadLispException::class);
        $this->expectExceptionMessage('expression is not supported by compiler');
        $lisp->readEvalCompiled('input');
    }

    public function testReadEvalCustomEnv()
    {
        $tokenizer = $this->createMock(Tokenizer::class);
        $reader = $this->createMock(Reader::class);
        $macroExpander = $this->createStub(MacroExpander::class);
        $compiler = $this->createStub(PhpCompiler::class);
        $printer = $this->createStub(Printer::class);
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

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, $printer, $defaultEnv);

        $this->assertSame('result', $lisp->readEval('input', $customEnv));
    }

    public static function repProvider(): array
    {
        // This tests all main components together:
        // Tokenizer, Reader, Evaller, Printer

        return [
            ['[(- (+ 2 3) 4) (- (* 2 3) 4)]', false, '[1 2]'],

            ['"string"', false, 'string'],
            ['"string"', true, '"string"'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('repProvider')]
    public function testRep(string $input, bool $readable, string $expected)
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $macroExpander = new MacroExpander();
        $compiler = new PhpCompiler(new Options());
        $printer = new Printer();
        $evaller = new Evaller($tokenizer, $reader, $printer, new Options());

        // Define some math functions for testing
        $env = new Env('env');
        $lib = new Math();
        $lib->register($env);

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, $printer, $env);

        ob_start();
        $lisp->rep($input, $readable);
        $result = ob_get_contents();
        ob_end_clean();

        $this->assertSame($expected, $result);
    }

    public function testSetDebug()
    {
        $tokenizer = $this->createStub(Tokenizer::class);
        $reader = $this->createStub(Reader::class);
        $macroExpander = $this->createStub(MacroExpander::class);
        $compiler = $this->createStub(PhpCompiler::class);
        $printer = $this->createStub(Printer::class);
        $evaller = $this->createMock(Evaller::class);

        $evaller->expects($this->once())
            ->method('setDebug')
            ->with($this->equalTo(true));

        $lisp = new Lisp($tokenizer, $reader, $macroExpander, $compiler, $evaller, $printer, new Env('env'));

        $lisp->setDebug(true);
    }

    public function testSetEnv()
    {
        $oldEnv = new Env('old');
        $newEnv = new Env('new');

        $lisp = new Lisp(
            $this->createStub(Tokenizer::class),
            $this->createStub(Reader::class),
            $this->createStub(MacroExpander::class),
            $this->createStub(PhpCompiler::class),
            $this->createStub(Evaller::class),
            $this->createStub(Printer::class),
            $oldEnv
        );

        $lisp->setEnv($newEnv);

        $this->assertSame($newEnv, $lisp->getEnv());
    }

    public function testSetEnvValue()
    {
        $env = new Env('env');

        $lisp = new Lisp(
            $this->createStub(Tokenizer::class),
            $this->createStub(Reader::class),
            $this->createStub(MacroExpander::class),
            $this->createStub(PhpCompiler::class),
            $this->createStub(Evaller::class),
            $this->createStub(Printer::class),
            $env
        );

        $lisp->setEnvValue('answer', 42);

        $this->assertSame(42, $env->get('answer'));
    }
}
