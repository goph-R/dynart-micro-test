<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Config;
use Dynart\Micro\Router;
use Dynart\Micro\Request;

class PrefixVariableCallableClass {
    function prefixVariable1() {
        return 'prefix1';
    }
    function prefixVariable2() {
        return 'prefix2';
    }
}

class RouteCallableClass {
    function action1() {}
}

/**
 * @covers \Dynart\Micro\Router
 */
final class RouterTest extends TestCase
{
    /** @var \Dynart\Micro\Config&\PHPUnit\Framework\MockObject\MockObject */
    private $config;

    /** @var \Dynart\Micro\Request&\PHPUnit\Framework\MockObject\MockObject */
    private $request;

    protected function setUp(): void {
        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function mockConfigGetWithNoRewrite() {
        $this->config->method('get')
            ->will($this->returnValueMap([
                ['app.base_url', null, true, 'https://test.com'],
                ['app.index_file', null, true, 'index.php'],
                ['app.route_parameter', null, true, 'route'],
                ['app.use_rewrite', null, true, false]
            ]));
    }

    private function mockConfigGetWithRewrite() {
        $this->config->method('get')
            ->will($this->returnValueMap([
                ['app.base_url', null, true, 'https://test.com'],
                ['app.index_file', null, true, 'index.php'],
                ['app.route_parameter', null, true, 'route'],
                ['app.use_rewrite', null, true, true]
            ]));
    }

    private function mockRequestGetWithTestRoute() {
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue('/test/route'));
    }

    private function mockRequestGetWithTestRouteWithParameter() {
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue('/test/route/123'));
    }

    private function mockRequestGetWithPrefixVariablesAndTestRoute() {
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue('/pv1/pv2/test/route/v1'));
    }

    private function mockRequestGetWithPrefixVariablesAndHomeRoute() {
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue('/'));
    }

    private function createRequestWithPrefixVariables() {
        $router = new Router($this->config, $this->request);
        $prefixVariableCallableInstance = new PrefixVariableCallableClass();
        $segmentIndex1 = $router->addPrefixVariable([$prefixVariableCallableInstance, 'prefixVariable1']);
        $segmentIndex2 = $router->addPrefixVariable([$prefixVariableCallableInstance, 'prefixVariable2']);
        return [$router, $segmentIndex1, $segmentIndex2];
    }

    public function testUrlWithNoRewriteAndHttpQueryParameters() {
        $this->mockConfigGetWithNoRewrite();
        $this->mockRequestGetWithTestRoute();
        $router = new Router($this->config, $this->request);
        $this->assertEquals(
            'https://test.com/index.php?param=value&route=/test/route',
            $router->url('/test/route', ['param' => 'value'])
        );
    }

    public function testUrlWithRewriteAndHttpQueryParameters() {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRoute();
        $router = new Router($this->config, $this->request);
        $this->assertEquals(
            'https://test.com/test/route?param=value',
            $router->url('/test/route', ['param' => 'value'])
        );
    }

    public function testCurrentSegment() {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRoute();
        $router = new Router($this->config, $this->request);
        $this->assertEquals('test', $router->currentSegment(0));
        $this->assertEquals('route', $router->currentSegment(1));
    }

    public function testAddPrefixVariableWhenTwoAddedThenUrlShouldReturnWithThoseAtTheBeginning() {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRoute();
        list($router, $segmentIndex1, $segmentIndex2) = $this->createRequestWithPrefixVariables();
        $this->assertEquals($segmentIndex1, 0);
        $this->assertEquals($segmentIndex2, 1);
        $this->assertEquals('https://test.com/prefix1/prefix2/test/route', $router->url('/test/route'));
    }

    public function testMatchCurrentRouteWithPrefixVariablesAndAPathParameter() {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithPrefixVariablesAndTestRoute();
        list($router, $segmentIndex1, $segmentIndex2) = $this->createRequestWithPrefixVariables();
        $routeCallableInstance = new RouteCallableClass();
        $callable = [$routeCallableInstance, 'action1'];
        $router->add('/test/route/?', $callable);
        $this->assertEquals([$callable, ['v1']], $router->matchCurrentRoute());
    }

    public function testMatchCurrentRouteWithHomeRouteWithPrefixVariables() {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithPrefixVariablesAndHomeRoute();
        list($router, $segmentIndex1, $segmentIndex2) = $this->createRequestWithPrefixVariables();
        $routeCallableInstance = new RouteCallableClass();
        $callable = [$routeCallableInstance, 'action1'];
        $router->add('/', $callable);
        $this->assertEquals([$callable, []], $router->matchCurrentRoute());
    }

    public function testMatchCurrentRouteReturnsNotFound() {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRouteWithParameter();
        $router = new Router($this->config, $this->request);
        $router->add('/test/route/never-called', []);
        $this->assertEquals(Router::ROUTE_NOT_FOUND, $router->matchCurrentRoute());
        $router->add('/test/route', []);
        $this->assertEquals(Router::ROUTE_NOT_FOUND, $router->matchCurrentRoute());
    }

    public function testAddRouteWithBothMethod() {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRoute();
        $router = new Router($this->config, $this->request);
        $routeCallableInstance = new RouteCallableClass();
        $callable = [$routeCallableInstance, 'action1'];
        $router->add('/', $callable, 'BOTH');
        $routes = $router->routes();
        $this->assertIsArray($routes);
        $this->assertEquals(2, count($routes));
        $this->assertEquals($callable, $routes['GET']['/']);
        $this->assertEquals($callable, $routes['POST']['/']);
    }
}