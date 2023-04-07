<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Config;
use Dynart\Micro\View;

/**
 * @covers \Dynart\Micro\View
 * @uses \Dynart\Micro\Router
 * @uses \Dynart\Micro\Config
 */
final class ViewTest extends TestCase
{
    /** @var View */
    private $view;

    /** @var \Dynart\Micro\Router&\PHPUnit\Framework\MockObject\MockObject */
    private $router;

    protected function setUp(): void {

        /** @var \Dynart\Micro\Config&\PHPUnit\Framework\MockObject\MockObject $config */
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $config->method('get')
            ->will($this->returnCallback(function ($name, $default = null, $useCache = true) {
                if ($name == 'app.view_folders') {
                    return dirname(__FILE__).'/../views';
                } else {
                    return '';
                }
            }));

        $this->router = $this->getMockBuilder(Dynart\Micro\Router::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->view = new View($config, $this->router);
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



}