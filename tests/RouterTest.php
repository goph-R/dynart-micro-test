<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Config;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Router;
use Dynart\Micro\Request;
use Dynart\Micro\RequestInterface;
use Dynart\Micro\AbstractApp;

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
        $this->config = $this->getMockBuilder(ConfigInterface::class)
            ->getMock();

        $this->request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();
    }

    private function mockConfigGetWithNoRewrite(): void {
        $this->config->method('get')
            ->will($this->returnValueMap([
                [AbstractApp::CONFIG_BASE_URL, null, true, 'https://test.com'],
                [Router::CONFIG_INDEX_FILE, Router::DEFAULT_INDEX_FILE, true, 'index.php'],
                [Router::CONFIG_ROUTE_PARAMETER, Router::DEFAULT_ROUTE_PARAMETER, true, 'route'],
                [Router::CONFIG_USE_REWRITE, Router::DEFAULT_USE_REWRITE, true, false]
            ]));
    }

    private function mockConfigGetWithRewrite(): void {
        $this->config->method('get')
            ->will($this->returnValueMap([
                [AbstractApp::CONFIG_BASE_URL, null, true, 'https://test.com'],
                [Router::CONFIG_INDEX_FILE, Router::DEFAULT_INDEX_FILE, true, 'index.php'],
                [Router::CONFIG_ROUTE_PARAMETER, Router::DEFAULT_ROUTE_PARAMETER, true, 'route'],
                [Router::CONFIG_USE_REWRITE, Router::DEFAULT_USE_REWRITE, true, true]
            ]));
    }

    private function mockRequestGetWithTestRoute(): void {
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue('/test/route'));
    }

    private function mockRequestGetWithTestRouteWithParameter(): void {
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue('/test/route/123'));
    }

    private function mockRequestGetWithPrefixVariablesAndTestRoute(): void {
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue('/pv1/pv2/test/route/v1'));
    }

    private function mockRequestGetWithPrefixVariablesAndHomeRoute(): void {
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

    public function testUrlWithNoRewriteAndHttpQueryParameters(): void {
        $this->mockConfigGetWithNoRewrite();
        $this->mockRequestGetWithTestRoute();
        $router = new Router($this->config, $this->request);
        $this->assertEquals(
            'https://test.com/index.php?param=value&route=/test/route',
            $router->url('/test/route', ['param' => 'value'])
        );
    }

    public function testUrlWithRewriteAndHttpQueryParameters(): void {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRoute();
        $router = new Router($this->config, $this->request);
        $this->assertEquals(
            'https://test.com/test/route?param=value',
            $router->url('/test/route', ['param' => 'value'])
        );
    }

    public function testCurrentSegment(): void {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRoute();
        $router = new Router($this->config, $this->request);
        $this->assertEquals('test', $router->currentSegment(0));
        $this->assertEquals('route', $router->currentSegment(1));
    }

    public function testAddPrefixVariableWhenTwoAddedThenUrlShouldReturnWithThoseAtTheBeginning(): void {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRoute();
        list($router, $segmentIndex1, $segmentIndex2) = $this->createRequestWithPrefixVariables();
        $this->assertEquals($segmentIndex1, 0);
        $this->assertEquals($segmentIndex2, 1);
        $this->assertEquals('https://test.com/prefix1/prefix2/test/route', $router->url('/test/route'));
    }

    public function testMatchCurrentRouteWithPrefixVariablesAndAPathParameter(): void {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithPrefixVariablesAndTestRoute();
        list($router, $segmentIndex1, $segmentIndex2) = $this->createRequestWithPrefixVariables();
        $routeCallableInstance = new RouteCallableClass();
        $callable = [$routeCallableInstance, 'action1'];
        $router->add('/test/route/?', $callable);
        $this->assertEquals([$callable, ['v1']], $router->matchCurrentRoute());
    }

    public function testMatchCurrentRouteWithHomeRouteWithPrefixVariables(): void {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithPrefixVariablesAndHomeRoute();
        list($router, $segmentIndex1, $segmentIndex2) = $this->createRequestWithPrefixVariables();
        $routeCallableInstance = new RouteCallableClass();
        $callable = [$routeCallableInstance, 'action1'];
        $router->add('/', $callable);
        $this->assertEquals([$callable, []], $router->matchCurrentRoute());
    }

    public function testMatchCurrentRouteReturnsNotFound(): void {
        $this->mockConfigGetWithRewrite();
        $this->mockRequestGetWithTestRouteWithParameter();
        $router = new Router($this->config, $this->request);
        $router->add('/test/route/never-called', []);
        $this->assertEquals(Router::ROUTE_NOT_FOUND, $router->matchCurrentRoute());
        $router->add('/test/route', []);
        $this->assertEquals(Router::ROUTE_NOT_FOUND, $router->matchCurrentRoute());
    }

    public function testAddRouteWithBothMethod(): void {
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

    // --- Catch-all routes ---

    /**
     * @return Router A router whose current path is the given one
     */
    private function routerAt(string $path): Router {
        $this->mockConfigGetWithRewrite();
        $this->request->method('httpMethod')->will($this->returnValue('GET'));
        $this->request->method('get')->will($this->returnValue($path));
        return new Router($this->config, $this->request);
    }

    public function testCatchAllCapturesTheWholeRemainderAsOneParameter(): void {
        $router = $this->routerAt('/docs/guide/install');
        $router->add('/docs/*', ['Test', 'callable']);
        $this->assertEquals([['Test', 'callable'], ['guide/install']], $router->matchCurrentRoute());
    }

    public function testCatchAllCapturesASingleSegmentToo(): void {
        $router = $this->routerAt('/docs/guide');
        $router->add('/docs/*', ['Test', 'callable']);
        $this->assertEquals([['Test', 'callable'], ['guide']], $router->matchCurrentRoute());
    }

    /**
     * `/docs/*` is "docs plus something", so the bare prefix is a different route.
     */
    public function testCatchAllNeedsAtLeastOneSegment(): void {
        $router = $this->routerAt('/docs');
        $router->add('/docs/*', ['Test', 'callable']);
        $this->assertEquals(Router::ROUTE_NOT_FOUND, $router->matchCurrentRoute());
    }

    public function testCatchAllAtTheRootMatchesEverything(): void {
        $router = $this->routerAt('/about/contact');
        $router->add('/*', ['Test', 'callable']);
        $this->assertEquals([['Test', 'callable'], ['about/contact']], $router->matchCurrentRoute());
    }

    public function testCatchAllAfterASegmentVariable(): void {
        $router = $this->routerAt('/a/b/c/d');
        $router->add('/a/?/*', ['Test', 'callable']);
        $this->assertEquals([['Test', 'callable'], ['b', 'c/d']], $router->matchCurrentRoute());
    }

    /**
     * The whole point of the two-pass match: a catch-all registered first must not swallow an
     * exact route registered after it.
     */
    public function testAnExactRouteWinsOverACatchAllRegisteredBefore(): void {
        $router = $this->routerAt('/login');
        $router->add('/*', ['Test', 'catchAll']);
        $router->add('/login', ['Test', 'exact']);
        $this->assertEquals([['Test', 'exact'], []], $router->matchCurrentRoute());
    }

    public function testASegmentRouteWinsOverACatchAll(): void {
        $router = $this->routerAt('/post/hello');
        $router->add('/*', ['Test', 'catchAll']);
        $router->add('/post/?', ['Test', 'segment']);
        $this->assertEquals([['Test', 'segment'], ['hello']], $router->matchCurrentRoute());
    }

    public function testTheCatchAllStillMatchesWhatNothingElseDoes(): void {
        $router = $this->routerAt('/about/contact');
        $router->add('/*', ['Test', 'catchAll']);
        $router->add('/login', ['Test', 'exact']);
        $this->assertEquals([['Test', 'catchAll'], ['about/contact']], $router->matchCurrentRoute());
    }

    public function testHasCatchAll(): void {
        $router = $this->routerAt('/');
        $this->assertTrue($router->hasCatchAll('/docs/*'));
        $this->assertTrue($router->hasCatchAll('/*'));
        $this->assertFalse($router->hasCatchAll('/docs/?'));
        $this->assertFalse($router->hasCatchAll('/docs'));
    }
}
