<?php

require_once dirname(__FILE__, 2) .'/src/ResettableMicro.php';

use PHPUnit\Framework\TestCase;

use Dynart\Micro\Micro;
use Dynart\Micro\App;
use Dynart\Micro\WebApp;
use Dynart\Micro\Config;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Request;
use Dynart\Micro\RequestInterface;
use Dynart\Micro\Response;
use Dynart\Micro\ResponseInterface;
use Dynart\Micro\Router;
use Dynart\Micro\RouterInterface;
use Dynart\Micro\SessionInterface;
use Dynart\Micro\ViewInterface;
use Dynart\Micro\MiddlewareInterface;
use Dynart\Micro\MicroException;
use Dynart\Micro\Middleware\AttributeProcessor;
use Dynart\Micro\AttributeHandler\RouteAttributeHandler;
use Dynart\Micro\EventServiceInterface;

use Dynart\Micro\Test\ResettableMicro;

class TestWebApp extends WebApp {
    private bool $finished = false;
    private string $errorCode = "";
    public function finish(string|int $content = 0): void
    {
        echo $content;
        $this->finished = true;
    }
    public function isFinished(): bool {
        return $this->finished;
    }
    protected function isCli(): bool {
        return false;
    }
    public function errorCode(): string {
        return $this->errorCode;
    }
    public function sendError(int $code, string $content = ''): void {
        parent::sendError($code, $content);
        $this->errorCode = $code;
    }
    public function hasMiddleware($middleware): bool {
        return in_array($middleware, $this->middlewares);
    }
}

class InitExceptionRouter extends Router {
    public function __construct(ConfigInterface $config, RequestInterface $request) {
        parent::__construct($config, $request);
        throw new MicroException("Router exception on init");
    }
}

class TestWebAppInitExceptionWithRouter extends TestWebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(RouterInterface::class, InitExceptionRouter::class);
    }
}


class TestWebAppWithNoErrorPage extends WebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(RouterInterface::class, InitExceptionRouter::class);
    }
    public function finish(string|int $content = 0): void {}
}

class TestWebAppSendError extends TestWebApp {

}

class WebAppTestMiddleware implements MiddlewareInterface {
    private bool $didRun = false;
    public function run(): void {
        $this->didRun = true;
    }
    public function didRun() {
        return $this->didRun;
    }
}

class TestController {
    public function index() { return 'test'; }
}

class WebAppProdConfig extends Config {
    public function get(string $name, mixed $default = null, $useCache = true): mixed {
        return $name == App::CONFIG_ENVIRONMENT ? App::PRODUCTION_ENVIRONMENT : parent::get($name, $default, $useCache);
    }
}

/**
 * @covers \Dynart\Micro\WebApp
 */
final class WebAppTest extends TestCase
{
    private TestWebApp $webApp;

    protected function setUp(): void {
        ResettableMicro::reset();
        $basePath = dirname(__FILE__, 2);
        $this->webApp = new TestWebApp([$basePath.'/configs/app.ini', $basePath.'/configs/app-extend.ini', $basePath.'/configs/webapp.error-pages.ini']);
    }

    private function setUpWebAppForProcess(): void {
        $_REQUEST['route'] = '/test/route/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->webApp->fullInit();
    }

    private function processAndFetchOutput(): bool|string {
        ob_start();
        $this->webApp->fullProcess();
        return ob_get_clean();
    }

    public function testConstructorShouldAddBaseWebRelatedClasses(): void {
        $this->assertTrue(Micro::hasInterface(RequestInterface::class));
        $this->assertTrue(Micro::hasInterface(ResponseInterface::class));
        $this->assertTrue(Micro::hasInterface(RouterInterface::class));
        $this->assertTrue(Micro::hasInterface(SessionInterface::class));
        $this->assertTrue(Micro::hasInterface(ViewInterface::class));
    }

    public function testProcessCallsRouteWithParameterOutputsString(): void {
        $this->setUpWebAppForProcess();
        Micro::get(RouterInterface::class)->add('/test/route/?', function($value) { return $value; });
        $content = $this->processAndFetchOutput();
        $this->assertEquals(WebApp::CONTENT_TYPE_HTML, Micro::get(ResponseInterface::class)->header(WebApp::HEADER_CONTENT_TYPE));
        $this->assertEquals('123', $content);
    }

