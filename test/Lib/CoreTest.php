<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\PhpCompiler;
use MadLisp\PhpCompiledProgram;
use MadLisp\Env;
use MadLisp\Evaller;
use MadLisp\Func;
use MadLisp\MadLispUserException;
use MadLisp\MList;
use MadLisp\Printer;
use MadLisp\Reader;
use MadLisp\Symbol;
use MadLisp\Tokenizer;
use MadLisp\Vector;
use MadLisp\Lib\Core;

class CoreTest extends TestCase
{
    public function testConstants()
    {
        [$env] = $this->getEnv();

        $this->assertNull($env->get('__FILE__'));
        $this->assertNull($env->get('__DIR__'));
    }

    public function testCompile()
    {
        [$env] = $this->getEnv();
        $ast = new MList([new Symbol('+'), 1, 2]);

        $program = $env->get('compile')->call([$ast]);

        $this->assertInstanceOf(PhpCompiledProgram::class, $program);
        $this->assertSame(3, $program->execute($env));
    }

    public function testDebug()
    {
        [$env, $evaller] = $this->getEnv();

        $this->assertFalse($evaller->getDebug());
        $this->assertTrue($env->get('debug')->call([]));
        $this->assertTrue($evaller->getDebug());
    }

    public function testDoc()
    {
        [$env] = $this->getEnv();
        $doc = $env->get('doc');

        $result = $doc->call([$doc]);
        $this->assertSame('Get or set documentation string for a function.', $result);

        $doc->call([$doc, 'New docstring']);
        $result = $doc->call([$doc]);
        $this->assertSame('New docstring', $result);
    }

    public function testPhpSapi()
    {
        [$env] = $this->getEnv();
        $result = $env->get('php-sapi')->call([]);
        $this->assertSame(php_sapi_name(), $result);
    }

    public function testPhpVersion()
    {
        [$env] = $this->getEnv();
        $result = $env->get('php-version')->call([]);
        $this->assertSame(phpversion(), $result);
    }

    public function testPrint()
    {
        [$env] = $this->getEnv();
        $print = $env->get('print');

        [$result, $output] = $this->callWithOutput($print, []);
        $this->assertNull($result);
        $this->assertSame('', $output);

        [$result, $output] = $this->callWithOutput($print, ['hello', 42, true, null]);
        $this->assertNull($result);
        $this->assertSame('hello42truenull', $output);

        [$result, $output] = $this->callWithOutput($print, [
            new MList([new Symbol('foo'), new Vector([1, 'bar'])])
        ]);
        $this->assertNull($result);
        $this->assertSame('(foo [1 bar])', $output);
    }

    public function testPrintr()
    {
        [$env] = $this->getEnv();
        $printr = $env->get('printr');

        [$result, $output] = $this->callWithOutput($printr, ['hello']);
        $this->assertNull($result);
        $this->assertSame('"hello"', $output);

        [$result, $output] = $this->callWithOutput($printr, [42]);
        $this->assertNull($result);
        $this->assertSame('42', $output);

        [$result, $output] = $this->callWithOutput($printr, [
            new Vector([true, 'line\ntext', null])
        ]);
        $this->assertNull($result);
        $this->assertSame('[true "line\\\\ntext" null]', $output);
    }

    public function testPrints()
    {
        [$env] = $this->getEnv();
        $prints = $env->get('prints');

        $this->assertSame('"hello"', $prints->call(['hello']));
        $this->assertSame('42', $prints->call([42]));
        $this->assertSame('true', $prints->call([true]));
        $this->assertSame('null', $prints->call([null]));
        $this->assertSame('(foo [1 "bar"])', $prints->call([
            new MList([new Symbol('foo'), new Vector([1, 'bar'])])
        ]));
    }

    public function testRead()
    {
        [$env] = $this->getEnv();
        $read = $env->get('read');

        $this->assertTrue($read->call(['true']));
        $this->assertSame(42, $read->call(['42']));
        $this->assertSame(3.14, $read->call(['3.14']));
        $this->assertSame('hello', $read->call(['"hello"']));
        $this->assertNull($read->call(['null']));

        $symbol = $read->call(['name']);
        $this->assertInstanceOf(Symbol::class, $symbol);
        $this->assertSame('name', $symbol->getName());

        $list = $read->call(['(foo 42)']);
        $this->assertInstanceOf(MList::class, $list);
        $this->assertSame('foo', $list->get(0)->getName());
        $this->assertSame(42, $list->get(1));

        $vector = $read->call(['[true "bar"]']);
        $this->assertInstanceOf(Vector::class, $vector);
        $this->assertTrue($vector->get(0));
        $this->assertSame('bar', $vector->get(1));
    }

    public function testSleep()
    {
        [$env] = $this->getEnv();

        $start = hrtime(true);
        $result = $env->get('sleep')->call([50]);
        $elapsed = (hrtime(true) - $start) / 1_000_000;

        $this->assertNull($result);
        $this->assertGreaterThanOrEqual(50, $elapsed);
        $this->assertLessThan(55, $elapsed);
    }

    public function testSystem()
    {
        [$env] = $this->getEnv();

        $command = sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('echo "system-test";')
        );

        $result = $env->get('system')->call([$command]);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame(0, $result->get(0));
        $this->assertSame('system-test', $result->get(1));
    }

    public function testSystemFailure()
    {
        [$env] = $this->getEnv();

        $command = sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('echo "failed"; exit(7);')
        );

        $result = $env->get('system')->call([$command]);

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame(7, $result->get(0));
        $this->assertSame('failed', $result->get(1));
    }

    public function testThrow()
    {
        [$env] = $this->getEnv();

        $this->expectException(MadLispUserException::class);
        $this->expectExceptionMessage('Test error message');

        $env->get('throw')->call(['Test error message']);
    }

    public function testThrowCustomValue()
    {
       [$env] = $this->getEnv();

       $value = new MList(['error' => 42, 'fatal' => true]);

       try {
           $env->get('throw')->call([$value]);
           $this->fail('Expected MadLispUserException to be thrown.');
       } catch (MadLispUserException $exception) {
           $this->assertSame($value, $exception->getValue());
       }
    }

    public function testSafemodeHidesFunctions()
    {
        [$env] = $this->getEnv(true);

        $this->assertFalse($env->has('__FILE__'));
        $this->assertFalse($env->has('__DIR__'));
        $this->assertFalse($env->has('debug'));
        $this->assertFalse($env->has('exit'));
        $this->assertFalse($env->has('print'));
        $this->assertFalse($env->has('printr'));
        $this->assertFalse($env->has('sleep'));
        $this->assertFalse($env->has('system'));
    }

    private function callWithOutput(Func $function, array $args): array
    {
        ob_start();
        $result = $function->call($args);
        $output = ob_get_contents();
        ob_end_clean();

        return [$result, $output];
    }

    private function getEnv(bool $safemode = false): array
    {
        $tokenizer = new Tokenizer();
        $reader = new Reader();
        $compiler = new PhpCompiler();
        $printer = new Printer();
        $evaller = new Evaller($tokenizer, $reader, $printer, $safemode);

        $core = new Core(
            $tokenizer,
            $reader,
            $compiler,
            $printer,
            $evaller,
            $safemode
        );

        $env = new Env('test');
        $core->register($env);

        return [$env, $evaller];
    }
}
