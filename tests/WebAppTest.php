<?php

require_once 'ResettableMicro.php';

use PHPUnit\Framework\TestCase;

use Dynart\Micro\Micro;
use Dynart\Micro\WebApp;
use Dynart\Micro\Config;
use Dynart\Micro\Logger;
use Dynart\Micro\Request;
use Dynart\Micro\Response;
use Dynart\Micro\Router;
use Dynart\Micro\Session;
use Dynart\Micro\View;
use Dynart\Micro\Middleware;
use Dynart\Micro\MicroException;
use Dynart\Micro\Middleware\AnnotationProcessor;
use Dynart\Micro\Annotation\RouteAnnotation;

class TestWebApp extends WebApp {
    private $finished = false;
    private $errorCode = -1;
    public function finish($content = 0) {
        echo $content;
        $this->finished = true;
    }
    public function isFinished() {
        return $this->finished;
    }
    protected function isCli() {
        return false;
    }
    public function errorCode() {
        return $this->errorCode;
    }
    public function sendError(int $code, $content = '') {
        parent::sendError($code, $content);
        $this->errorCode = $code;
    }
    public function hasMiddleware($middleware) {
        return in_array($middleware, $this->middlewares);
    }
}

class InitExceptionRouter extends Router {
    public function __construct(Config $config, Request $request) {
        parent::__construct($config, $request);
        throw new MicroException("Router exception on init");
    }
}

class InitExceptionConfig extends Config {
    public function __construct() {
        parent::__construct();
        throw new MicroException("Config exception on init");
    }
}

class InitExceptionLogger extends Logger {
    public function __construct(Config $config) {
        parent::__construct($config);
        throw new MicroException("Logger exception on init");
    }
}

class TestWebAppInitException extends TestWebApp {
    public function init() {
        throw new MicroException("Exception on init");
    }
}

class TestWebAppInitExceptionWithRouter extends TestWebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Router::class, InitExceptionRouter::class);
    }
}


class TestWebAppWithNoErrorPage extends WebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Router::class, InitExceptionRouter::class);
    }
    public function finish($content = 0) {}
}


class TestWebAppInitExceptionWithConfig extends TestWebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Config::class, InitExceptionConfig::class);
    }
}

class TestWebAppInitExceptionWithLogger extends TestWebApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Logger::class, InitExceptionLogger::class);
    }
}

