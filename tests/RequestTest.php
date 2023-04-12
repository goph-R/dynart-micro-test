<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Request;
use Dynart\Micro\UploadedFile;
use Dynart\Micro\App;

final class RequestTestApp extends App {
    public function process() {}
    public function init() {}
}

/**
 * @covers \Dynart\Micro\Request
 */
final class RequestTest extends TestCase {

    /** @var Request */
    private $request;

    protected function setUp(): void {
        $_SERVER = [];
        $_REQUEST = [];
        $_COOKIE = [];
        $_FILES = [];
        $this->request = new Request();
        $this->request->setHeader('test_header', 'test_value');
        $this->request->setBody('{"test_key": "test_value"}');
    }

    public function testGetReturnsValueFromGlobalRequestArray() {
        $_REQUEST['request_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->get('request_test'));
    }

    public function testGetReturnsDefaultValueWhenKeyNotExistsInTheGlobalRequestArray() {
        $this->assertEquals('default_value', $this->request->get('non_existing_key', 'default_value'));
    }

    public function testCookieReturnsValueFromGlobalCookieArray() {
        $_COOKIE['request_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->cookie('request_test'));
    }

    public function testCookieReturnsDefaultValueWhenKeyNotExistsInTheGlobalCookieArray() {
        $this->assertEquals('default_value', $this->request->cookie('non_existing_key', 'default_value'));
    }

    public function testServerReturnsValueFromGlobalServerArray() {
        $_SERVER['server_test'] = 'test_value';
        $this->assertEquals('test_value', $this->request->server('server_test'));
    }

    public function testServerReturnsDefaultValueWhenKeyNotExistsInTheGlobalServerArray() {
        $this->assertEquals('default_value', $this->request->server('non_existing_key', 'default_value'));
    }

    public function testHttpMethodReturnsTheValueFromGlobalServerArray() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertEquals('GET', $this->request->httpMethod());
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

    public function testHeaderShouldReturnHeaderValue() {
        $this->assertEquals($this->request->header('test_header'), 'test_value');
    }

    public function testHeaderShouldReturnDefaultValueWhenKeyNotExistsInHeaders() {
        $this->assertEquals($this->request->header('non_existing', 'default_value'), 'default_value');
    }

    public function testBodyAsJsonShouldReturnAnAssociativeArrayWhenBodyContainsJsonString() {
        $array = $this->request->bodyAsJson();
        $this->assertIsArray($array);
        $this->assertArrayHasKey('test_key', $array);
        $this->assertContains('test_value', $array);
    }

    public function testBodyAsJsonGivenTheBodyContainsInvalidJsonShouldThrowAppException() {
        $this->expectException(\Dynart\Micro\AppException::class);
        $this->request->setBody('{"invalid_json":');
        $this->request->bodyAsJson();
    }

    public function testBodyAsJsonGivenTheBodyIsEmptyShouldReturnNull() {
        $this->request->setBody('');
        $this->assertNull($this->request->bodyAsJson());
    }

    public function testUploadedFileGivenOneUploadedFileShouldReturnWithOneUploadedFileClass() {
        $this->createTestApp();
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

    public function testUploadedFileGivenTwoUploadedFileShouldReturnWithAnUploadedFileArrayWithTwoElements() {
        $this->createTestApp();
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

    private function createTestApp() { // needed for a working `create` method
        if (App::instance()) { return; }
        $app = new RequestTestApp();
        App::run($app);
    }
}
