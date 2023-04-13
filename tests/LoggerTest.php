<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Logger;
use Dynart\Micro\Config;

/**
 * @covers \Dynart\Micro\Logger
 */
final class LoggerTest extends TestCase {

    private $dir;

    /** @var Logger */
    private $logger;

    protected function setUp(): void {
        $this->dir = dirname(dirname(__FILE__)).'/logs';

        /** @var \Dynart\Micro\Config&\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap([
                [Logger::CONFIG_LEVEL, Logger::DEFAULT_LEVEL, true, 'info'],
                [Logger::CONFIG_DIR, Logger::DEFAULT_DIR, true, $this->dir],
                [Logger::CONFIG_OPTIONS, Logger::DEFAULT_OPTIONS, true, $this->dir],
            ]));

        $config->expects($this->once())
            ->method('getArray')
            ->will($this->returnValue([]));

        $this->logger = new Logger($config);
    }



    public function testConstructorSetsLogLevelDirAndOptions() {
        $path = $this->logger->getLogFilePath();
        $this->assertEquals($this->dir, dirname($path));
    }

    public function testLevel() {
        $this->assertEquals('info', $this->logger->level());
    }

    public function testErrorLog() { //don't know how to test this...
        $expectedErrorMsg = "_LOG_TEST_"; // this should be in stderr, but that is in use so can't open
        $this->logger->error($expectedErrorMsg);
        $this->assertInstanceOf(Logger::class, $this->logger);
    }
}