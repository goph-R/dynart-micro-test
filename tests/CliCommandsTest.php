<?php

use PHPUnit\Framework\TestCase;

use Dynart\Micro\CliCommands;

/**
 * @covers \Dynart\Micro\CliCommands
 */
final class CliCommandsTest extends TestCase
{
    private CliCommands $commands;

    protected function setUp(): void {
        $this->commands = new CliCommands();
    }

    protected function tearDown(): void {
        unset($_SERVER['argv'], $_SERVER['argc']);
    }

    private function setArgv(array $args): void {
        $_SERVER['argv'] = $args;
        $_SERVER['argc'] = count($args);
    }

    public function testCurrentReturnsNullWhenNoArgv(): void {
        $this->setArgv(['script.php']);
        $this->assertNull($this->commands->current());
    }

    public function testCurrentReturnsCommandName(): void {
        $this->setArgv(['script.php', 'migrate']);
        $this->assertEquals('migrate', $this->commands->current());
    }

    public function testMatchCurrentReturnsNullForUnknownCommand(): void {
        $this->setArgv(['script.php', 'unknown']);
        $this->assertNull($this->commands->matchCurrent());
    }

    public function testMatchCurrentReturnsNullWhenNoArgv(): void {
        $this->setArgv(['script.php']);
        $this->assertNull($this->commands->matchCurrent());
    }

    public function testMatchCurrentReturnsCallableWithNoParams(): void {
        $callable = function() { return 'ok'; };
        $this->commands->add('test', $callable);
        $this->setArgv(['script.php', 'test']);

        $result = $this->commands->matchCurrent();

        $this->assertSame($callable, $result[0]);
        $this->assertEmpty($result[1]);
    }

    public function testMatchCurrentParsesNamedParameters(): void {
        $callable = function($params) {};
        $this->commands->add('generate', $callable, ['name', 'type']);
        $this->setArgv(['script.php', 'generate', '-name', 'users', '-type', 'migration']);

        $result = $this->commands->matchCurrent();

        $this->assertSame($callable, $result[0]);
        $this->assertEquals('users', $result[1]['name']);
        $this->assertEquals('migration', $result[1]['type']);
    }

    public function testMatchCurrentParsesFlags(): void {
        $callable = function($params) {};
        $this->commands->add('build', $callable, [], ['verbose', 'force']);
        $this->setArgv(['script.php', 'build', '-verbose', '-force']);

        $result = $this->commands->matchCurrent();

        $this->assertTrue($result[1]['verbose']);
        $this->assertTrue($result[1]['force']);
    }

    public function testMatchCurrentFlagsDefaultToFalse(): void {
        $callable = function($params) {};
        $this->commands->add('build', $callable, [], ['verbose', 'force']);
        $this->setArgv(['script.php', 'build']);

        $result = $this->commands->matchCurrent();

        $this->assertFalse($result[1]['verbose']);
        $this->assertFalse($result[1]['force']);
    }

    public function testMatchCurrentNamedParamsDefaultToEmpty(): void {
        $callable = function($params) {};
        $this->commands->add('generate', $callable, ['name', 'type']);
        $this->setArgv(['script.php', 'generate']);

        $result = $this->commands->matchCurrent();

        $this->assertEquals('', $result[1]['name']);
        $this->assertEquals('', $result[1]['type']);
    }

    public function testMatchCurrentMixesNamedParamsAndFlags(): void {
        $callable = function($params) {};
        $this->commands->add('deploy', $callable, ['env'], ['dry']);
        $this->setArgv(['script.php', 'deploy', '-env', 'production', '-dry']);

        $result = $this->commands->matchCurrent();

        $this->assertEquals('production', $result[1]['env']);
        $this->assertTrue($result[1]['dry']);
    }

    public function testMatchCurrentPositionalArguments(): void {
        $callable = function($params) {};
        $this->commands->add('copy', $callable);
        $this->setArgv(['script.php', 'copy', 'source.txt', 'dest.txt']);

        $result = $this->commands->matchCurrent();

        $this->assertEquals('source.txt', $result[1][0]);
        $this->assertEquals('dest.txt', $result[1][1]);
    }

    public function testMatchCurrentIgnoresUnknownFlags(): void {
        $callable = function($params) {};
        $this->commands->add('run', $callable, ['name'], ['verbose']);
        $this->setArgv(['script.php', 'run', '-unknown', '-name', 'test']);

        $result = $this->commands->matchCurrent();

        $this->assertEquals('test', $result[1]['name']);
        $this->assertFalse($result[1]['verbose']);
    }
}
