<?php

use Dynart\Micro\Translation;
use Dynart\Micro\Config;
use Dynart\Micro\AbstractApp;

/**
 * @covers \Dynart\Micro\Translation
 */
final class TranslationTest extends \PHPUnit\Framework\TestCase
{
    private Config $config;

    private function loadConfig(): void {
        $this->config = new Config();
        $this->config->load(dirname(dirname(__FILE__)).'/configs/translation.ini');
    }

    public function testLocaleWhenMultiLocaleIsSetAndDefaultLocaleIsHu(): void {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertEquals('hu', $translation->locale());
    }

    public function testGetWhenMultiLocaleIsSetAndLocaleIsDefaultAndTheTextHasVariable(): void {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $translation->add('test', '~/translations');
        $this->assertEquals('Szia Joe!', $translation->get('test:welcome', ['name' => 'Joe']));
    }

    public function testGetWhenMultiLocaleIsSetAndLocaleIsEnAndTheTextHasVariable(): void {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $translation->add('test', '~/translations');
        $translation->setLocale('en');
        $this->assertEquals('Hi Joe!', $translation->get('test:welcome', ['name' => 'Joe']));
    }

    public function testGetWhenTranslationNamespaceDoesntExist(): void {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertEquals('#test:welcome#', $translation->get('test:welcome'));
    }

    public function testAllLocales(): void { // coverage
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertEquals(['hu', 'en'], $translation->allLocales());
    }

    public function testHasMultiLocale(): void {
        $this->loadConfig();
        $translation = new Translation($this->config);
        $this->assertTrue($translation->hasMultiLocales());
    }
}