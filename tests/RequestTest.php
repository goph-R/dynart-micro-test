<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Request;

final class RequestTest extends TestCase {

    /** @var Request */
    private $request;

    protected function setUp(): void {
        $_SERVER = [];
        $_REQUEST = [];
        $this->request = new Request();
    }

    public function testGetReturnsValueFromGlobalRequestArray() {
        $_REQUEST['request_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->get('request_test'));
    }

    public function testGetReturnsDefaultValueWhenKeyNotExistsInTheGlobalRequestArray() {
        $this->assertEquals('default_value', $this->request->get('non_existing_key', 'default_value'));
    }

    public function testServerReturnsValueFromGlobalServerArray() {
        $_SERVER['server_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->server('server_test'));
    }

    public function testServerReturnsDefaultValueWhenKeyNotExistsInTheGlobalServerArray() {
        $this->assertEquals('default_value', $this->request->server('non_existing_key', 'default_value'));
    }

    public function testMethodReturnsTheValueFromGlobalServerArray() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertEquals('GET', $this->request->method());
    }

    public function testIpGivenRemoteAddrShouldReturnWithIt() {
        $_SERVER['REMOTE_ADDR'] = 'remote';
        $this->assertEquals('remote', $this->request->ip());
    }

    public function testIpGivenHttpXForwardedForShouldReturnWithIt() {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'forwarded';
        $this->assertEquals('forwarded', $this->request->ip());
    }

    public function testIpGivenHttpClientIpShouldReturnWithIt() {
        $_SERVER['HTTP_CLIENT_IP'] = 'client';
        $this->assertEquals('client', $this->request->ip());
    }

    public function testIpGivenNoIpShouldReturnNull() {
        $this->assertNull($this->request->ip());
    }
}



