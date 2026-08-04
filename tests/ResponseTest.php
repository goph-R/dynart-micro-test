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
        $content = ob_get_clean(); // ob_end_clean() returns a bool, not the buffer
        $this->assertEquals('content', $content);
    }

    // --- Cookies ---

    public function testSetGetCookie(): void {
        $this->response->setCookie('token', 'abc123');
        $this->assertEquals('abc123', $this->response->cookie('token'));
        $this->assertEquals('default', $this->response->cookie('non_existing', 'default'));
    }

    /**
     * The common reason to set a cookie from the server is to hold something a script has no
     * business reading, so these two are on unless the caller says otherwise.
     */
    public function testCookieDefaultsAreHttpOnlyAndSameSite(): void {
        $this->response->setCookie('token', 'abc123');
        $options = $this->response->cookies()['token'][1];
        $this->assertTrue($options['httponly']);
        $this->assertEquals('Lax', $options['samesite']);
        $this->assertEquals('/', $options['path']);
    }

    /**
     * `secure` stays off by default: turning it on would make cookies silently vanish on a plain
     * HTTP development site, which is a much harder failure to diagnose than a missing flag.
     */
    public function testSecureIsOffByDefault(): void {
        $this->response->setCookie('token', 'abc123');
        $this->assertFalse($this->response->cookies()['token'][1]['secure']);
    }

    public function testCookieOptionsAreMergedIntoTheDefaults(): void {
        $this->response->setCookie('token', 'abc123', ['secure' => true, 'samesite' => 'Strict']);
        $options = $this->response->cookies()['token'][1];
        $this->assertTrue($options['secure']);
        $this->assertEquals('Strict', $options['samesite']);
        $this->assertTrue($options['httponly']); // untouched default
    }

    public function testClearCookieExpiresIt(): void {
        $this->response->setCookie('token', 'abc123');
        $this->response->clearCookie('token');
        $this->assertEquals('', $this->response->cookie('token'));
        $this->assertEquals(1, $this->response->cookies()['token'][1]['expires']);
    }

    public function testClearCookieKeepsTheGivenPath(): void {
        $this->response->clearCookie('token', ['path' => '/admin']);
        $this->assertEquals('/admin', $this->response->cookies()['token'][1]['path']);
    }

    public function testClearCookies(): void {
        $this->response->setCookie('token', 'abc123');
        $this->response->clearCookies();
        $this->assertSame([], $this->response->cookies());
        $this->assertNull($this->response->cookie('token'));
    }

    public function testSendWithCookiesStillOutputsTheContent(): void {
        ob_start();
        $this->response->setCookie('token', 'abc123');
        $this->response->send('content');
        $content = ob_get_clean();
        $this->assertEquals('content', $content);
    }
}