<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Session;

final class SessionTest extends TestCase {

    public function testSessionStartSetGetDestroy(): void {
        $session = new Session();
        $session->set('test', 'value');
        $this->assertEquals('value', $session->get('test'));
        $session->destroy();
        $this->assertEquals('default', $session->get('test', 'default'));
    }

    public function testIdReturnsSessionId(): void {
        $session = new Session();
        $this->assertNotEmpty($session->id());
        $this->assertEquals(session_id(), $session->id());
    }

    public function testConstructorStartsSession(): void {
        // Session is already started by earlier tests, so status should be active
        $session = new Session();
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    public function testConstructorDoesNotRestartActiveSession(): void {
        $session1 = new Session();
        $id1 = $session1->id();
        $session2 = new Session();
        $id2 = $session2->id();
        $this->assertEquals($id1, $id2);
    }

}