<?php

require_once dirname(__FILE__, 2) .'/src/ResettableMicro.php';

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Form;
use Dynart\Micro\Request;
use Dynart\Micro\Session;
use Dynart\Micro\AbstractValidator;
use Dynart\Micro\Micro;
use Dynart\Micro\ViewInterface;
use Dynart\Micro\FormWidgets;
use Dynart\Micro\LoggerInterface;
use Dynart\Micro\Test\ResettableMicro;

class SpyView extends \Dynart\Micro\View {
    public array $fetchLog = [];

    public function __construct() {
        // Skip parent — no Config needed for the spy
    }

    public function fetch(string $__viewPath, array $__vars = []): string {
        $this->fetchLog[] = ['template' => $__viewPath, 'params' => $__vars];
        return "<{$__viewPath}>";
    }
}

/**
 * A logger that only remembers, so a test can assert something was reported
 */
class SpyLogger extends \Psr\Log\AbstractLogger implements LoggerInterface {
    public array $warnings = [];

    public function level(): string {
        return 'warning';
    }

    public function log($level, $message, array $context = []): void {
        if ($level === 'warning') {
            $this->warnings[] = (string)$message;
        }
    }
}

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

class StubTranslation implements \Dynart\Micro\TranslationInterface {

    public function __construct(private array $texts = []) {}

    public function add(string $namespace, string $folder): void {}
    public function allLocales(): array { return ['en']; }
    public function hasMultiLocales(): bool { return false; }
    public function locale(): string { return 'en'; }
    public function setLocale(string $locale): void {}

    public function get(string $id, array $params = []): string {
        return $this->texts[$id] ?? '#'.$id.'#';
    }
}

class HookSpyForm extends Form {
    public array $calls = [];
    protected function beforeValidate(): void { $this->calls[] = 'beforeValidate'; }
    protected function afterValidate(bool $valid): void { $this->calls[] = 'afterValidate:'.($valid ? 'true' : 'false'); }
}

/**
 * A request whose uploaded files are whatever the test says they are
 */
class FileStubRequest extends Request {

    public function __construct(private array $files = []) {
        parent::__construct();
    }

    public function uploadedFile(string $name): \Dynart\Micro\UploadedFile|array|null {
        return $this->files[$name] ?? null;
    }
}

/**
 * @covers \Dynart\Micro\Form
 */
final class FormTest extends TestCase {

    private Session $session;

    private Form $form;

    protected function setUp(): void {
        ResettableMicro::reset();
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

    /**
     * The one that used to pass
     *
     * `null == ''` is true in PHP, so a loose comparison let any form the visitor had never
     * rendered be posted from another site with an empty token - which is the whole attack.
     */
    public function testValidateCsrfFailsWhenTheSessionHasNoToken(): void {
        $this->form->setValues(['_csrf' => '']);
        $this->assertFalse($this->form->validateCsrf());
    }

    public function testValidateCsrfFailsWhenTheFieldIsMissingEntirely(): void {
        $this->form->generateCsrf();
        $this->form->setValues([]);
        $this->assertFalse($this->form->validateCsrf());
    }

    public function testValidateCsrfFailsWhenBothAreEmpty(): void {
        $this->session->set($this->form->csrfSessionName(), '');
        $this->form->setValues(['_csrf' => '']);
        $this->assertFalse($this->form->validateCsrf());
    }

    // --- Uploaded files ---

    /**
     * An upload arrives in `$_FILES`, so without binding it a **required** file field could never
     * be satisfied however many files were attached.
     */
    public function testBindTakesTheUploadedFileOfAFileField(): void {
        $request = new FileStubRequest(['upload' => ['photo' => $this->uploadedFile('holiday.jpg')]]);
        $form = new Form($request, $this->session, 'upload', false);
        $form->addFields(['photo' => ['type' => 'file']]);
        $form->bind();
        $this->assertSame('holiday.jpg', $form->value('photo'));
        $this->assertSame('holiday.jpg', $form->uploadedFile('photo')->name());
    }

    public function testARequiredFileFieldValidatesWhenAFileWasSent(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new FileStubRequest(['upload' => ['photo' => $this->uploadedFile('holiday.jpg')]]);
        $form = new Form($request, $this->session, 'upload', false);
        $form->addFields(['photo' => ['type' => 'file']]);
        $this->assertTrue($form->process());
    }

    public function testARequiredFileFieldFailsWhenNothingWasSent(): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new FileStubRequest([]);
        $form = new Form($request, $this->session, 'upload', false);
        $form->addFields(['photo' => ['type' => 'file']]);
        $this->assertFalse($form->process());
        $this->assertNotEmpty($form->error('photo'));
        $this->assertNull($form->uploadedFile('photo'));
    }

