<?php

use Dynart\Micro\Translation;
use Dynart\Micro\Config;

/**
 * @covers \Dynart\Micro\Translation
 */
final class TranslationTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Dynart\Micro\Config&\PHPUnit\Framework\MockObject\MockObject $config */
    private $config;

    public function mockConfigWithMultiLocale() {
        $this->config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->config->expects($this->any())
            ->method('get')
            ->will($this->returnValueMap([
                ['app.root_path', null, true, dirname(dirname(__FILE__))],
                [Translation::CONFIG_DEFAULT, Translation::DEFAULT_LOCALE, true, 'hu']
            ]));
        $this->config->expects($this->any())
            ->method('getCommaSeparatedValues')
            ->will($this->returnValue(['hu', 'en']));
    }

    public function testLocaleWhenMultiLocaleIsSetAndDefaultLocaleIsHu() {
        $this->mockConfigWithMultiLocale();
        $translation = new Translation($this->config);
        $this->assertEquals('hu', $translation->locale());
    }

    public function testGetWhenMultiLocaleIsSetAndLocaleIsHuAndTheTextHasVariable() {
        $this->mockConfigWithMultiLocale();
        $translation = new Translation($this->config);
        $translation->add('test', '~/translations');
        $this->assertEquals('Szia Joe!', $translation->get('test:welcome', ['name' => 'Joe']));
    }

    public function testGetWhenMultiLocaleIsSetAndLocaleIsEnAndTheTextHasVariable() {
        $this->mockConfigWithMultiLocale();
        $translation = new Translation($this->config);
        $translation->add('test', '~/translations');
        $translation->setLocale('en');
        $this->assertEquals('Hi Joe!', $translation->get('test:welcome', ['name' => 'Joe']));
    }

    public function testGetWhenTranslationNamespaceDoesntExist() {
        $this->mockConfigWithMultiLocale();
        $translation = new Translation($this->config);
        $this->assertEquals('#test:welcome#', $translation->get('test:welcome'));
    }

    public function testAllLocales() { // coverage
        $this->mockConfigWithMultiLocale();
        $translation = new Translation($this->config);
        $this->assertEquals(['hu', 'en'], $translation->allLocales());
    }

    public function testHasMultiLocale() {
        $this->mockConfigWithMultiLocale();
        $translation = new Translation($this->config);
        $this->assertTrue($translation->hasMultiLocales());
    }
}