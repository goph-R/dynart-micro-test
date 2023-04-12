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

class TestWebApp extends WebApp {
    public function finish(string $content = '') {}
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

/**
 * @covers \Dynart\Micro\WebApp
 */
final class WebAppTest extends TestCase
{
    /** @var TestWebApp */
    private $webApp;

    protected function setUp(): void {
        $basePath = dirname(dirname(__FILE__));
        $this->webApp = new TestWebApp([$basePath.'/webapp.config.ini', $basePath.'/webapp.config-extend.ini']);
    }

    public function testBaseClassesWereAdded() {
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


}