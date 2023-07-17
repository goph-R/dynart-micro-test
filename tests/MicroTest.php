<?php

require_once 'ResettableMicro.php';

use PHPUnit\Framework\TestCase;

use Dynart\Micro\Micro;
use Dynart\Micro\App;
use Dynart\Micro\MicroException;

class TestApp extends App {
    public function init() {}
    public function process() {}
}

interface TestInterface {
    function someMethod();
}

class TestClass1 implements TestInterface {
    function someMethod() {}
}

class TestClass2 {
    private $lazyParam = false;
    public function postConstruct() {
        $this->lazyParam = true;
    }
    public function lazyParam() {
        return $this->lazyParam;
    }
}

class TestClassWithDependencies {
    private $param1;
    public function __construct(TestInterface $test1, TestClass2 $test2, $param1) {
        $this->param1 = $param1;
    }
    public function param1() {
        return $this->param1;
    }
}

class TestDependency1 {
    public function __construct(TestDependency2 $s) {
    }
}

class TestDependency2 {
    public function __construct(TestDependency3 $s) {
    }
}

class TestDependency3 {
    public function __construct(TestDependency1 $s) {
    }
}

/**
 * @covers \Dynart\Micro\Micro
 */
final class MicroTest extends TestCase
{

    protected function setUp(): void {
        ResettableMicro::reset();
    }

    public function testRunSetsInstance() {
        $app = new TestApp([]);
        Micro::run($app);
        $this->assertEquals($app, Micro::instance());
    }

    public function testRunCallTwiceThrowsMicroException() {
        $this->expectException(MicroException::class);
        $app = new TestApp([]);
        Micro::run($app);
        Micro::run($app);
    }

    public function testAddStoresTheInterfaceAndClass() {
        Micro::add(TestInterface::class, TestClass1::class);
        $this->assertTrue(Micro::hasInterface(TestInterface::class));
    }

    public function testAddThrowsMicroExceptionWhenClassDoesNotImplementInterface() {
        $this->expectException(MicroException::class);
        Micro::add(TestInterface::class, TestClass2::class);
    }

    public function testGetClassThrowsMicroExceptionWhenNoInterfaceWasAdded() {
        $this->expectException(MicroException::class);
        Micro::getClass(TestInterface::class);
    }

    public function testGetCreatesAnInstanceWithDependencies() {
        Micro::add(TestInterface::class, TestClass1::class);
        Micro::add(TestClass2::class);
        Micro::add(TestClassWithDependencies::class);
        $testWithDeps = Micro::get(TestClassWithDependencies::class, ['param1']);
        $this->assertEquals('param1', $testWithDeps->param1());
    }

    public function testGetReturnsAlwaysWithTheSameInstance() {
        Micro::add(TestInterface::class, TestClass1::class);
        $this->assertSame(Micro::get(TestInterface::class), Micro::get(TestInterface::class));
    }

    public function testCreateThrowsMicroExceptionWhenClassDoesNotExist() {
        $this->expectException(MicroException::class);
        Micro::create('\SomethingThatDoesNotExists');
    }

    public function testCreateCallsPostConstruct() {
        $test2 = Micro::create(TestClass2::class);
        $this->assertTrue($test2->lazyParam());
    }

    public function testInterfacesReturnsWithTheAddedInterfaces() {
        Micro::add(TestClass1::class);
        $this->assertContains(TestClass1::class, Micro::interfaces());
    }

    public function testCircularDependency() {
        $this->expectException(MicroException::class);
        Micro::add(TestDependency3::class);
        Micro::add(TestDependency2::class);
        Micro::add(TestDependency1::class);
        Micro::get(TestDependency1::class);
    }

    public function testNonExistingDependency() {
        $this->expectException(MicroException::class);
        Micro::add(TestDependency1::class);
        Micro::get(TestDependency1::class);
    }
}