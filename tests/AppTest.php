<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\App;
use Dynart\Micro\AppException;

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
 * @covers \Dynart\Micro\App
 */
final class AppTest extends TestCase
{
    public function testRunSetsInstance() {
        $app = new TestApp([]);
        App::run($app);
        $this->assertEquals($app, App::instance());
    }

    public function testRunCallTwiceThrowsAppException() {
        $this->expectException(AppException::class);
        $app = new TestApp([]);
        App::run($app);
        App::run($app);
    }

    public function testAddStoresTheInterfaceAndClass() {
        $app = new TestApp([]);
        $app->add(TestInterface::class, TestClass1::class);
        $this->assertTrue($app->hasInterface(TestInterface::class));
    }

    public function testAddThrowsAppExceptionWhenClassDoesNotImplementInterface() {
        $this->expectException(AppException::class);
        $app = new TestApp([]);
        $app->add(TestInterface::class, TestClass2::class);
    }

    public function testGetClassThrowsAppExceptionWhenNoInterfaceWasAdded() {
        $this->expectException(AppException::class);
        $app = new TestApp([]);
        $app->getClass(TestInterface::class);
    }

    public function testGetCreatesAnInstanceWithDependencies() {
        $app = new TestApp([]);
        $app->add(TestInterface::class, TestClass1::class);
        $app->add(TestClass2::class);
        $app->add(TestClassWithDependencies::class);
        $testWithDeps = $app->get(TestClassWithDependencies::class, ['param1']);
        $this->assertEquals('param1', $testWithDeps->param1());
    }

    public function testGetReturnsAlwaysWithTheSameInstance() {
        $app = new TestApp([]);
        $app->add(TestInterface::class, TestClass1::class);
        $this->assertSame($app->get(TestInterface::class), $app->get(TestInterface::class));
    }

    public function testCreateThrowsAppExceptionWhenClassDoesNotExist() {
        $this->expectException(AppException::class);
        $app = new TestApp([]);
        $app->create('\SomethingThatDoesNotExists');
    }

    public function testCreateCallsPostConstruct() {
        $app = new TestApp([]);
        $test2 = $app->create(TestClass2::class);
        $this->assertTrue($test2->lazyParam());
    }

    public function testInterfacesReturnsWithTheAddedInterfaces() {
        $app = new TestApp([]);
        $app->add(TestClass1::class);
        $this->assertContains(TestClass1::class, $app->interfaces());
    }

    public function testCircularDependency() {
        $this->expectException(AppException::class);
        $app = new TestApp([]);
        $app->add(TestDependency3::class);
        $app->add(TestDependency2::class);
        $app->add(TestDependency1::class);
        $app->get(TestDependency1::class);
    }

    public function testNonExistingDependency() {
        $this->expectException(AppException::class);
        $app = new TestApp([]);
        $app->add(TestDependency1::class);
        $app->get(TestDependency1::class);
    }
}