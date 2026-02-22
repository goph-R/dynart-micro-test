<?php

require_once dirname(dirname(__FILE__)).'/src/ResettableMicro.php';
use Dynart\Micro\Test\ResettableMicro;

use PHPUnit\Framework\TestCase;

use Dynart\Micro\Micro;
use Dynart\Micro\AbstractApp;
use Dynart\Micro\Config;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Logger;
use Dynart\Micro\LoggerInterface;
use Dynart\Micro\MiddlewareInterface;
use Dynart\Micro\MicroException;
use Dynart\Micro\EventServiceInterface;


class TestApp extends AbstractApp {
    protected bool $exitOnFinish = false;
    public function init(): void {}
    public function process(): void {}
    protected function isCli(): bool {
        return false;
    }
}

class AppTestRunLog {
    public static array $log = [];
    public static function reset(): void { self::$log = []; }
}

class AppTestHighPriorityMiddleware implements MiddlewareInterface {
    public function run(): void { AppTestRunLog::$log[] = 'high'; }
}

class AppTestLowPriorityMiddleware implements MiddlewareInterface {
    public function run(): void { AppTestRunLog::$log[] = 'low'; }
}

class InitExceptionConfig extends Config {
    public function __construct() {
        parent::__construct();
        throw new MicroException("Config exception on init");
    }
}

class InitExceptionLogger extends Logger {
    public function __construct(ConfigInterface $config) {
        parent::__construct($config);
        throw new MicroException("Logger exception on init");
    }
}

class TestAppInitException extends TestApp {
    public function init(): void {
        throw new MicroException("Exception on init");
    }
}

class TestAppInitExceptionWithConfig extends TestApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(ConfigInterface::class, InitExceptionConfig::class);
    }
}

class TestAppInitExceptionWithLogger extends TestApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(LoggerInterface::class, InitExceptionLogger::class);
    }
}

class TestAppLogger extends Logger {
    private ?string $errorMessage = null;
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
        Micro::add(LoggerInterface::class, TestAppLogger::class);
    }
    public function process(): void {
        throw new MicroException("Test exception");
    }
}

class AppTestMiddleware implements MiddlewareInterface {
    private bool $didRun = false;
    public function run(): void {
        $this->didRun = true;
    }
    public function didRun() {
        return $this->didRun;
    }
}

/**
 * @covers \Dynart\Micro\AbstractApp
 */
final class AppTest extends TestCase
{
    private TestApp $app;

    protected function setUp(): void {
        ResettableMicro::reset();
        AppTestRunLog::reset();
        $basePath = dirname(dirname(__FILE__));
        $this->app = new TestApp([$basePath.'/configs/app.ini', $basePath.'/configs/app-extend.ini']);
    }

    public function testFullInitLoadsConfigs(): void {
        /** @var Config $config */
        $this->app->fullInit();
        $config = Micro::get(ConfigInterface::class);
        $this->assertTrue($config->get('loaded'));
        $this->assertTrue($config->get('extension_loaded'));
    }

    public function testFullInitCallsMiddlewares(): void {
        $this->app->addMiddleware(AppTestMiddleware::class);
        $this->app->fullInit();
        $middleware = Micro::get(AppTestMiddleware::class);
        $this->assertTrue($middleware->didRun());
    }

    public function testHandleExceptionOnFullInitWithConfig(): void {
        $this->expectException(MicroException::class);
        $app = new TestAppInitExceptionWithConfig([dirname(dirname(__FILE__)).'/configs/app.ini']);
        $app->fullInit();
    }

    public function testHandleExceptionOnFullInitWithLogger(): void {
        $this->expectException(MicroException::class);
        $app = new TestAppInitExceptionWithLogger([dirname(dirname(__FILE__)).'/configs/app.ini']);
        $app->fullInit();
    }

    public function testHandleExceptionOnFullProcess(): void {
        $app = new TestAppProcessException([dirname(dirname(__FILE__)).'/configs/app.ini']);
        $app->fullInit();
        ob_start();
        $app->fullProcess();
        $content = ob_get_clean();
        error_log($content);
        $logger = Micro::get(LoggerInterface::class);
        $this->assertTrue(strpos($logger->errorMessage(), 'Test exception') !== false);
    }

    public function testFinish(): void {
        ob_start();
        $this->app->finish('test');
        $content = ob_get_clean();
        $this->assertEquals('test', $content);
    }

    public function testInitFinishedEventIsEmitted(): void {
        $emitted = false;
        Micro::get(EventServiceInterface::class)->subscribe(AbstractApp::EVENT_INIT_FINISHED, function() use (&$emitted) {
            $emitted = true;
        });
        $this->app->fullInit();
        $this->assertTrue($emitted);
    }

    public function testHasMiddleware(): void {
        $this->assertFalse($this->app->hasMiddleware(AppTestMiddleware::class));
        $this->app->addMiddleware(AppTestMiddleware::class);
        $this->assertTrue($this->app->hasMiddleware(AppTestMiddleware::class));
    }

    public function testAddMiddlewareIsIdempotent(): void {
        $this->app->addMiddleware(AppTestHighPriorityMiddleware::class);
        $this->app->addMiddleware(AppTestHighPriorityMiddleware::class);
        $this->app->fullInit();
        $this->assertCount(1, AppTestRunLog::$log);
    }

    public function testAddMiddlewareWithLowerNumberPriorityRunsFirst(): void {
        $this->app->addMiddleware(AppTestLowPriorityMiddleware::class, 100);
        $this->app->addMiddleware(AppTestHighPriorityMiddleware::class, 10);
        $this->app->fullInit();
        $this->assertEquals(['high', 'low'], AppTestRunLog::$log);
    }
}