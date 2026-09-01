<?php
/**
 * MadLisp language
 * @link http://madlisp.com/
 * @copyright Copyright (c) 2026 Pekka Laiho
 */

use PHPUnit\Framework\TestCase;

use MadLisp\CoreFunc;
use MadLisp\Env;
use MadLisp\Lisp;
use MadLisp\LispFactory;
use MadLisp\Options;
use MadLisp\UserFunc;
use MadLisp\Vector;

class LispFactoryTest extends TestCase
{
    public function testMake()
    {
        $factory = new LispFactory();

        $lisp = $factory->make(new Options());

        $this->assertInstanceOf(Lisp::class, $lisp);
    }

    public function testEnv()
    {
        $lisp = (new LispFactory())->make(new Options());
        $userEnv = $lisp->getEnv();

        $this->assertInstanceOf(Env::class, $userEnv);
        $this->assertSame('root/user', $userEnv->getFullName());
        $this->assertSame('root', $userEnv->getParent()->getFullName());

        $this->assertTrue($userEnv->get('list') instanceof CoreFunc);
        $this->assertTrue($userEnv->get('map') instanceof CoreFunc);
        $this->assertTrue($userEnv->get('+') instanceof CoreFunc);
        $this->assertTrue($userEnv->get('prints') instanceof CoreFunc);
        $this->assertTrue($userEnv->get('defn') instanceof UserFunc);
    }

    public function testUserDefInUserEnv()
    {
        $lisp = (new LispFactory())->make(new Options());
        $userEnv = $lisp->getEnv();
        $rootEnv = $userEnv->getParent();

        $lisp->readEval('(def user-value 42)');

        $this->assertSame(42, $userEnv->get('user-value'));
        $this->assertFalse($rootEnv->has('user-value'));
    }

    public function testDefn()
    {
        $lisp = (new LispFactory())->make(new Options());

        $lisp->readEval('(defn add (a b) (+ a b))');

        $this->assertSame(7, $lisp->readEval('(add 3 4)'));
    }

    public function testBuiltInMacros()
    {
        $lisp = (new LispFactory())->make(new Options());

        $this->assertSame(10, $lisp->readEval('(when true 10)'));
        $this->assertNull($lisp->readEval('(when false 10)'));
        $this->assertSame(10, $lisp->readEval('(unless false 10)'));
        $this->assertNull($lisp->readEval('(unless true 10)'));
    }

    public function testBasicEvaluation()
    {
        $lisp = (new LispFactory())->make(new Options());

        $result = $lisp->readEval('(map (fn (x) (* x 2)) [1 2 3])');

        $this->assertInstanceOf(Vector::class, $result);
        $this->assertSame([2, 4, 6], $result->getData());
    }

    public function testRep()
    {
        $lisp = (new LispFactory())->make(new Options());

        ob_start();
        $lisp->rep('(list (+ 1 2) [4 5])', true);
        $result = ob_get_clean();

        $this->assertSame('(3 [4 5])', $result);
    }

    public function testSafeMode()
    {
        $normal = (new LispFactory())->make(new Options());
        $safeOptions = new Options();
        $safeOptions->safemode = true;
        $safe = (new LispFactory())->make($safeOptions);

        $normalRoot = $normal->getEnv()->getParent();
        $safeRoot = $safe->getEnv()->getParent();

        $this->assertTrue($normalRoot->has('print'));
        $this->assertTrue($normalRoot->has('exit'));
        $this->assertTrue($normalRoot->has('__FILE__'));

        // Safe mode has prints and +
        $this->assertTrue($safe->getEnv()->get('prints') instanceof CoreFunc);
        $this->assertTrue($safe->getEnv()->get('+') instanceof CoreFunc);

        // ... but not print, exit or __FILE__
        $this->assertFalse($safeRoot->has('print'));
        $this->assertFalse($safeRoot->has('exit'));
        $this->assertFalse($safeRoot->has('__FILE__'));
    }
}
