<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\UploadedFile;

/**
 * @covers \Dynart\Micro\UploadedFile
 */
final class UploadedFileTest extends TestCase {
    public function testUploadedFile() {
        $uploadedFile = new UploadedFile('name', 'tempPath', UPLOAD_ERR_OK, 'image/jpeg', 123);
        $this->assertEquals('name', $uploadedFile->name());
        $this->assertEquals('tempPath', $uploadedFile->tempPath());
        $this->assertEquals(UPLOAD_ERR_OK, $uploadedFile->error());
        $this->assertEquals('image/jpeg', $uploadedFile->type());
        $this->assertEquals(123, $uploadedFile->size());
        $this->assertEquals(false, $uploadedFile->uploaded());
        $this->assertEquals(false, $uploadedFile->moveTo('/tmp')); // sets tempPath to ''
        $this->assertEquals('', $uploadedFile->tempPath());
    }
}