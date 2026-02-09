<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Response;

/**
 * @covers \Dynart\Micro\Response
 */
final class ResponseTest extends TestCase
{
    private Response $response;

    protected function setUp(): void {
        $this->response = new Response();
    }

    public function testSetGetHeader(): void {
        $this->response->setHeader('test', 'value');
        $this->assertEquals('value', $this->response->header('test'));
        $this->assertEquals('default', $this->response->header('non_existing', 'default'));
    }

    public function testClearHeaders(): void {
        $this->response->setHeader('test', 'value');
        $this->response->clearHeaders();
        $this->assertNull($this->response->header('test'));
    }

    public function testSend(): void {
        ob_start();
        $this->response->setHeader('x-test-header', 'test-value');
        $this->response->send('content');
        $content = ob_end_clean();
        $this->assertEquals('content', $content);
    }
}