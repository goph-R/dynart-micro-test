<?php

require_once dirname(__FILE__, 2) .'/src/ResettableMicro.php';

use PHPUnit\Framework\TestCase;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Request;
use Dynart\Micro\Middleware\JwtCookieReader;
use Dynart\Micro\Test\ResettableMicro;

/**
 * @covers \Dynart\Micro\Middleware\JwtCookieReader
 */
final class JwtCookieReaderTest extends TestCase {

    private Request $request;

    protected function setUp(): void {
        ResettableMicro::reset();
        $_COOKIE = [];
        $this->request = new Request();
    }

    protected function tearDown(): void {
        $_COOKIE = [];
    }

    /**
     * A config that answers the cookie name and passes everything else through to the default
     */
    private function config(?string $cookieName = null): ConfigInterface {
        $config = $this->createMock(ConfigInterface::class);
        $config->method('get')->willReturnCallback(
            function(string $name, mixed $default = null) use ($cookieName) {
                if ($name === JwtCookieReader::CONFIG_COOKIE_NAME && $cookieName !== null) {
                    return $cookieName;
                }
                return $default;
            }
        );
        return $config;
    }

    private function reader(?string $cookieName = null): JwtCookieReader {
        return new JwtCookieReader($this->request, $this->config($cookieName));
    }

    public function testDefaultCookieName(): void {
        $this->assertEquals(JwtCookieReader::DEFAULT_COOKIE_NAME, $this->reader()->cookieName());
    }

    public function testCookieNameFromConfig(): void {
        $this->assertEquals('dpress_token', $this->reader('dpress_token')->cookieName());
    }

    public function testLiftsTheCookieIntoTheAuthorizationHeader(): void {
        $_COOKIE['token'] = 'abc123';
        $this->reader()->run();
        $this->assertEquals('Bearer abc123', $this->request->header('Authorization'));
    }

    public function testUsesTheConfiguredCookieName(): void {
        $_COOKIE['dpress_token'] = 'abc123';
        $this->reader('dpress_token')->run();
        $this->assertEquals('Bearer abc123', $this->request->header('Authorization'));
    }

    public function testIgnoresACookieWithADifferentName(): void {
        $_COOKIE['token'] = 'abc123';
        $this->reader('dpress_token')->run();
        $this->assertNull($this->request->header('Authorization'));
    }

    public function testDoesNothingWithoutTheCookie(): void {
        $this->reader()->run();
        $this->assertNull($this->request->header('Authorization'));
    }

    public function testDoesNothingForAnEmptyCookie(): void {
        $_COOKIE['token'] = '';
        $this->reader()->run();
        $this->assertNull($this->request->header('Authorization'));
    }

    /**
     * An API client sending a real header must never be overridden by a stale cookie the browser
     * happened to send along with the request.
     */
    public function testAnExistingAuthorizationHeaderWins(): void {
        $this->request->setHeader('Authorization', 'Bearer from-header');
        $_COOKIE['token'] = 'from-cookie';
        $this->reader()->run();
        $this->assertEquals('Bearer from-header', $this->request->header('Authorization'));
    }
}