    public function testProcessCallsRouteWithParameterOutputsArrayAsJsonString(): void {
        $this->setUpWebAppForProcess();
        Micro::get(RouterInterface::class)->add('/test/route/?', function($value) { return ['value' => $value]; });
        $content = $this->processAndFetchOutput();
        $this->assertEquals(WebApp::CONTENT_TYPE_JSON, Micro::get(ResponseInterface::class)->header(WebApp::HEADER_CONTENT_TYPE));
        $this->assertEquals('{"value":"123"}', $content);
    }

    public function testProcessCallsRouteWithClassAndMethodName(): void {
        $this->setUpWebAppForProcess();
        Micro::add(TestController::class);
        Micro::get(RouterInterface::class)->add('/test/route/?', [TestController::class, 'index']);
        $content = $this->processAndFetchOutput();
        $this->assertEquals('test', $content);
    }

    public function testRedirectClearsHeadersSetsOnlyLocationSendsNoContentAndFinishes(): void {
        $this->webApp->init();
        $response = Micro::get(ResponseInterface::class);
        $response->setHeader('remove', 'this');
        ob_start();
        $this->webApp->redirect('somewhere');
        $content = ob_get_clean();
        $this->assertEquals('/index.php?route=somewhere', $response->header('Location'));
        $this->assertNull($response->header('remove'));
        $this->assertEmpty($content);
        $this->assertTrue($this->webApp->isFinished());
    }

    public function testRedirectWithFullUrl(): void {
        $this->webApp->init();
        $response = Micro::get(ResponseInterface::class);
        $this->webApp->redirect('https://somewhere.com');
        $this->assertEquals('https://somewhere.com', $response->header('Location'));
    }

    public function testHandleExceptionOnFullProcess(): void {
        $this->setUpWebAppForProcess();
        Micro::get(RouterInterface::class)->add('/test/route/?', function($value) { throw new MicroException("error"); });

        ob_start();
        $this->webApp->fullProcess();
        $content = ob_get_clean();
        $this->assertTrue(str_contains($content, '<h2>Dynart\Micro\MicroException</h2>'));
    }

    public function testHandleExceptionOnFullProcessOnProduction(): void {
        Micro::add(ConfigInterface::class, WebAppProdConfig::class);
        $this->setUpWebAppForProcess();
        Micro::get(RouterInterface::class)->add('/test/route/?', function($value) { throw new MicroException("error"); });
        ob_start();
        $this->webApp->fullProcess();
        $content = ob_get_clean();
        $this->assertEmpty($content);
    }

    public function testHandleExceptionOnFullInitWithRouter(): void {
        $webApp = new TestWebAppInitExceptionWithRouter([dirname(dirname(__FILE__)).'/configs/app.ini']);
        ob_start();
        $webApp->fullInit();
        $content = ob_get_clean();
        $this->assertTrue(str_contains($content, '<h2>Dynart\Micro\MicroException</h2>'));
    }

    public function testHandleExceptionOnFullInitWithRouterWithCliAndWithNoErrorPages(): void {
        $webApp = new TestWebAppWithNoErrorPage([dirname(__FILE__, 2) .'/configs/app.ini']);
        $webApp->fullInit();
        $this->assertInstanceOf(WebApp::class, $webApp); // just for coverage
    }

    public function testSendError404(): void {
        $this->setUpWebAppForProcess();
        $this->webApp->fullProcess();
        $this->assertEquals(404, $this->webApp->errorCode());
    }

    public function testRouteMatchedEventIsEmitted(): void {
        $this->setUpWebAppForProcess();
        Micro::get(RouterInterface::class)->add('/test/route/?', function($value) { return $value; });
        $receivedCallable = null;
        $receivedParams = null;
        Micro::get(EventServiceInterface::class)->subscribe(WebApp::EVENT_ROUTE_MATCHED, function($callable, $params) use (&$receivedCallable, &$receivedParams) {
            $receivedCallable = $callable;
            $receivedParams = $params;
        });
        $this->processAndFetchOutput();
        $this->assertNotNull($receivedCallable);
        $this->assertEquals(['123'], $receivedParams);
    }

    public function testUseRouteAttributes(): void {
        $this->setUpWebAppForProcess();
        $this->webApp->useRouteAttributes();
        $this->assertTrue(Micro::hasInterface(AttributeProcessor::class));
        $this->assertTrue(Micro::hasInterface(RouteAttributeHandler::class));
        $this->assertTrue($this->webApp->hasMiddleware(AttributeProcessor::class));
    }
}