class TestWebAppSendError extends TestWebApp {

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
        ResettableMicro::reset();
        $basePath = dirname(dirname(__FILE__));
        $this->webApp = new TestWebApp([$basePath.'/configs/webapp.config.ini', $basePath.'/configs/webapp.config-extend.ini']);
    }

    private function setUpWebAppForProcess() {
        $_REQUEST['route'] = '/test/route/123';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->webApp->fullInit();
    }

    private function processAndFetchOutput() {
        ob_start();
        $this->webApp->fullProcess();
        return ob_get_clean();
    }

    public function testConstructorShouldAddBaseWebRelatedClasses() {
        $this->assertTrue(Micro::hasInterface(Request::class));
        $this->assertTrue(Micro::hasInterface(Response::class));
        $this->assertTrue(Micro::hasInterface(Router::class));
        $this->assertTrue(Micro::hasInterface(Session::class));
        $this->assertTrue(Micro::hasInterface(View::class));
    }

    public function testFullInitLoadsConfigs() {
        /** @var Config $config */
        $this->webApp->fullInit();
        $config = Micro::get(Config::class);
        $this->assertTrue($config->get('loaded'));
        $this->assertTrue($config->get('extension_loaded'));
    }

    public function testFullInitCallsMiddlewares() {
        $this->webApp->addMiddleware(TestMiddleware::class);
        $this->webApp->fullInit();
        $middleware = Micro::get(TestMiddleware::class);
        $this->assertTrue($middleware->didRun());
    }

    public function testProcessCallsRouteWithParameterOutputsString() {
        $this->setUpWebAppForProcess();
        Micro::get(Router::class)->add('/test/route/?', function($value) { return $value; });
        $content = $this->processAndFetchOutput();
        $this->assertEquals(WebApp::CONTENT_TYPE_HTML, Micro::get(Response::class)->header(WebApp::HEADER_CONTENT_TYPE));
        $this->assertEquals('123', $content);
    }

    public function testProcessCallsRouteWithParameterOutputsArrayAsJsonString() {
        $this->setUpWebAppForProcess();
        Micro::get(Router::class)->add('/test/route/?', function($value) { return ['value' => $value]; });
        $content = $this->processAndFetchOutput();
        $this->assertEquals(WebApp::CONTENT_TYPE_JSON, Micro::get(Response::class)->header(WebApp::HEADER_CONTENT_TYPE));
        $this->assertEquals('{"value":"123"}', $content);
    }

    public function testProcessCallsRouteWithClassAndMethodName() {
        $this->setUpWebAppForProcess();
        Micro::add(TestController::class);
        Micro::get(Router::class)->add('/test/route/?', [TestController::class, 'index']);
        $content = $this->processAndFetchOutput();
        $this->assertEquals('test', $content);
    }

    public function testRedirectClearsHeadersSetsOnlyLocationSendsNoContentAndFinishes() {
        $this->webApp->init();
        $response = Micro::get(Response::class);
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
        $response = Micro::get(Response::class);
        $this->webApp->redirect('https://somewhere.com');
        $this->assertEquals('https://somewhere.com', $response->header('Location'));
    }

    public function testHandleExceptionOnFullProcess() {
        $this->setUpWebAppForProcess();
        Micro::get(Router::class)->add('/test/route/?', function($value) { throw new MicroException("error"); });
        ob_start();
        $this->webApp->fullProcess();
        $content = ob_get_clean();
        $this->assertTrue(strpos($content, '<h2>Dynart\Micro\MicroException</h2>') !== false);
    }

    public function testHandleExceptionOnFullInitWithRouter() {
        $webApp = new TestWebAppInitExceptionWithRouter([dirname(dirname(__FILE__)).'/configs/webapp.config.ini']);
        ob_start();
        $webApp->fullInit();
        $content = ob_get_clean();
        $this->assertTrue(strpos($content, '<h2>Dynart\Micro\MicroException</h2>') !== false);
    }

    public function testHandleExceptionOnFullInitWithConfig() {
        $this->expectException(MicroException::class);
        $webApp = new TestWebAppInitExceptionWithConfig([dirname(dirname(__FILE__)).'/configs/webapp.config.ini']);
        $webApp->fullInit();
    }

    public function testHandleExceptionOnFullInitWithLogger() {
        $this->expectException(MicroException::class);
        $webApp = new TestWebAppInitExceptionWithLogger([dirname(dirname(__FILE__)).'/configs/webapp.config.ini']);
        $webApp->fullInit();
    }

    public function testHandleExceptionOnFullInitWithRouterWithCliAndWithNoErrorPages() { // just for coverage
        $webApp = new TestWebAppWithNoErrorPage([dirname(dirname(__FILE__)).'/configs/webapp.config.no-error-pages.ini']);
        $webApp->fullInit();
        $this->assertInstanceOf(WebApp::class, $webApp);
    }

    public function testSendError404() {
        $this->setUpWebAppForProcess();
        $this->webApp->fullProcess();
        $this->assertEquals(404, $this->webApp->errorCode());
    }

    public function testUseRouteAnnotations() {
        $this->setUpWebAppForProcess();
        $this->webApp->useRouteAnnotations();
        $this->assertTrue(Micro::hasInterface(AnnotationProcessor::class));
        $this->assertTrue(Micro::hasInterface(RouteAnnotation::class));
        $this->assertTrue($this->webApp->hasMiddleware(AnnotationProcessor::class));
    }
}