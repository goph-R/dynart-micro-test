<?php

require_once dirname(__FILE__, 2) .'/src/ResettableMicro.php';

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Micro;
use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\Attribute\Route;
use Dynart\Micro\AttributeHandler\RouteAttributeHandler;
use Dynart\Micro\Middleware\AttributeProcessor;
use Dynart\Micro\MicroException;
use Dynart\Micro\Config;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Request;
use Dynart\Micro\RequestInterface;
use Dynart\Micro\Router;
use Dynart\Micro\RouterInterface;
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

class TestClassAttributeHandler implements AttributeHandlerInterface {
    public array $handled = [];

    public function attributeClass(): string { return TestClassAttribute::class; }
    public function targets(): array { return [AttributeHandlerInterface::TARGET_CLASS]; }
    public function handle(string $className, mixed $subject, object $attribute): void {
        $this->handled[] = ['class' => $className, 'label' => $attribute->label];
    }
}

class TestPropertyAttributeHandler implements AttributeHandlerInterface {
    public array $handled = [];

    public function attributeClass(): string { return TestPropertyAttribute::class; }
    public function targets(): array { return [AttributeHandlerInterface::TARGET_PROPERTY]; }
    public function handle(string $className, mixed $subject, object $attribute): void {
        $this->handled[] = ['class' => $className, 'property' => $subject->getName(), 'name' => $attribute->name];
    }
}

class NotAnAttributeHandler {
    public function run() {}
}

class TestableAttributeProcessor extends AttributeProcessor {
    public function getNamespaces(): array { return $this->namespaces; }
    public function callLoadNamespacesFromConfig(): void { $this->loadNamespacesFromConfig(); }
    public function callDiscoverClassesFromNamespaces(): void { $this->discoverClassesFromNamespaces(); }
    public function callScanDirectory(string $dir, string $prefix, string $subPath = ''): array {
        return $this->scanDirectory($dir, $prefix, $subPath);
    }
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
        Micro::add(ConfigInterface::class, Config::class);
        Micro::add(RequestInterface::class, Request::class);
        Micro::add(RouterInterface::class, Router::class);
        return Micro::get(RouterInterface::class);
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