    /**
     * The browser sends the field with `UPLOAD_ERR_NO_FILE` when the input was left alone, which
     * is not a file - taking it would make a required field pass with nothing behind it.
     */
    public function testAnEmptyFileInputIsNotAFile(): void {
        $request = new FileStubRequest(['upload' => ['photo' => $this->uploadedFile('', UPLOAD_ERR_NO_FILE)]]);
        $form = new Form($request, $this->session, 'upload', false);
        $form->addFields(['photo' => ['type' => 'file']]);
        $form->bind();
        $this->assertNull($form->uploadedFile('photo'));
        $this->assertNull($form->value('photo'));
    }

    public function testAnUnnamedFormReadsTheFileByTheFieldName(): void {
        $request = new FileStubRequest(['photo' => $this->uploadedFile('holiday.jpg')]);
        $form = new Form($request, $this->session, '', false);
        $form->addFields(['photo' => ['type' => 'file']]);
        $form->bind();
        $this->assertSame('holiday.jpg', $form->uploadedFile('photo')->name());
    }

    private function uploadedFile(string $name, int $error = UPLOAD_ERR_OK): \Dynart\Micro\UploadedFile {
        return new \Dynart\Micro\UploadedFile($name, sys_get_temp_dir().'/'.$name, $error, 'image/jpeg', 10);
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

    // --- Fetch ---

    private function setupSpyView(): SpyView {
        Micro::add(ViewInterface::class, SpyView::class);
        return Micro::get(ViewInterface::class);
    }

    public function testFetchErrorsDelegatesToView(): void {
        $spy = $this->setupSpyView();
        $result = $this->form->fetchErrors();
        $this->assertCount(1, $spy->fetchLog);
        $this->assertEquals(Form::VIEW_ERRORS, $spy->fetchLog[0]['template']);
        $this->assertSame($this->form, $spy->fetchLog[0]['params']['form']);
        $this->assertEquals('<'.Form::VIEW_ERRORS.'>', $result);
    }

    public function testFetchFieldDelegatesToView(): void {
        $spy = $this->setupSpyView();
        $field = ['type' => 'text'];
        $result = $this->form->fetchField('email', $field);
        $this->assertCount(1, $spy->fetchLog);
        $this->assertEquals(Form::VIEW_FIELD, $spy->fetchLog[0]['template']);
        $this->assertSame($this->form, $spy->fetchLog[0]['params']['form']);
        $this->assertEquals('email', $spy->fetchLog[0]['params']['name']);
        $this->assertEquals($field, $spy->fetchLog[0]['params']['field']);
        $this->assertEquals('<'.Form::VIEW_FIELD.'>', $result);
    }

    /**
     * The input's template comes from the registry, not from a constant
     *
     * `VIEW_ERRORS` and `VIEW_FIELD` above are still constants, because there is one errors list
     * and one label/error wrapper. There are as many inputs as there are field types, which is
     * why that one moved to `FormWidgets` - a constant only the one subclass can set is a
     * mechanism with room for exactly one contributor.
     */
    public function testFetchInputRendersTheWidgetRegisteredForTheType(): void {
        $spy = $this->setupSpyView();
        Micro::add(FormWidgets::class);
        $field = ['type' => 'text'];
        $result = $this->form->fetchInput('email', $field);
        $this->assertCount(1, $spy->fetchLog);
        $this->assertEquals(FormWidgets::VIEW_PREFIX.'text', $spy->fetchLog[0]['template']);
        $this->assertSame($this->form, $spy->fetchLog[0]['params']['form']);
        $this->assertEquals('email', $spy->fetchLog[0]['params']['name']);
        $this->assertEquals($field, $spy->fetchLog[0]['params']['field']);
        $this->assertEquals('<'.FormWidgets::VIEW_PREFIX.'text>', $result);
    }

    /**
     * A field with no type at all is a text field, the same default the widgets use
     */
    public function testAFieldWithNoTypeRendersAsText(): void {
        $spy = $this->setupSpyView();
        Micro::add(FormWidgets::class);
        $this->form->fetchInput('email', []);
        $this->assertEquals(FormWidgets::VIEW_PREFIX.'text', $spy->fetchLog[0]['template']);
    }

    /**
     * An unknown type used to render an empty string - no error, no warning, a missing row in
     * somebody's form and nothing anywhere to say why
     */
    public function testAnUnknownTypeSaysSoInsteadOfRenderingNothing(): void {
        $this->setupSpyView();
        Micro::add(FormWidgets::class);
        Micro::add(LoggerInterface::class, SpyLogger::class);
        $result = $this->form->fetchInput('colour', ['type' => 'colour-picker']);
        $this->assertStringContainsString('colour-picker', $result);
        $this->assertStringContainsString('no form widget', $result);
        $logged = Micro::get(LoggerInterface::class)->warnings;
        $this->assertCount(1, $logged);
        $this->assertStringContainsString('colour-picker', $logged[0]);
    }

    public function testFetchCombinesErrorsAndAllFields(): void {
        $spy = $this->setupSpyView();
        $this->form->addFields([
            'name'  => ['type' => 'text'],
            'email' => ['type' => 'email'],
        ]);
        $result = $this->form->fetch();
        // 1 fetchErrors call + 1 fetchField call per field
        $this->assertCount(3, $spy->fetchLog);
        $this->assertEquals(Form::VIEW_ERRORS, $spy->fetchLog[0]['template']);
        $this->assertEquals(Form::VIEW_FIELD, $spy->fetchLog[1]['template']);
        $this->assertEquals('name',       $spy->fetchLog[1]['params']['name']);
        $this->assertEquals(Form::VIEW_FIELD, $spy->fetchLog[2]['template']);
        $this->assertEquals('email',      $spy->fetchLog[2]['params']['name']);
        $this->assertEquals('<'.Form::VIEW_ERRORS.'><'.Form::VIEW_FIELD.'><'.Form::VIEW_FIELD.'>', $result);
    }

    // --- Input names & ids ---

    public function testInputNameGroupsFieldsUnderTheFormName(): void {
        $this->assertEquals('form[email]', $this->form->inputName('email'));
    }

    public function testInputNameIsPlainForUnnamedForm(): void {
        $form = new Form(new Request(), $this->session, '', false);
        $this->assertEquals('email', $form->inputName('email'));
    }

    public function testInputIdUsesUnderscore(): void {
        $this->assertEquals('form_email', $this->form->inputId('email'));
    }

    public function testInputIdIsPlainForUnnamedForm(): void {
        $form = new Form(new Request(), $this->session, '', false);
        $this->assertEquals('email', $form->inputId('email'));
    }

    public function testIdByNameAndFieldUsesGeneratedIdWhenNoneGiven(): void {
        $this->assertEquals('form_email', $this->form->idByNameAndField('email', ['type' => 'text']));
    }

    public function testIdByNameAndFieldPrefersExplicitId(): void {
        $this->assertEquals('custom', $this->form->idByNameAndField('email', ['type' => 'text', 'id' => 'custom']));
    }

    /**
     * The rendered input name has to be the one bind() reads back, otherwise a named form
     * never receives its own values.
     */
    public function testInputNameRoundTripsThroughBind(): void {
        $form = new Form(new Request(), $this->session, 'contact', false);
        $form->addFields(['email' => ['type' => 'text']]);
        $this->assertEquals('contact[email]', $form->inputName('email'));
        // what PHP builds in $_REQUEST from name="contact[email]"
        $_REQUEST['contact'] = ['email' => 'joe@test.com'];
        $form->bind();
        $this->assertEquals('joe@test.com', $form->value('email'));
    }

    public function testBindHandlesNonArrayRequestValue(): void {
        $_REQUEST['form'] = 'not-an-array';
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->bind();
        $this->assertEquals([], $form->values());
    }

    // --- Per field required ---

    public function testAddFieldsRespectsPerFieldRequiredFlag(): void {
        $this->form->addFields([
            'email' => ['type' => 'text'],
            'notes' => ['type' => 'text', 'required' => false],
        ]);
        $this->assertTrue($this->form->required('email'));
        $this->assertFalse($this->form->required('notes'));
    }

    public function testAddFieldsPerFieldFlagOverridesFalseDefault(): void {
        $this->form->addFields([
            'notes' => ['type' => 'text'],
            'email' => ['type' => 'text', 'required' => true],
        ], false);
        $this->assertFalse($this->form->required('notes'));
        $this->assertTrue($this->form->required('email'));
    }

    // --- Name & csrf setters ---

    public function testSetName(): void {
        $this->form->setName('contact');
        $this->assertEquals('contact', $this->form->name());
        $this->assertEquals('contact[email]', $this->form->inputName('email'));
        $this->assertEquals('form.contact.csrf', $this->form->csrfSessionName());
    }

    public function testSetCsrf(): void {
        $this->assertTrue($this->form->csrf());
        $this->form->setCsrf(false);
        $this->assertFalse($this->form->csrf());
        $this->form->generateCsrf();
        $this->assertArrayNotHasKey('_csrf', $this->form->fields());
    }

    // --- Errors ---

    public function testAddErrorGoesToFormErrors(): void {
        $this->form->addError('Something went wrong.');
        $this->assertEquals(['Something went wrong.'], $this->form->formErrors());
        $this->assertTrue($this->form->hasErrors());
    }

    public function testAddFieldErrorKeepsTheFirstError(): void {
        $this->form->addFieldError('email', 'First.');
        $this->form->addFieldError('email', 'Second.');
        $this->assertEquals('First.', $this->form->error('email'));
    }

    public function testErrorsReturnsFieldErrorsOnly(): void {
        $this->form->addError('Form level.');
        $this->form->addFieldError('email', 'Field level.');
        $this->assertEquals(['email' => 'Field level.'], $this->form->errors());
        $this->assertEquals(['Form level.'], $this->form->formErrors());
    }

    public function testHasErrorsIsFalseOnFreshForm(): void {
        $this->assertFalse($this->form->hasErrors());
    }

    // --- Translated messages ---

    public function testRequiredMessageUsesDefaultWithoutTranslation(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->validate();
        $this->assertEquals(Form::DEFAULT_MESSAGE_REQUIRED, $form->error('name'));
    }

    public function testRequiredMessageIsTranslated(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->setTranslation(new StubTranslation(['micro:form_required' => 'Kötelező.']));
        $form->addFields(['name' => ['type' => 'text']]);
        $form->validate();
        $this->assertEquals('Kötelező.', $form->error('name'));
    }

    public function testRequiredMessageFallsBackWhenTranslationMissing(): void {
        $form = new Form(new Request(), $this->session, 'form', false);
        $form->setTranslation(new StubTranslation([]));
        $form->addFields(['name' => ['type' => 'text']]);
        $form->validate();
        $this->assertEquals(Form::DEFAULT_MESSAGE_REQUIRED, $form->error('name'));
    }

    public function testCsrfMessageIsTranslated(): void {
        $this->form->setTranslation(new StubTranslation(['micro:form_csrf_invalid' => 'Érvénytelen token.']));
        $this->form->generateCsrf();
        $this->form->addValues(['_csrf' => 'bad-token']);
        $this->form->validate();
        $this->assertEquals(['Érvénytelen token.'], $this->form->formErrors());
    }

    public function testCsrfMessageUsesDefaultWithoutTranslation(): void {
        $this->form->generateCsrf();
        $this->form->addValues(['_csrf' => 'bad-token']);
        $this->form->validate();
        $this->assertEquals([Form::DEFAULT_MESSAGE_CSRF_INVALID], $this->form->formErrors());
    }

    // --- Lifecycle hooks ---

    public function testProcessCallsHooksInOrder(): void {
        $_REQUEST['form'] = ['name' => 'Joe'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = new HookSpyForm(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $this->assertTrue($form->process('POST'));
        $this->assertEquals(['beforeValidate', 'afterValidate:true'], $form->calls);
    }

    public function testProcessHooksReportInvalidResult(): void {
        $_REQUEST['form'] = ['name' => ''];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = new HookSpyForm(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $this->assertFalse($form->process('POST'));
        $this->assertEquals(['beforeValidate', 'afterValidate:false'], $form->calls);
    }

    public function testProcessSkipsHooksWhenHttpMethodDoesNotMatch(): void {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $form = new HookSpyForm(new Request(), $this->session, 'form', false);
        $form->addFields(['name' => ['type' => 'text']]);
        $form->process('POST');
        $this->assertEquals([], $form->calls);
    }

    /**
     * generateCsrf() runs at the end of process(), so it must not clear the bound values —
     * otherwise a form redisplayed after a failed validation loses everything the user typed.
     */
    public function testProcessKeepsBoundValuesAfterCsrfGeneration(): void {
        $_REQUEST['form'] = ['name' => 'Joe', '_csrf' => 'whatever'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $form = new Form(new Request(), $this->session, 'form');
        $form->addFields(['name' => ['type' => 'text']]);
        $form->process('POST');
        $this->assertEquals('Joe', $form->value('name'));
    }
}
