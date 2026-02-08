<?php

use PHPUnit\Framework\TestCase;

use Dynart\Micro\CliOutput;

/**
 * @covers \Dynart\Micro\CliOutput
 */
final class CliOutputTest extends TestCase
{
    private CliOutput $output;

    protected function setUp(): void {
        $this->output = new CliOutput();
    }

    public function testWriteWithoutColor() {
        $this->output->setUseColor(false);
        ob_start();
        $this->output->write('hello');
        $content = ob_get_clean();
        $this->assertEquals('hello', $content);
    }

    public function testWriteWithColorDisabledDoesNotOutputAnsiCodes() {
        $this->output->setUseColor(false);
        $this->output->setColor(CliOutput::RED);
        ob_start();
        $this->output->write('hello');
        $content = ob_get_clean();
        $this->assertEquals('hello', $content);
    }

    public function testWriteWithForegroundColor() {
        $this->output->setColor(CliOutput::RED);
        ob_start();
        $this->output->write('error');
        $content = ob_get_clean();
        $this->assertStringContainsString("\033[91m", $content);
        $this->assertStringContainsString('error', $content);
        $this->assertStringContainsString(CliOutput::COLOR_OFF, $content);
    }

    public function testWriteWithBackgroundColor() {
        $this->output->setColor(null, CliOutput::DARK_BLUE);
        ob_start();
        $this->output->write('text');
        $content = ob_get_clean();
        $this->assertStringContainsString("\033[44m", $content);
        $this->assertStringContainsString('text', $content);
        $this->assertStringContainsString(CliOutput::COLOR_OFF, $content);
    }

    public function testWriteWithBothColors() {
        $this->output->setColor(CliOutput::WHITE, CliOutput::DARK_RED);
        ob_start();
        $this->output->write('alert');
        $content = ob_get_clean();
        $this->assertStringContainsString("\033[97m", $content);
        $this->assertStringContainsString("\033[41m", $content);
        $this->assertStringContainsString('alert', $content);
        $this->assertStringContainsString(CliOutput::COLOR_OFF, $content);
    }

    public function testWriteWithNoColorSetDoesNotOutputAnsiCodes() {
        ob_start();
        $this->output->write('plain');
        $content = ob_get_clean();
        $this->assertEquals('plain', $content);
    }

    public function testSetColorResetsWithNonIntValue() {
        $this->output->setColor(CliOutput::RED);
        $this->output->setColor(null);
        ob_start();
        $this->output->write('text');
        $content = ob_get_clean();
        $this->assertEquals('text', $content);
    }

    public function testWriteLine() {
        $this->output->setUseColor(false);
        ob_start();
        $this->output->writeLine('line');
        $content = ob_get_clean();
        $this->assertEquals("line\n", $content);
    }

    public function testDarkColorCodes() {
        $this->output->setColor(CliOutput::BLACK);
        ob_start();
        $this->output->write('x');
        $content = ob_get_clean();
        $this->assertStringContainsString("\033[30m", $content);
    }

    public function testBrightColorCodes() {
        $this->output->setColor(CliOutput::DARK_GRAY);
        ob_start();
        $this->output->write('x');
        $content = ob_get_clean();
        $this->assertStringContainsString("\033[90m", $content);
    }

    public function testDarkBackgroundColorCodes() {
        $this->output->setColor(null, CliOutput::BLACK);
        ob_start();
        $this->output->write('x');
        $content = ob_get_clean();
        $this->assertStringContainsString("\033[40m", $content);
    }

    public function testBrightBackgroundColorCodes() {
        $this->output->setColor(null, CliOutput::WHITE);
        ob_start();
        $this->output->write('x');
        $content = ob_get_clean();
        $this->assertStringContainsString("\033[107m", $content);
    }
}
