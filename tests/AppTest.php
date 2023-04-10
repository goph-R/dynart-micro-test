<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\App;

class AppTestBase extends App {
    public function init() {}
    public function process() {}
}

/**
 * @covers \Dynart\Micro\App
 */
final class AppTest extends TestCase
{
    public function testSomething() {
        $app = new AppTestBase();
        $app->add(AppTest::class);
        $instanceOfThisTest = $app->get(AppTest::class);
        $this->assertInstanceOf(App::class, $app);
        $this->assertInstanceOf(AppTest::class, $instanceOfThisTest);
    }
}