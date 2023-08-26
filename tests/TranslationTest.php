<?php

use Dynart\Micro\Translation;
use Dynart\Micro\Config;
use Dynart\Micro\App;

/**
 * @covers \Dynart\Micro\Translation
 */
final class TranslationTest extends \PHPUnit\Framework\TestCase
{
    /** @var \Dynart\Micro\Config&\PHPUnit\Framework\MockObject\MockObject $config */
    private $config;

    private function loadConfig() {
        $this->config = new Config();
        $this->config->load(dirname(dirname(__FILE__)).'/configs/translation.ini');
    }

    public function testLocaleWhenMultiLocaleIsSetAndDefaultLocaleIsHu() {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertEquals('hu', $translation->locale());
    }

    public function testGetWhenMultiLocaleIsSetAndLocaleIsDefaultAndTheTextHasVariable() {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $translation->add('test', '~/translations');
        $this->assertEquals('Szia Joe!', $translation->get('test:welcome', ['name' => 'Joe']));
    }

    public function testGetWhenMultiLocaleIsSetAndLocaleIsEnAndTheTextHasVariable() {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $translation->add('test', '~/translations');
        $translation->setLocale('en');
        $this->assertEquals('Hi Joe!', $translation->get('test:welcome', ['name' => 'Joe']));
    }

    public function testGetWhenTranslationNamespaceDoesntExist() {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertEquals('#test:welcome#', $translation->get('test:welcome'));
    }

    public function testAllLocales() { // coverage
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertEquals(['hu', 'en'], $translation->allLocales());
    }

    public function testHasMultiLocale() {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertTrue($translation->hasMultiLocales());
    }
}