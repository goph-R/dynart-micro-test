<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Config;
use Dynart\Micro\View;
use Dynart\Micro\App;

/**
 * @covers \Dynart\Micro\View
 */
final class ViewTest extends TestCase
{
    /** @var View */
    private $view;

    protected function setUp(): void {
        $config = new Config();
        $config->load(dirname(dirname(__FILE__)).'/configs/view.config.ini');
        $this->view = new View($config);
    }

    public function testUseLayout() { // coverage
        $this->view->useLayout('test_layout');
        $this->assertEquals('test_layout', $this->view->layout());
    }

    public function testSetTheme() { // coverage
        $this->view->setTheme('test_theme');
        $this->assertEquals('test_theme', $this->view->theme());
    }

    public function testAddFolder() { // coverage
        $this->view->addFolder('test_namespace', 'test_folder');
        $this->assertEquals('test_folder', $this->view->folder('test_namespace'));
    }

    public function testSetGet() {
        $this->view->set('test_key', 'test_value');
        $this->assertEquals('test_value', $this->view->get('test_key'));
        $this->assertEquals('default', $this->view->get('non_existing', 'default'));
    }

    public function testAddScript() {
        $this->view->addScript('test_script.js', ['attribute1' => 'value1']);
        $scripts = $this->view->scripts();
        $this->assertArrayHasKey('test_script.js', $scripts);
        $this->assertArrayHasKey('attribute1', $scripts['test_script.js']);
        $this->assertEquals($scripts['test_script.js']['attribute1'], 'value1');
    }

    public function testAddStyle() {
        $this->view->addStyle('test_style.css', ['attribute1' => 'value1']);
        $scripts = $this->view->styles();
        $this->assertArrayHasKey('test_style.css', $scripts);
        $this->assertArrayHasKey('attribute1', $scripts['test_style.css']);
        $this->assertEquals($scripts['test_style.css']['attribute1'], 'value1');
    }

    public function testStartEndBlockShouldCreateBlockWithContent() {
        $testContent = "Test content";
        $this->view->startBlock('test_block');
        echo $testContent;
        $this->view->endBlock();
        $this->assertEquals($testContent, $this->view->block('test_block'));
    }

    public function testStartEndBlockTwiceShouldCreateThenAppendBlockContent() {
        $testContent = "Test content";
        $this->view->startBlock('test_block');
        echo $testContent;
        $this->view->endBlock();
        $this->view->startBlock('test_block');
        echo $testContent;
        $this->view->endBlock();
        $this->assertEquals($testContent.$testContent, $this->view->block('test_block'));
    }

    public function testFetchGivenNonExistingViewPathShouldThrowAppExcpetion() {
        $this->expectException(\Dynart\Micro\MicroException::class);
        $this->view->fetch('non_existing');
    }

    public function testFetchWhenVariablesSetAndRenderedShouldRenderTheRightValues() {
        $result = $this->view->fetch('variables', [
            'var1' => 'value1',
            'var2' => 'value2'
        ]);
        $this->assertEquals('value1,value2', $result);
    }

    public function testFetchWhenThemeSetIncludesAllFunctionsPhp() {
        $this->view->setTheme('~/views/theme');
        $this->view->fetch('empty');
        $this->assertTrue(defined('TEST_THEME_FUNCTIONS'));
        $this->assertTrue(defined('TEST_APP_FUNCTIONS'));
        $this->assertTrue(function_exists('base_url'));
    }

    public function testFetchAppFunctionsPhpCanOverwriteDefaultFunctions() {
        $this->view->fetch('empty');
        $this->assertEquals(base_url(), 'overwritten');
        $this->assertEquals(url(), 'overwritten');
        $this->assertEquals(route_url(), 'overwritten');
        $this->assertEquals(esc_html(), 'overwritten');
        $this->assertEquals(esc_attr(), 'overwritten');
        $this->assertEquals(esc_attrs(), 'overwritten');
        $this->assertEquals(tr(), 'overwritten');
    }

    public function testFetchTemplateWithLayoutShouldRenderWithLayout() {
        $content = $this->view->fetch('empty-with-layout');
        $this->assertEquals('layout', $content);
    }

    public function testFetchWhenThemeSetAndTemplateIsInTheThemeFolderShouldRenderTheThemeTemplate() {
        $this->view->setTheme('~/views/theme');
        $content = $this->view->fetch('empty');
        $this->assertEquals('overwritten', $content);
    }

    public function testFetchWhenNamespaceAddedAndUsedInTheViewPathShouldRenderThat() {
        $this->view->addFolder('namespace', '~/views/namespace');
        $content = $this->view->fetch('namespace:text');
        $this->assertEquals('text', $content);
    }

    public function testFetchWhenThemeSetAndNamespaceAddedAndTheTemplateExistsBothInNamespaceAndThemeShouldRenderTheme() {
        $this->view->addFolder('namespace', '~/views/namespace');
        $this->view->setTheme('~/views/theme');
        $content = $this->view->fetch('namespace:theme');
        $this->assertEquals('theme', $content);
    }

    public function testFetchWhenThePathContainsANonExistingNameSpaceShouldThrowMicroException() {
        $this->expectException(\Dynart\Micro\MicroException::class);
        $this->view->fetch('non_existing:non_existing');
    }
}