        $router = Micro::get(RouterInterface::class);
        $this->assertEmpty($router->routes());
    }

    // --- loadNamespacesFromConfig ---

    public function testLoadNamespacesFromConfigWithNullConfigDoesNothing(): void {
        $processor = new TestableAttributeProcessor();
        $processor->callLoadNamespacesFromConfig();
        $this->assertEmpty($processor->getNamespaces());
    }

    public function testLoadNamespacesFromConfigWithEmptyValueDoesNothing(): void {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->with('app.scan_namespaces', '')->willReturn('');
        $processor = new TestableAttributeProcessor($config);
        $processor->callLoadNamespacesFromConfig();
        $this->assertEmpty($processor->getNamespaces());
    }

    public function testLoadNamespacesFromConfigLoadsSingleNamespace(): void {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->with('app.scan_namespaces', '')->willReturn('App');
        $processor = new TestableAttributeProcessor($config);
        $processor->callLoadNamespacesFromConfig();
        $this->assertEquals(['App'], $processor->getNamespaces());
    }

    public function testLoadNamespacesFromConfigLoadsMultipleNamespaces(): void {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->with('app.scan_namespaces', '')->willReturn('App,Services');
        $processor = new TestableAttributeProcessor($config);
        $processor->callLoadNamespacesFromConfig();
        $this->assertEquals(['App', 'Services'], $processor->getNamespaces());
    }

    public function testLoadNamespacesFromConfigTrimsWhitespace(): void {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->with('app.scan_namespaces', '')->willReturn(' App , Services ');
        $processor = new TestableAttributeProcessor($config);
        $processor->callLoadNamespacesFromConfig();
        $this->assertEquals(['App', 'Services'], $processor->getNamespaces());
    }

    public function testLoadNamespacesFromConfigDeduplicatesWithProgrammaticNamespace(): void {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->with('app.scan_namespaces', '')->willReturn('App,Services');
        $processor = new TestableAttributeProcessor($config);
        $processor->addNamespace('App');
        $processor->callLoadNamespacesFromConfig();
        $this->assertEquals(['App', 'Services'], $processor->getNamespaces());
    }

    // --- discoverClassesFromNamespaces ---

    private function makeRealConfig(): ConfigInterface {
        $rootPath = str_replace('\\', '/', dirname(__FILE__, 2));
        $config = $this->createMock(ConfigInterface::class);
        $config->method('rootPath')->willReturn($rootPath);
        $config->method('get')->willReturn('');
        return $config;
    }

    private function frameworkSrcPath(): string {
        return str_replace('\\', '/', dirname(__FILE__, 2)) . '/vendor/dynart/micro/src';
    }

    public function testDiscoverClassesFromNamespacesWithEmptyNamespacesDoesNothing(): void {
        $processor = new TestableAttributeProcessor($this->makeRealConfig());
        $processor->callDiscoverClassesFromNamespaces();
        $this->assertEmpty(Micro::interfaces());
    }

    public function testDiscoverClassesFromNamespacesWithNullConfigDoesNothing(): void {
        $processor = new TestableAttributeProcessor();
        $processor->addNamespace('Dynart\\Micro\\Middleware');
        $processor->callDiscoverClassesFromNamespaces();
        $this->assertEmpty(Micro::interfaces());
    }

    public function testDiscoverClassesFromNamespacesWithInvalidRootPathDoesNothing(): void {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('rootPath')->willReturn('/nonexistent/path');
        $processor = new TestableAttributeProcessor($config);
        $processor->addNamespace('Dynart\\Micro\\Middleware');
        $processor->callDiscoverClassesFromNamespaces();
        $this->assertEmpty(Micro::interfaces());
    }

    public function testDiscoverClassesFromNamespacesRegistersConcreteClasses(): void {
        // Use sub-namespace to avoid matching Dynart\Micro\Entities\ PSR-4 entry
        $processor = new TestableAttributeProcessor($this->makeRealConfig());
        $processor->addNamespace('Dynart\\Micro\\Middleware');
        $processor->callDiscoverClassesFromNamespaces();
        $this->assertTrue(Micro::hasInterface(\Dynart\Micro\Middleware\AttributeProcessor::class));
        $this->assertTrue(Micro::hasInterface(\Dynart\Micro\Middleware\JwtValidator::class));
        $this->assertTrue(Micro::hasInterface(\Dynart\Micro\Middleware\LocaleResolver::class));
    }

    public function testDiscoverClassesFromNamespacesSkipsAlreadyRegisteredClasses(): void {
        Micro::add(\Dynart\Micro\Middleware\JwtValidator::class);
        $countBefore = count(Micro::interfaces());

        $processor = new TestableAttributeProcessor($this->makeRealConfig());
        $processor->addNamespace('Dynart\\Micro\\Middleware');
        $processor->callDiscoverClassesFromNamespaces();

        $countAfter = count(Micro::interfaces());
        // Other middleware classes are added, but JwtValidator is skipped (already registered)
        $this->assertGreaterThan($countBefore, $countAfter);
        $this->assertTrue(Micro::hasInterface(\Dynart\Micro\Middleware\JwtValidator::class));
    }

    // --- scanDirectory (skipping non-concrete types) ---

    public function testScanDirectoryIncludesConcreteClasses(): void {
        $processor = new TestableAttributeProcessor();
        $classes = $processor->callScanDirectory($this->frameworkSrcPath(), 'Dynart\\Micro\\');
        $this->assertContains(\Dynart\Micro\JwtUser::class, $classes);
        $this->assertContains(\Dynart\Micro\Config::class, $classes);
        $this->assertContains(\Dynart\Micro\Router::class, $classes);
    }

    public function testScanDirectorySkipsInterfaces(): void {
        $processor = new TestableAttributeProcessor();
        $classes = $processor->callScanDirectory($this->frameworkSrcPath(), 'Dynart\\Micro\\');
        $this->assertNotContains(\Dynart\Micro\JwtAuthInterface::class, $classes);
        $this->assertNotContains(\Dynart\Micro\ConfigInterface::class, $classes);
    }

    public function testScanDirectorySkipsAbstractClasses(): void {
        $processor = new TestableAttributeProcessor();
        $classes = $processor->callScanDirectory($this->frameworkSrcPath(), 'Dynart\\Micro\\');
        $this->assertNotContains(\Dynart\Micro\AbstractApp::class, $classes);
        $this->assertNotContains(\Dynart\Micro\AbstractValidator::class, $classes);
    }
}
