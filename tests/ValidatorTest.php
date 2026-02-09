<?php

use PHPunit\Framework\TestCase;
use Dynart\Micro\Validator;
use Dynart\Micro\Form;
use Dynart\Micro\Request;
use Dynart\Micro\Session;

final class TestValidator extends Validator {
    public function validate(mixed $value): bool {
        $this->message = 'message';
        return false;
    }
}

/**
 * @covers \Dynart\Micro\Validator
 */
final class ValidatorTest extends TestCase {
    public function testSetForm(): void {
        $form = new Form(new Request(), new Session());
        $validator = new TestValidator();
        $validator->setForm($form);
        $this->assertSame($form, $validator->form());
    }

    public function testMessage(): void { // coverage
        $validator = new TestValidator();
        $validator->validate('');
        $this->assertEquals('message', $validator->message());
    }
}