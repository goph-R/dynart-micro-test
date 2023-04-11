<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Session;

final class SessionTest extends TestCase {

    public function testSessionStartSetGetDestroy() {
        $session = new Session();
        $session->set('test', 'value');
        $this->assertEquals('value', $session->get('test'));
        $session->destroy();
        $this->assertEquals('default', $session->get('test', 'default'));
    }

}