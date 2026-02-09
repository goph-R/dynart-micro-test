<?php

require_once dirname(__FILE__, 2) .'/src/ResettableMicro.php';

use PHPUnit\Framework\TestCase;

use Dynart\Micro\Micro;
use Dynart\Micro\Config;
use Dynart\Micro\Logger;
use Dynart\Micro\CliApp;
use Dynart\Micro\CliCommands;
use Dynart\Micro\CliOutput;
use Dynart\Micro\MicroException;

use Dynart\Micro\Test\ResettableMicro;

class TestCliApp extends CliApp {
    private bool $finished = false;
    private mixed $finishContent = null;

    public function finish(string|int $content = 0): void {
        $this->finishContent = $content;
        $this->finished = true;
    }

    public function isFinished(): bool {
        return $this->finished;
    }

    public function finishContent() {
        return $this->finishContent;
    }
}

class TestCliAppLogger extends Logger {
    private ?string $errorMessage = null;
    public function error($message, array $context = array()) {
        $this->errorMessage = $message;
    }
    public function errorMessage() {
        return $this->errorMessage;
    }
}

class TestCliAppWithException extends TestCliApp {
    public function __construct(array $configPaths) {
        parent::__construct($configPaths);
        Micro::add(Logger::class, TestCliAppLogger::class);
    }
    public function init(): void {
        parent::init();
        throw new MicroException("CLI init exception");
    }
}

class TestCliController {
    public function run() {
        return 0;
    }
    public function greet($params) {
        return 0;
    }
}

/**
 * @covers \Dynart\Micro\CliApp
 */
final class CliAppTest extends TestCase
{
    private TestCliApp $app;
    private string $basePath;

    protected function setUp(): void {
        ResettableMicro::reset();
        $this->basePath = dirname(__FILE__, 2);
        $this->app = new TestCliApp([$this->basePath.'/configs/app.ini']);
    }

    protected function tearDown(): void {
        unset($_SERVER['argv'], $_SERVER['argc']);
    }

    private function setArgv(array $args): void {
        $_SERVER['argv'] = $args;
        $_SERVER['argc'] = count($args);
    }

    public function testConstructorRegistersCliClasses(): void {
        $this->assertTrue(Micro::hasInterface(CliCommands::class));
        $this->assertTrue(Micro::hasInterface(CliOutput::class));
    }

    public function testInitCreatesCommands(): void {
        $this->app->fullInit();
        $this->assertInstanceOf(CliCommands::class, Micro::get(CliCommands::class));
    }

    public function testProcessCallsMatchedCommand(): void {
        $this->setArgv(['script.php', 'hello']);
        $this->app->fullInit();
        $called = false;
        Micro::get(CliCommands::class)->add('hello', function() use (&$called) {
            $called = true;
            return 0;
        });
        $this->app->fullProcess();
        $this->assertTrue($called);
    }

    public function testProcessPassesParamsToCommand(): void {
        $this->setArgv(['script.php', 'greet', '-name', 'world']);
        $this->app->fullInit();
        $receivedParams = null;
        Micro::get(CliCommands::class)->add('greet', function($params) use (&$receivedParams) {
            $receivedParams = $params;
            return 0;
        }, ['name']);
        $this->app->fullProcess();
        $this->assertEquals('world', $receivedParams['name']);
    }

    public function testProcessFinishesWithCommandReturnValue(): void {
        $this->setArgv(['script.php', 'test']);
        $this->app->fullInit();
        Micro::get(CliCommands::class)->add('test', function() {
            return 42;
        });
        $this->app->fullProcess();
        $this->assertEquals(42, $this->app->finishContent());
    }

    public function testProcessFinishesWithExitCode1ForUnknownCommand(): void {
        $this->setArgv(['script.php', 'nonexistent']);
        $this->app->fullInit();
        $this->app->fullProcess();
        $this->assertEquals(1, $this->app->finishContent());
    }

    public function testProcessCallsCommandWithNoParamsUsingCallUserFunc(): void {
        $this->setArgv(['script.php', 'simple']);
        $this->app->fullInit();
        $called = false;
        Micro::get(CliCommands::class)->add('simple', function() use (&$called) {
            $called = true;
            return 0;
        });
        $this->app->fullProcess();
        $this->assertTrue($called);
        $this->assertTrue($this->app->isFinished());
    }

    public function testProcessWithMicroCallable(): void {
        $this->setArgv(['script.php', 'run']);
        $this->app->fullInit();
        Micro::add(TestCliController::class);
        Micro::get(CliCommands::class)->add('run', [TestCliController::class, 'run']);
        $this->app->fullProcess();
        $this->assertEquals(0, $this->app->finishContent());
    }

    public function testHandleExceptionFinishesWithExitCode1(): void {
        $this->setArgv(['script.php']);
        $app = new TestCliAppWithException([$this->basePath.'/configs/app.ini']);
        $app->fullInit();
        $this->assertEquals(1, $app->finishContent());
    }
}
