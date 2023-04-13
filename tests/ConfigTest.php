<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Config;

/**
 * @covers \Dynart\Micro\Config
 */
final class ConfigTest extends TestCase {

    public function testLoadWhenNoEnvironmentVariablesWasSetShouldLoadProperTypesAndValues() {
        $config = new Config();
        $config->load(dirname(dirname(__FILE__)) . '/configs/config.ini');
        $this->assertEquals(11, $config->get('integer'));
        $this->assertEquals(12.5, $config->get('float'));
        $this->assertEquals('string', $config->get('string'));
        $this->assertTrue($config->get('bool.true'));
        $this->assertFalse($config->get('bool.false'));
        $this->assertEquals(['one', 'two'], $config->getArray('array'));
        $this->assertEquals('inside', $config->get('env.from.outside'));
    }

    public function testLoadWhenEnvironmentVariablesWasSet() {
        putenv("TEST_ENV=test_env");
        putenv("env.from.outside=outside");
        $config = new Config();
        $config->load(dirname(dirname(__FILE__)) . '/configs/config.ini');
        $this->assertEquals('TEST_ENV=test_env', $config->get('env.in.value'));
        $this->assertEquals('outside', $config->get('env.from.outside'));
    }

    public function testLoadSecondOverridesFirst() {
        $config = new Config();
        $config->load(dirname(dirname(__FILE__)) . '/configs/config.ini');
        $config->load(dirname(dirname(__FILE__)) . '/configs/config-extend.ini');
        $this->assertEquals(22, $config->get('integer'));
    }

    public function testCommaSeparatedValues() {
        $config = new Config();
        $config->load(dirname(dirname(__FILE__)) . '/configs/config.ini');
        $this->assertEquals(['1', '2', '3'], $config->getCommaSeparatedValues('comma.separated'));
    }

    public function testGetShouldUseCacheOnSecondCallInDefault() {
        putenv("env.from.outside=outside");
        $config = new Config();
        $this->assertEquals('outside', $config->get('env.from.outside'));
        putenv("env.from.outside=not_cached");
        $this->assertEquals('outside', $config->get('env.from.outside'));
    }

    public function testGetArrayShouldUseCacheOnSecondCallInDefault() {
        $config = new Config();
        $this->assertEquals(['a', 'b'], $config->getArray('env.array'));
        $this->assertTrue($config->isCached('env.array'));
        $this->assertEquals(['a', 'b'], $config->getArray('env.array'));
    }

    public function testGetFullPathShouldReturnARightPath() {
        $config = new Config();
        $config->load(dirname(dirname(__FILE__)) . '/configs/config.ini');
        $this->assertEquals('app_root_path/path', $config->getFullPath('~/path'));
    }

}