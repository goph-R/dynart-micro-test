<?php

require_once dirname(dirname(__FILE__)).'/src/ResettableMicro.php';
use Dynart\Micro\Test\ResettableMicro;

use PHPUnit\Framework\TestCase;

use Dynart\Micro\Micro;
use Dynart\Micro\App;
use Dynart\Micro\Config;
use Dynart\Micro\Logger;
use Dynart\Micro\Middleware;
use Dynart\Micro\MicroException;


class TestApp extends App {
    private $finished = false;
    public function init() {}
    public function process() {}
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
    public function hasMiddleware($middleware) {
        return in_array($middleware, $this->middlewares);
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

class TestAppInitException extends TestApp {
    public function init() {
        throw new MicroException("Exception on init");
    }
}

class TestAppInitExceptionWithConfig extends TestApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Config::class, InitExceptionConfig::class);
    }
}

class TestAppInitExceptionWithLogger extends TestApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Logger::class, InitExceptionLogger::class);
    }
}

class TestAppLogger extends Logger {
    private $errorMessage;
    public function error($message, array $context = array()) {
        $this->errorMessage = $message;
    }
    public function errorMessage() {
        return $this->errorMessage;
    }
}

class TestAppProcessException extends TestApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Logger::class, TestAppLogger::class);
    }
    public function process() {
        throw new MicroException("Test exception");
    }
}

class AppTestMiddleware implements Middleware {
    private $didRun = false;
    public function run() {
        $this->didRun = true;
    }
    public function didRun() {
        return $this->didRun;
    }
}

/**
 * @covers \Dynart\Micro\App
 */
final class AppTest extends TestCase
{
    /** @var TestApp */
    private $app;

    protected function setUp(): void {
        ResettableMicro::reset();
        $basePath = dirname(dirname(__FILE__));
        $this->app = new TestApp([$basePath.'/configs/app.ini', $basePath.'/configs/app-extend.ini']);
    }

    public function testFullInitLoadsConfigs() {
        /** @var Config $config */
        $this->app->fullInit();
        $config = Micro::get(Config::class);
        $this->assertTrue($config->get('loaded'));
        $this->assertTrue($config->get('extension_loaded'));
    }

    public function testFullInitCallsMiddlewares() {
        $this->app->addMiddleware(AppTestMiddleware::class);
        $this->app->fullInit();
        $middleware = Micro::get(AppTestMiddleware::class);
        $this->assertTrue($middleware->didRun());
    }

    public function testHandleExceptionOnFullInitWithConfig() {
        $this->expectException(MicroException::class);
        $app = new TestAppInitExceptionWithConfig([dirname(dirname(__FILE__)).'/configs/app.ini']);
        $app->fullInit();
    }

    public function testHandleExceptionOnFullInitWithLogger() {
        $this->expectException(MicroException::class);
        $app = new TestAppInitExceptionWithLogger([dirname(dirname(__FILE__)).'/configs/app.ini']);
        $app->fullInit();
    }

    public function testHandleExceptionOnFullProcess() {
        $app = new TestAppProcessException([dirname(dirname(__FILE__)).'/configs/app.ini']);
        $app->fullInit();
        ob_start();
        $app->fullProcess();
        $content = ob_get_clean();
        error_log($content);
        $logger = Micro::get(Logger::class);
        $this->assertTrue(strpos($logger->errorMessage(), 'Test exception') !== false);
    }    
}