<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Response;

/**
 * @covers \Dynart\Micro\Response
 */
final class ResponseTest extends TestCase
{
    /** @var Response */
    private $response;

    protected function setUp(): void {
        $this->response = new Response();
        $this->response->setHeader('test', 'value');
    }

    public function testSetGetHeader() {
        $this->assertEquals('value', $this->response->header('test'));
        $this->assertEquals('default', $this->response->header('non_existing', 'default'));
    }

    public function testClearHeaders() {
        $this->response->clearHeaders();
        $this->assertNull($this->response->header('test'));
    }

    public function testSend() {
        ob_start();
        $this->response->clearHeaders(); // because of 'Cannot modify header information - headers already sent'
        $this->response->send('content');
        $content = ob_end_clean();
        $this->assertEquals('content', $content);
    }
}