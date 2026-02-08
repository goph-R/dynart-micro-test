<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\AttributeHandler;
use Dynart\Micro\AttributeHandler\RouteAttributeHandler;
use Dynart\Micro\Attribute\Route;
use Dynart\Micro\Config;
use Dynart\Micro\Request;
use Dynart\Micro\Router;

/**
 * @covers \Dynart\Micro\AttributeHandler\RouteAttributeHandler
 */
final class RouteAttributeHandlerTest extends TestCase
{
    private Router $router;
    private RouteAttributeHandler $handler;

    protected function setUp(): void {
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $config->method('get')->willReturn('/');

        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->method('get')->willReturn('/');

        $this->router = new Router($config, $request);
        $this->handler = new RouteAttributeHandler($this->router);
    }

    public function testAttributeClassReturnsRouteClass() {
        $this->assertEquals(Route::class, $this->handler->attributeClass());
    }

    public function testTargetsReturnsMethodOnly() {
        $this->assertEquals([AttributeHandler::TARGET_METHOD], $this->handler->targets());
    }

    public function testHandleAddsRouteToRouter() {
        $attribute = new Route('GET', '/users');
        $refMethod = new ReflectionMethod(self::class, 'dummyAction');
        $this->handler->handle('TestController', $refMethod, $attribute);

        $routes = $this->router->routes();
        $this->assertArrayHasKey('GET', $routes);
        $this->assertArrayHasKey('/users', $routes['GET']);
        $this->assertEquals(['TestController', 'dummyAction'], $routes['GET']['/users']);
    }

    public function testHandleAddsPostRoute() {
        $attribute = new Route('POST', '/users/save');
        $refMethod = new ReflectionMethod(self::class, 'dummyAction');
        $this->handler->handle('TestController', $refMethod, $attribute);

        $routes = $this->router->routes();
        $this->assertArrayHasKey('POST', $routes);
        $this->assertEquals(['TestController', 'dummyAction'], $routes['POST']['/users/save']);
    }

    public function testHandleAddsBothRoute() {
        $attribute = new Route('BOTH', '/form');
        $refMethod = new ReflectionMethod(self::class, 'dummyAction');
        $this->handler->handle('TestController', $refMethod, $attribute);

        $routes = $this->router->routes();
        $this->assertEquals(['TestController', 'dummyAction'], $routes['GET']['/form']);
        $this->assertEquals(['TestController', 'dummyAction'], $routes['POST']['/form']);
    }

    public function testHandleWithWildcardRoute() {
        $attribute = new Route('GET', '/users/?/posts/?');
        $refMethod = new ReflectionMethod(self::class, 'dummyAction');
        $this->handler->handle('TestController', $refMethod, $attribute);

        $routes = $this->router->routes();
        $this->assertArrayHasKey('/users/?/posts/?', $routes['GET']);
    }

    public function dummyAction() {}
}
