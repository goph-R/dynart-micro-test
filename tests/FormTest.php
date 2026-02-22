<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Form;
use Dynart\Micro\Request;
use Dynart\Micro\Session;
use Dynart\Micro\AbstractValidator;

class AlwaysFailsValidator extends AbstractValidator {
    public function validate(mixed $value): bool {
        $this->message = 'Validation failed.';
        return false;
    }
}

class AlwaysPassesValidator extends AbstractValidator {
    public function validate(mixed $value): bool {
        return true;
    }
}

/**
 * @covers \Dynart\Micro\Form
 */
final class FormTest extends TestCase {

    private Session $session;

    private Form $form;

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

    public function testGenerateCsrfAddsCsrfFieldAndSetsSession(): void {
        $this->form->generateCsrf();
        $this->assertArrayHasKey($this->form->csrfName(), $this->form->fields());
        $this->assertEquals(
            $this->session->get($this->form->csrfSessionName()),
            $this->form->value($this->form->csrfName())
        );
    }

    public function testGenerateCsrfDoesNothingWhenCsrfDisabled(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->generateCsrf();
        $this->assertArrayNotHasKey('_csrf', $form->fields());
    }

    public function testCsrfSessionName(): void {
        $this->assertEquals('form.form.csrf', $this->form->csrfSessionName());
    }

    public function testCsrfSessionNameWithCustomFormName(): void {
        $form = new Form(new Request(), $this->session, 'login');
        $this->assertEquals('form.login.csrf', $form->csrfSessionName());
    }

    public function testCsrfName(): void {
        $this->assertEquals('_csrf', $this->form->csrfName());
    }

    public function testValidateCsrfReturnsTrueWhenSessionMatchesValue(): void {
        $this->form->generateCsrf();
        $this->assertTrue($this->form->validateCsrf());
    }

    public function testValidateCsrfReturnsFalseWhenMismatch(): void {
        $this->form->generateCsrf();
        $this->form->setValues(['_csrf' => 'wrong-token']);
        $this->assertFalse($this->form->validateCsrf());
    }

    public function testValidateCsrfReturnsTrueWhenCsrfDisabled(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $this->assertTrue($form->validateCsrf());
    }

    // --- Name & Fields ---

    public function testName(): void {
        $this->assertEquals('form', $this->form->name());
    }

    public function testNameWithCustomName(): void {
        $form = new Form(new Request(), $this->session, 'contact');
        $this->assertEquals('contact', $form->name());
    }

    public function testAddFieldsMakesThemRequired(): void {
        $this->form->addFields(['email' => ['type' => 'text']]);
        $this->assertTrue($this->form->required('email'));
    }

    public function testAddFieldsNotRequired(): void {
        $this->form->addFields(['notes' => ['type' => 'textarea']], false);
        $this->assertFalse($this->form->required('notes'));
    }

    public function testFieldsReturnsAddedFields(): void {
        $fields = ['name' => ['type' => 'text'], 'email' => ['type' => 'email']];
        $this->form->addFields($fields);
        $this->assertEquals($fields, $this->form->fields());
    }

    public function testSetRequiredTrue(): void {
        $this->form->addFields(['name' => ['type' => 'text']], false);
        $this->assertFalse($this->form->required('name'));
        $this->form->setRequired('name', true);
        $this->assertTrue($this->form->required('name'));
    }

    public function testSetRequiredFalse(): void {
        $this->form->addFields(['name' => ['type' => 'text']]);
        $this->assertTrue($this->form->required('name'));
        $this->form->setRequired('name', false);
        $this->assertFalse($this->form->required('name'));
    }

    public function testSetRequiredTrueDoesNotDuplicate(): void {
        $this->form->addFields(['name' => ['type' => 'text']]);
        $this->form->setRequired('name', true);
        $this->assertTrue($this->form->required('name'));
    }

    // --- Values ---

    public function testSetValuesAndValue(): void {
        $this->form->setValues(['name' => 'Joe']);
        $this->assertEquals('Joe', $this->form->value('name'));
    }

    public function testValueReturnsNullForNonexistentField(): void {
        $this->assertNull($this->form->value('missing'));
    }

