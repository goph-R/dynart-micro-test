<?php

require_once dirname(__FILE__, 2) .'/src/ResettableMicro.php';

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Micro;
use Dynart\Micro\AttributeHandler;
use Dynart\Micro\Attribute\Route;
use Dynart\Micro\AttributeHandler\RouteAttributeHandler;
use Dynart\Micro\Middleware\AttributeProcessor;
use Dynart\Micro\MicroException;
use Dynart\Micro\Config;
use Dynart\Micro\Request;
use Dynart\Micro\Router;
use Dynart\Micro\Test\ResettableMicro;

#[\Attribute(\Attribute::TARGET_CLASS)]
class TestClassAttribute {
    public function __construct(public string $label) {}
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class TestPropertyAttribute {
    public function __construct(public string $name) {}
}

#[TestClassAttribute('my-controller')]
class AttributeTestController {

    #[TestPropertyAttribute('tagged')]
    public string $field = '';

    #[Route('GET', '/test')]
    public function index() {}

    #[Route('POST', '/test/save')]
    public function save() {}

    public function noAttribute() {}
}

class AttributeTestControllerOther {
    #[Route('GET', '/other')]
    public function index() {}
}

class TestClassAttributeHandler implements AttributeHandler {
    public array $handled = [];

    public function attributeClass(): string { return TestClassAttribute::class; }
    public function targets(): array { return [AttributeHandler::TARGET_CLASS]; }
    public function handle(string $className, mixed $subject, object $attribute): void {
        $this->handled[] = ['class' => $className, 'label' => $attribute->label];
    }
}

class TestPropertyAttributeHandler implements AttributeHandler {
    public array $handled = [];

    public function attributeClass(): string { return TestPropertyAttribute::class; }
    public function targets(): array { return [AttributeHandler::TARGET_PROPERTY]; }
    public function handle(string $className, mixed $subject, object $attribute): void {
        $this->handled[] = ['class' => $className, 'property' => $subject->getName(), 'name' => $attribute->name];
    }
}

class NotAnAttributeHandler {
    public function run() {}
}

/**
 * @covers \Dynart\Micro\Middleware\AttributeProcessor
 */
final class AttributeProcessorTest extends TestCase
{
    protected function setUp(): void {
        ResettableMicro::reset();
        $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    private function setUpRouter(): Router {
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $config->method('get')->willReturn('/');

        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->method('get')->willReturn('/');

        Micro::add(Config::class);
        Micro::add(Request::class);
        Micro::add(Router::class);
        return Micro::get(Router::class);
    }

    public function testAddThrowsExceptionForNonHandler(): void {
        $processor = new AttributeProcessor();
        $this->expectException(MicroException::class);
        $processor->add(NotAnAttributeHandler::class);
    }

    public function testRunProcessesMethodAttributes(): void {
        $router = $this->setUpRouter();
        Micro::add(RouteAttributeHandler::class);
        Micro::add(AttributeTestController::class);

        $processor = new AttributeProcessor();
        $processor->add(RouteAttributeHandler::class);
        $processor->run();

        $routes = $router->routes();
        $this->assertEquals([AttributeTestController::class, 'index'], $routes['GET']['/test']);
        $this->assertEquals([AttributeTestController::class, 'save'], $routes['POST']['/test/save']);
    }

    public function testRunProcessesClassAttributes(): void {
        Micro::add(TestClassAttributeHandler::class);
        Micro::add(AttributeTestController::class);

        $processor = new AttributeProcessor();
        $processor->add(TestClassAttributeHandler::class);
        $processor->run();

        $handler = Micro::get(TestClassAttributeHandler::class);
        $this->assertCount(1, $handler->handled);
        $this->assertEquals(AttributeTestController::class, $handler->handled[0]['class']);
        $this->assertEquals('my-controller', $handler->handled[0]['label']);
    }

    public function testRunProcessesPropertyAttributes(): void {
        Micro::add(TestPropertyAttributeHandler::class);
        Micro::add(AttributeTestController::class);

        $processor = new AttributeProcessor();
        $processor->add(TestPropertyAttributeHandler::class);
        $processor->run();

        $handler = Micro::get(TestPropertyAttributeHandler::class);
        $this->assertCount(1, $handler->handled);
        $this->assertEquals(AttributeTestController::class, $handler->handled[0]['class']);
        $this->assertEquals('field', $handler->handled[0]['property']);
        $this->assertEquals('tagged', $handler->handled[0]['name']);
    }

    public function testNamespaceFilterProcessesOnlyMatchingClasses(): void {
        $router = $this->setUpRouter();
        Micro::add(RouteAttributeHandler::class);
        Micro::add(AttributeTestController::class);
        Micro::add(AttributeTestControllerOther::class);

        $processor = new AttributeProcessor();
        $processor->add(RouteAttributeHandler::class);
        $processor->addNamespace('AttributeTestControllerOther');
        $processor->run();

        $routes = $router->routes();
        $this->assertArrayHasKey('/other', $routes['GET']);
        $this->assertArrayNotHasKey('/test', $routes['GET'] ?? []);
    }

    public function testMethodsWithoutAttributesAreIgnored(): void {
        $router = $this->setUpRouter();
        Micro::add(RouteAttributeHandler::class);
        Micro::add(AttributeTestController::class);

        $processor = new AttributeProcessor();
        $processor->add(RouteAttributeHandler::class);
        $processor->run();

        $routes = $router->routes();
        $getRoutes = array_keys($routes['GET']);
        $this->assertNotContains('/noAttribute', $getRoutes);
    }

    public function testRunWithNoHandlersDoesNothing(): void {
        Micro::add(AttributeTestController::class);
        $processor = new AttributeProcessor();
        $processor->run();
        // No exception means success
        $this->assertTrue(true);
    }

    public function testRunWithNoRegisteredClassesDoesNothing(): void {
        Micro::add(RouteAttributeHandler::class);
        $this->setUpRouter();

        $processor = new AttributeProcessor();
        $processor->add(RouteAttributeHandler::class);
        $processor->run();

        $router = Micro::get(Router::class);
        $this->assertEmpty($router->routes());
    }
}
