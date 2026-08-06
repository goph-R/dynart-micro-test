<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\FormWidgets;

/**
 * Which template renders a field type
 *
 * This used to be an `if/elseif` chain inside a template, and the only way to add a type was to
 * replace the whole chain by pointing `Form::VIEW_INPUT` somewhere else. That is a mechanism with
 * room for exactly one contributor: dpress spent it, and nothing after dpress could add a type at
 * all. A registry has room for everybody.
 *
 * @covers \Dynart\Micro\FormWidgets
 */
final class FormWidgetsTest extends TestCase {

    private FormWidgets $widgets;

    protected function setUp(): void {
        $this->widgets = new FormWidgets();
    }

    // --- what ships ---

    public function testEveryBuiltInTypeIsRegistered(): void {
        foreach (FormWidgets::BUILT_IN as $type) {
            $this->assertTrue($this->widgets->has($type), "'$type' is not registered");
        }
    }

    /**
     * The framework registers its own types through the same call an application uses. A
     * mechanism the framework does not eat is a mechanism nobody has tested.
     */
    public function testTheBuiltInTypesResolveIntoTheFrameworksOwnNamespace(): void {
        $this->assertSame(FormWidgets::VIEW_PREFIX.'select', $this->widgets->view('select'));
    }

    /**
     * Every registered template has to be a file that exists, or the type is registered and still
     * renders nothing - which is the failure this whole class was built to end
     */
    public function testEveryBuiltInTypeHasATemplateOnDisk(): void {
        $views = dirname(__DIR__).'/vendor/dynart/micro/views/widget';
        foreach (FormWidgets::BUILT_IN as $type) {
            $this->assertFileExists($views.'/'.$type.'.phtml');
        }
    }

    public function testTheOldSingleTemplateIsGone(): void {
        $this->assertFileDoesNotExist(
            dirname(__DIR__).'/vendor/dynart/micro/views/form-input.phtml',
            'the if/elseif chain is still there, so there are two mechanisms for one job'
        );
    }

    // --- what an application or a plugin does with it ---

    public function testANewTypeCanBeAdded(): void {
        $this->widgets->add('markdown', 'dpress:widget/markdown');
        $this->assertSame('dpress:widget/markdown', $this->widgets->view('markdown'));
    }

    /**
     * Replacing a built in is as legitimate as adding one: an application wanting its own
     * `select` should not have to fork the other six to get it
     */
    public function testABuiltInTypeCanBeReplaced(): void {
        $this->widgets->add('select', 'myapp:widget/fancy-select');
        $this->assertSame('myapp:widget/fancy-select', $this->widgets->view('select'));
    }

    public function testAnUnknownTypeHasNoView(): void {
        $this->assertFalse($this->widgets->has('colour-picker'));
        $this->assertNull($this->widgets->view('colour-picker'));
    }

    /**
     * `types()` exists for the diagnostic: when a widget does not render, the message says what
     * *is* registered, which is usually enough to spot the typo
     */
    public function testTypesListsEverythingSorted(): void {
        $this->widgets->add('markdown', 'dpress:widget/markdown');
        $types = $this->widgets->types();
        $sorted = $types;
        sort($sorted);
        $this->assertSame($sorted, $types);
        $this->assertContains('markdown', $types);
        $this->assertCount(count(FormWidgets::BUILT_IN) + 1, $types);
    }
}