    public function testValueWithEscape(): void {
        $this->form->setValues(['name' => '<script>alert("xss")</script>']);
        $escaped = $this->form->value('name', true);
        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $escaped);
    }

    public function testValues(): void {
        $values = ['name' => 'Joe', 'email' => 'joe@test.com'];
        $this->form->setValues($values);
        $this->assertEquals($values, $this->form->values());
    }

    public function testAddValues(): void {
        $this->form->setValues(['name' => 'Joe']);
        $this->form->addValues(['email' => 'joe@test.com']);
        $this->assertEquals(['name' => 'Joe', 'email' => 'joe@test.com'], $this->form->values());
    }

    // --- Binding ---

    public function testBindWithFormName(): void {
        $_REQUEST['form'] = ['name' => 'Joe', 'email' => 'joe@test.com'];
        $form = new Form(new Request(), $this->session);
        $form->addFields(['name' => ['type' => 'text'], 'email' => ['type' => 'email']]);
        $form->bind();
        $this->assertEquals('Joe', $form->value('name'));
        $this->assertEquals('joe@test.com', $form->value('email'));
    }

    public function testBindWithoutFormName(): void {
        $_REQUEST['name'] = 'Joe';
        $_REQUEST['email'] = 'joe@test.com';
        $form = new Form(new Request(), $this->session, '', false);
        $form->addFields(['name' => ['type' => 'text'], 'email' => ['type' => 'email']]);
        $form->bind();
        $this->assertEquals('Joe', $form->value('name'));
        $this->assertEquals('joe@test.com', $form->value('email'));
    }

    // --- Validation ---

    public function testValidateReturnsTrueWhenAllRequiredFieldsFilled(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->setValues(['name' => 'Joe']);
        $this->assertTrue($form->validate());
    }

    public function testValidateReturnsFalseWhenRequiredFieldEmpty(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->setValues(['name' => '']);
        $this->assertFalse($form->validate());
        $this->assertEquals('Required.', $form->error('name'));
    }

    public function testValidateRunsValidators(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['email' => ['type' => 'email']]);
        $form->setValues(['email' => 'invalid']);
        $form->addValidator('email', new AlwaysFailsValidator());
        $this->assertFalse($form->validate());
        $this->assertEquals('Validation failed.', $form->error('email'));
    }

    public function testValidateSkipsValidatorsWhenFieldAlreadyHasError(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['email' => ['type' => 'email']]);
        $form->setValues(['email' => '']);
        $form->addValidator('email', new AlwaysFailsValidator());
        $form->validate();
        // Required error takes precedence
        $this->assertEquals('Required.', $form->error('email'));
    }

    public function testValidateSkipsValidatorsForOptionalEmptyField(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['notes' => ['type' => 'textarea']], false);
        $form->setValues(['notes' => '']);
        $form->addValidator('notes', new AlwaysFailsValidator());
        $this->assertTrue($form->validate());
    }

    public function testValidateStopsAtFirstFailedValidatorForField(): void {
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

    public function testValidateCsrfFailureReturnsInvalid(): void {
        $this->form->generateCsrf();
        $this->form->setValues(['_csrf' => 'bad-token']);
        $this->form->addFields(['name' => ['type' => 'text']], false);
        $this->assertFalse($this->form->validate());
    }

    // --- Errors ---

    public function testErrorReturnsNullWhenNoError(): void {
        $this->assertNull($this->form->error('name'));
    }

    public function testAddErrorMakesValidateFail(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addError('Something went wrong.');
        $this->assertFalse($form->validate());
    }

    // --- Validators ---

    public function testAddValidatorSetsFormOnValidator(): void {
        $validator = new AlwaysPassesValidator();
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->addValidator('name', $validator);
        $this->assertSame($form, $validator->form());
    }

    // --- Process ---

    public function testProcessReturnsFalseWhenHttpMethodDoesNotMatch(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $this->assertFalse($form->process('POST'));
    }

    public function testProcessBindsAndValidatesWhenHttpMethodMatches(): void {
        $_REQUEST['form'] = ['name' => 'Joe'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $this->assertTrue($form->process('POST'));
        $this->assertEquals('Joe', $form->value('name'));
    }

    public function testProcessGeneratesCsrf(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $form = new Form(new Request(), $this->session);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->process('POST');
        $this->assertArrayHasKey('_csrf', $form->fields());
    }
}
