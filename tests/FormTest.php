<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Form;
use Dynart\Micro\Request;
use Dynart\Micro\Session;
use Dynart\Micro\Validator;

class AlwaysFailsValidator extends Validator {
    public function validate($value) {
        $this->message = 'Validation failed.';
        return false;
    }
}

class AlwaysPassesValidator extends Validator {
    public function validate($value) {
        return true;
    }
}

/**
 * @covers \Dynart\Micro\Form
 */
final class FormTest extends TestCase {

    /** @var Session */
    private $session;

    /** @var Form */
    private $form;

    protected function setUp(): void {
        $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->session = new Session();
        $this->form = new Form(new Request(), $this->session);
    }

    protected function tearDown(): void {
        $_REQUEST = [];
    }

    // --- CSRF ---

    public function testGenerateCsrfAddsCsrfFieldAndSetsSession() {
        $this->form->generateCsrf();
        $this->assertArrayHasKey($this->form->csrfName(), $this->form->fields());
        $this->assertEquals(
            $this->session->get($this->form->csrfSessionName()),
            $this->form->value($this->form->csrfName())
        );
    }

    public function testGenerateCsrfDoesNothingWhenCsrfDisabled() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->generateCsrf();
        $this->assertArrayNotHasKey('_csrf', $form->fields());
    }

    public function testCsrfSessionName() {
        $this->assertEquals('form.form.csrf', $this->form->csrfSessionName());
    }

    public function testCsrfSessionNameWithCustomFormName() {
        $form = new Form(new Request(), $this->session, 'login');
        $this->assertEquals('form.login.csrf', $form->csrfSessionName());
    }

    public function testCsrfName() {
        $this->assertEquals('_csrf', $this->form->csrfName());
    }

    public function testValidateCsrfReturnsTrueWhenSessionMatchesValue() {
        $this->form->generateCsrf();
        $this->assertTrue($this->form->validateCsrf());
    }

    public function testValidateCsrfReturnsFalseWhenMismatch() {
        $this->form->generateCsrf();
        $this->form->setValues(['_csrf' => 'wrong-token']);
        $this->assertFalse($this->form->validateCsrf());
    }

    public function testValidateCsrfReturnsTrueWhenCsrfDisabled() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $this->assertTrue($form->validateCsrf());
    }

    // --- Name & Fields ---

    public function testName() {
        $this->assertEquals('form', $this->form->name());
    }

    public function testNameWithCustomName() {
        $form = new Form(new Request(), $this->session, 'contact');
        $this->assertEquals('contact', $form->name());
    }

    public function testAddFieldsMakesThemRequired() {
        $this->form->addFields(['email' => ['type' => 'text']]);
        $this->assertTrue($this->form->required('email'));
    }

    public function testAddFieldsNotRequired() {
        $this->form->addFields(['notes' => ['type' => 'textarea']], false);
        $this->assertFalse($this->form->required('notes'));
    }

    public function testFieldsReturnsAddedFields() {
        $fields = ['name' => ['type' => 'text'], 'email' => ['type' => 'email']];
        $this->form->addFields($fields);
        $this->assertEquals($fields, $this->form->fields());
    }

    public function testSetRequiredTrue() {
        $this->form->addFields(['name' => ['type' => 'text']], false);
        $this->assertFalse($this->form->required('name'));
        $this->form->setRequired('name', true);
        $this->assertTrue($this->form->required('name'));
    }

    public function testSetRequiredFalse() {
        $this->form->addFields(['name' => ['type' => 'text']]);
        $this->assertTrue($this->form->required('name'));
        $this->form->setRequired('name', false);
        $this->assertFalse($this->form->required('name'));
    }

    public function testSetRequiredTrueDoesNotDuplicate() {
        $this->form->addFields(['name' => ['type' => 'text']]);
        $this->form->setRequired('name', true);
        $this->assertTrue($this->form->required('name'));
    }

    // --- Values ---

    public function testSetValuesAndValue() {
        $this->form->setValues(['name' => 'Joe']);
        $this->assertEquals('Joe', $this->form->value('name'));
    }

    public function testValueReturnsNullForNonexistentField() {
        $this->assertNull($this->form->value('missing'));
    }

    public function testValueWithEscape() {
        $this->form->setValues(['name' => '<script>alert("xss")</script>']);
        $escaped = $this->form->value('name', true);
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $escaped);
    }

    public function testValues() {
        $values = ['name' => 'Joe', 'email' => 'joe@test.com'];
        $this->form->setValues($values);
        $this->assertEquals($values, $this->form->values());
    }

    public function testAddValues() {
        $this->form->setValues(['name' => 'Joe']);
        $this->form->addValues(['email' => 'joe@test.com']);
        $this->assertEquals(['name' => 'Joe', 'email' => 'joe@test.com'], $this->form->values());
    }

    // --- Binding ---

    public function testBindWithFormName() {
        $_REQUEST['form'] = ['name' => 'Joe', 'email' => 'joe@test.com'];
        $form = new Form(new Request(), $this->session);
        $form->addFields(['name' => ['type' => 'text'], 'email' => ['type' => 'email']]);
        $form->bind();
        $this->assertEquals('Joe', $form->value('name'));
        $this->assertEquals('joe@test.com', $form->value('email'));
    }

    public function testBindWithoutFormName() {
        $_REQUEST['name'] = 'Joe';
        $_REQUEST['email'] = 'joe@test.com';
        $form = new Form(new Request(), $this->session, '', false);
        $form->addFields(['name' => ['type' => 'text'], 'email' => ['type' => 'email']]);
        $form->bind();
        $this->assertEquals('Joe', $form->value('name'));
        $this->assertEquals('joe@test.com', $form->value('email'));
    }

    // --- Validation ---

    public function testValidateReturnsTrueWhenAllRequiredFieldsFilled() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->setValues(['name' => 'Joe']);
        $this->assertTrue($form->validate());
    }

    public function testValidateReturnsFalseWhenRequiredFieldEmpty() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->setValues(['name' => '']);
        $this->assertFalse($form->validate());
        $this->assertEquals('Required.', $form->error('name'));
    }

    public function testValidateRunsValidators() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['email' => ['type' => 'email']]);
        $form->setValues(['email' => 'invalid']);
        $form->addValidator('email', new AlwaysFailsValidator());
        $this->assertFalse($form->validate());
        $this->assertEquals('Validation failed.', $form->error('email'));
    }

    public function testValidateSkipsValidatorsWhenFieldAlreadyHasError() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['email' => ['type' => 'email']]);
        $form->setValues(['email' => '']);
        $form->addValidator('email', new AlwaysFailsValidator());
        $form->validate();
        // Required error takes precedence
        $this->assertEquals('Required.', $form->error('email'));
    }

    public function testValidateSkipsValidatorsForOptionalEmptyField() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['notes' => ['type' => 'textarea']], false);
        $form->setValues(['notes' => '']);
        $form->addValidator('notes', new AlwaysFailsValidator());
        $this->assertTrue($form->validate());
    }

    public function testValidateStopsAtFirstFailedValidatorForField() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['email' => ['type' => 'email']]);
        $form->setValues(['email' => 'test']);
        $validator1 = new AlwaysFailsValidator();
        $validator2 = new AlwaysPassesValidator();
        $form->addValidator('email', $validator1);
        $form->addValidator('email', $validator2);
        $form->validate();
        $this->assertEquals('Validation failed.', $form->error('email'));
    }

    public function testValidateCsrfFailureReturnsInvalid() {
        $this->form->generateCsrf();
        $this->form->setValues(['_csrf' => 'bad-token']);
        $this->form->addFields(['name' => ['type' => 'text']], false);
        $this->assertFalse($this->form->validate());
    }

    // --- Errors ---

    public function testErrorReturnsNullWhenNoError() {
        $this->assertNull($this->form->error('name'));
    }

    public function testAddErrorMakesValidateFail() {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addError('Something went wrong.');
        $this->assertFalse($form->validate());
    }

    // --- Validators ---

    public function testAddValidatorSetsFormOnValidator() {
        $validator = new AlwaysPassesValidator();
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->addValidator('name', $validator);
        $this->assertSame($form, $validator->form());
    }

    // --- Process ---

    public function testProcessReturnsFalseWhenHttpMethodDoesNotMatch() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $this->assertFalse($form->process('POST'));
    }

    public function testProcessBindsAndValidatesWhenHttpMethodMatches() {
        $_REQUEST['form'] = ['name' => 'Joe'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $this->assertTrue($form->process('POST'));
        $this->assertEquals('Joe', $form->value('name'));
    }

    public function testProcessGeneratesCsrf() {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $form = new Form(new Request(), $this->session);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->process('POST');
        $this->assertArrayHasKey('_csrf', $form->fields());
    }
}
