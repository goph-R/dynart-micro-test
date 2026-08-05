<?php
// `src/` is not on the autoloader - see MicroTest, which does the same
require_once dirname(dirname(__FILE__)).'/src/ResettableMicro.php';
require_once dirname(dirname(__FILE__)).'/src/StubConfig.php';

use PHPUnit\Framework\TestCase;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\Request;
use Dynart\Micro\Test\ResettableMicro;
use Dynart\Micro\Test\StubConfig;
use Dynart\Micro\UploadedFile;
use Dynart\Micro\AbstractApp;

final class RequestTestApp extends AbstractApp {
    public function process(): void {}
    public function init(): void {}
}

/**
 * @covers \Dynart\Micro\Request
 */
final class RequestTest extends TestCase {

    private Request $request;

    protected function setUp(): void {
        $_SERVER = [];
        $_REQUEST = [];
        $_COOKIE = [];
        $_FILES = [];
        $this->request = new Request();
        $this->request->setHeader('test_header', 'test_value');
        $this->request->setBody('{"test_key": "test_value"}');
    }

    protected function tearDown(): void {
        ResettableMicro::reset(); // the trusted proxies are read from the container
    }

    public function testGetReturnsValueFromGlobalRequestArray(): void {
        $_REQUEST['request_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->get('request_test'));
    }

    public function testGetReturnsDefaultValueWhenKeyNotExistsInTheGlobalRequestArray(): void {
        $this->assertEquals('default_value', $this->request->get('non_existing_key', 'default_value'));
    }

    public function testCookieReturnsValueFromGlobalCookieArray(): void {
        $_COOKIE['request_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->cookie('request_test'));
    }

    public function testCookieReturnsDefaultValueWhenKeyNotExistsInTheGlobalCookieArray(): void {
        $this->assertEquals('default_value', $this->request->cookie('non_existing_key', 'default_value'));
    }

    public function testServerReturnsValueFromGlobalServerArray(): void {
        $_SERVER['server_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->server('server_test'));
    }

    public function testServerReturnsDefaultValueWhenKeyNotExistsInTheGlobalServerArray(): void {
        $this->assertEquals('default_value', $this->request->server('non_existing_key', 'default_value'));
    }

    public function testHttpMethodReturnsTheValueFromGlobalServerArray(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertEquals('GET', $this->request->httpMethod());
    }

    public function testIpGivenRemoteAddrShouldReturnWithIt(): void {
        $_SERVER['REMOTE_ADDR'] = 'remote';
        $this->assertEquals('remote', $this->request->ip());
    }

    public function testIpGivenNoIpShouldReturnNull(): void {
        $this->assertNull($this->request->ip());
    }

    /**
     * A header is whatever the client typed
     *
     * `ip()` used to return `X-Forwarded-For` whenever it was present, so anything counting or
     * blocking by address could be handed a different one on every request - or somebody else's,
     * which is worse, because then the count belongs to them.
     */
    public function testIpIgnoresForwardedHeadersFromSomebodyWhoIsNotATrustedProxy(): void {
        $_SERVER['REMOTE_ADDR'] = 'remote';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'spoofed';
        $_SERVER['HTTP_CLIENT_IP'] = 'spoofed-too';
        $this->assertEquals('remote', $this->request->ip());
    }

    /**
     * With nothing configured there is no proxy, so a forwarded header is nobody's word
     */
    public function testIpIgnoresForwardedHeadersWhenNoProxyIsTrusted(): void {
        $this->withTrustedProxies('');
        $_SERVER['REMOTE_ADDR'] = 'proxy';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'client';
        $this->assertEquals('proxy', $this->request->ip());
    }

    public function testIpBelievesATrustedProxy(): void {
        $this->withTrustedProxies('proxy');
        $_SERVER['REMOTE_ADDR'] = 'proxy';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'client';
        $this->assertEquals('client', $this->request->ip());
    }

    /**
     * The chain reads client first, each hop appending the one it heard from. Everything left of
     * the last address a machine we trust actually saw was written by somebody we cannot check.
     */
    public function testIpTakesTheLastAddressATrustedProxyActuallySaw(): void {
        $this->withTrustedProxies('proxy, inner');
        $_SERVER['REMOTE_ADDR'] = 'proxy';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'forged, client, inner';
        $this->assertEquals('client', $this->request->ip());
    }

    public function testIpFallsBackToTheProxyWhenItForwardedNothing(): void {
        $this->withTrustedProxies('proxy');
        $_SERVER['REMOTE_ADDR'] = 'proxy';
        $this->assertEquals('proxy', $this->request->ip());
    }

    private function withTrustedProxies(string $value): void {
        $config = new StubConfig([Request::CONFIG_TRUSTED_PROXIES => $value]);
        ResettableMicro::reset();
        ResettableMicro::set(ConfigInterface::class, $config);
    }

    public function testHeaderShouldReturnHeaderValue(): void {
        $this->assertEquals($this->request->header('test_header'), 'test_value');
    }

    public function testHeaderShouldReturnDefaultValueWhenKeyNotExistsInHeaders(): void {
        $this->assertEquals($this->request->header('non_existing', 'default_value'), 'default_value');
    }

    public function testBodyAsJsonShouldReturnAnAssociativeArrayWhenBodyContainsJsonString(): void {
        $array = $this->request->bodyAsJson();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('test_key', $array);
        $this->assertContains('test_value', $array);
    }

    public function testBodyAsJsonGivenTheBodyContainsInvalidJsonShouldThrowMicroException(): void {
        $this->expectException(\Dynart\Micro\MicroException::class);
        $this->request->setBody('{"invalid_json":');
        $this->request->bodyAsJson();
    }

    public function testBodyAsJsonGivenTheBodyIsEmptyShouldReturnNull(): void {
        $this->request->setBody('');
        $this->assertNull($this->request->bodyAsJson());
    }

    public function testUploadedFileGivenOneUploadedFileShouldReturnWithOneUploadedFileClass(): void {
        $_FILES = [
            'test_file' => [
                'name' => 'test.jpg',
                'size' => 123,
                'tmp_name' => '/tmp/test.jpg',
                'error' => UPLOAD_ERR_OK,
                'type' => 'image/jpeg'
            ]
        ];
        $request = new Request(); // have to create the uploaded files in the constructor
        $uploadedFile = $request->uploadedFile('test_file');
        $this->assertEquals('test.jpg', $uploadedFile->name());
        $this->assertEquals(123, $uploadedFile->size());
        $this->assertEquals('/tmp/test.jpg', $uploadedFile->tempPath());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->error());
        $this->assertEquals('image/jpeg', $uploadedFile->type());
    }

    public function testUploadedFileGivenTwoUploadedFileShouldReturnWithAnUploadedFileArrayWithTwoElements(): void {
        $_FILES = [
            'test_file' => [
                'name' => ['test1.jpg', 'test2.jpg'],
                'size' => [123, 456],
                'tmp_name' => ['/tmp/test1.jpg', '/tmp/test2.jpg'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'type' => ['image/jpeg', 'image/jpeg']
            ]
        ];
        /** @var UploadedFile[] $uploadedFile */
        $request = new Request(); // have to create the uploaded files in the constructor
        $uploadedFile = $request->uploadedFile('test_file');

        $this->assertIsArray($uploadedFile);
        $this->assertEquals(2, count($uploadedFile));

        $this->assertEquals('test1.jpg', $uploadedFile[0]->name());
        $this->assertEquals(123, $uploadedFile[0]->size());
        $this->assertEquals('/tmp/test1.jpg', $uploadedFile[0]->tempPath());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile[0]->error());
        $this->assertEquals('image/jpeg', $uploadedFile[0]->type());

        $this->assertEquals('test2.jpg', $uploadedFile[1]->name());
        $this->assertEquals(456, $uploadedFile[1]->size());
        $this->assertEquals('/tmp/test2.jpg', $uploadedFile[1]->tempPath());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile[1]->error());
        $this->assertEquals('image/jpeg', $uploadedFile[1]->type());
    }
}
