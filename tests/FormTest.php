<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Form;
use Dynart\Micro\Request;
use Dynart\Micro\Session;

final class FormTest extends TestCase {

    /** @var Session */
    private $session;

    /** @var Form */
    private $form;

    protected function setUp(): void {
        $this->session = new Session();
        $this->form = new Form(new Request(), $this->session); // request and session will be mocked via global arrays
    }

    public function testGenerateCsrfAddsCsrfFieldAndSetsSession() {
        $this->form->generateCsrf();
        $this->assertArrayHasKey($this->form->csrfName(), $this->form->fields());
        $this->assertEquals(
            $this->session->get($this->form->csrfSessionName()),
            $this->form->value($this->form->csrfName())
        );
    }



}