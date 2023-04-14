<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\WebApp;
use Dynart\Micro\Config;
use Dynart\Micro\Logger;
use Dynart\Micro\Request;
use Dynart\Micro\Response;
use Dynart\Micro\Router;
use Dynart\Micro\Session;
use Dynart\Micro\View;
use Dynart\Micro\Middleware;
use Dynart\Micro\AppException;

class TestWebApp extends WebApp {
    private $finished = false;
    public function finish(string $content = '') {
        echo $content;
        $this->finished = true;
    }
    public function isFinished() {
        return $this->finished;
    }
    protected function isCli() {
        return false;
    }
}

class InitExceptionRouter extends Router {
    public function __construct(Config $config, Request $request) {
        parent::__construct($config, $request);
        throw new AppException("Exception on init");
    }
}

class InitExceptionConfig extends Config {
    public function __construct() {
        parent::__construct();
        throw new AppException("Exception on init");
    }
}

class InitExceptionLogger extends Logger {
    public function __construct(Config $config) {
        parent::__construct($config);
        throw new AppException("Exception on init");
    }
}


class TestWebAppInitExceptionWithRouter extends TestWebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->add(Router::class, InitExceptionRouter::class);
    }
}


class TestWebAppWithNoErrorPage extends WebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->add(Router::class, InitExceptionRouter::class);
    }
    public function finish(string $content = '') {}
}


class TestWebAppInitExceptionWithConfig extends TestWebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->add(Config::class, InitExceptionConfig::class);
    }
}

class TestWebAppInitExceptionWithLogger extends TestWebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        $this->add(Logger::class, InitExceptionLogger::class);
    }
}

class TestMiddleware implements Middleware {
    private $didRun = false;
    public function run() {
        $this->didRun = true;
    }
    public function didRun() {
        return $this->didRun;
    }
}

class TestController {
    public function index() { return 'test'; }
}

/**
 * @covers \Dynart\Micro\WebApp
 */
final class WebAppTest extends TestCase
{
    /** @var TestWebApp */
    private $webApp;

    protected function setUp(): void {
        $basePath = dirname(dirname(__FILE__));
        $this->webApp = new TestWebApp([$basePath.'/configs/webapp.config.ini', $basePath.'/configs/webapp.config-extend.ini']);
    }

    private function setUpWebAppForProcess() {
        $_REQUEST['route'] = '/test/route/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->webApp->init();
    }

    private function fetchWebAppOutput() {
        ob_start();
        $this->webApp->process();
        return ob_end_clean();
    }

    public function testConstructorShouldAddBaseClasses() {
        $this->assertTrue($this->webApp->hasInterface(Config::class));
        $this->assertTrue($this->webApp->hasInterface(Logger::class));
        $this->assertTrue($this->webApp->hasInterface(Request::class));
        $this->assertTrue($this->webApp->hasInterface(Response::class));
        $this->assertTrue($this->webApp->hasInterface(Router::class));
        $this->assertTrue($this->webApp->hasInterface(Session::class));
        $this->assertTrue($this->webApp->hasInterface(View::class));
    }

    public function testInitLoadsConfigs() {
        /** @var Config $config */
        $this->webApp->init();
        $config = $this->webApp->get(Config::class);
        $this->assertTrue($config->get('loaded'));
        $this->assertTrue($config->get('extension_loaded'));
    }

    public function testInitCallsMiddlewares() {
        $this->webApp->addMiddleware(TestMiddleware::class);
        $this->webApp->init();
        $middleware = $this->webApp->get(TestMiddleware::class);
        $this->assertTrue($middleware->didRun());
    }

    public function testProcessCallsRouteWithParameterOutputsString() {
        $this->setUpWebAppForProcess();
        $this->webApp->get(Router::class)->add('/test/route/?', function($value) { return $value; });
        $content = $this->fetchWebAppOutput();
        $this->assertEquals(WebApp::CONTENT_TYPE_HTML, $this->webApp->get(Response::class)->header(WebApp::HEADER_CONTENT_TYPE));
        $this->assertEquals('123', $content);
    }

    public function testProcessCallsRouteWithParameterOutputsArrayAsJsonString() {
        $this->setUpWebAppForProcess();
        $this->webApp->get(Router::class)->add('/test/route/?', function($value) { return ['value' => $value]; });
        $content = $this->fetchWebAppOutput();
        $this->assertEquals(WebApp::CONTENT_TYPE_JSON, $this->webApp->get(Response::class)->header(WebApp::HEADER_CONTENT_TYPE));
        $this->assertEquals('{"value":"123"}', $content);
    }

    public function testProcessCallsRouteWithClassAndMethodName() {
        $this->setUpWebAppForProcess();
        $this->webApp->add(TestController::class);
        $this->webApp->get(Router::class)->add('/test/route/?', [TestController::class, 'index']);
        $content = $this->fetchWebAppOutput();
        $this->assertEquals('test', $content);
    }

    public function testRedirectClearsHeadersSetsOnlyLocationSendsNoContentAndFinishes() {
        $this->webApp->init();
        $response = $this->webApp->get(Response::class);
        $response->setHeader('remove', 'this');
        ob_start();
        $this->webApp->redirect('somewhere');
        $content = ob_get_clean();
        $this->assertEquals('/index.php?route=somewhere', $response->header('Location'));
        $this->assertNull($response->header('remove'));
        $this->assertEmpty($content);
        $this->assertTrue($this->webApp->isFinished());
    }

    public function testRedirectWithFullUrl() {
        $this->webApp->init();
        $response = $this->webApp->get(Response::class);
        $this->webApp->redirect('https://somewhere.com');
        $this->assertEquals('https://somewhere.com', $response->header('Location'));
    }

    public function testHandleExceptionOnProcess() {
        $this->setUpWebAppForProcess();
        $this->webApp->get(Router::class)->add('/test/route/?', function($value) { throw new AppException("error"); });
        ob_start();
        $this->webApp->process();
        $content = ob_get_clean();
        $this->assertTrue(strpos($content, '<h2>Dynart\Micro\AppException</h2>') !== false);
    }

    public function testHandleExceptionOnInitWithRouter() {
        $webApp = new TestWebAppInitExceptionWithRouter([dirname(dirname(__FILE__)).'/configs/webapp.config.ini']);
        ob_start();
        $webApp->init();
        $content = ob_get_clean();
        echo $content;
        $this->assertTrue(strpos($content, '<h2>Dynart\Micro\AppException</h2>') !== false);
    }

    public function testHandleExceptionOnInitWithConfig() {
        $this->expectException(AppException::class);
        $webApp = new TestWebAppInitExceptionWithConfig([dirname(dirname(__FILE__)).'/configs/webapp.config.ini']);
        $webApp->init();
    }

    public function testHandleExceptionOnInitWithLogger() {
        $this->expectException(AppException::class);
        $webApp = new TestWebAppInitExceptionWithLogger([dirname(dirname(__FILE__)).'/configs/webapp.config.ini']);
        $webApp->init();
    }

    public function testHandleExceptionOnInitWithRouterWithCliAndWithNoErrorPages() { // just for coverage
        $webApp = new TestWebAppWithNoErrorPage([dirname(dirname(__FILE__)).'/configs/webapp.config.no-error-pages.ini']);
        $webApp->init();
        $this->assertInstanceOf(WebApp::class, $webApp);
    }
}