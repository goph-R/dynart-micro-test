<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\JwtUser;

/**
 * @covers \Dynart\Micro\JwtUser
 */
final class JwtUserTest extends TestCase
{
    public function testSubReturnsValue(): void {
        $user = new JwtUser('user-42');
        $this->assertEquals('user-42', $user->sub());
    }

    public function testDefaultPermissionsIsEmpty(): void {
        $user = new JwtUser('user-42');
        $this->assertEquals([], $user->permissions());
    }

    public function testPermissionsReturnsArray(): void {
        $user = new JwtUser('user-42', ['read', 'write']);
        $this->assertEquals(['read', 'write'], $user->permissions());
    }

    public function testHasPermissionReturnsTrueForExisting(): void {
        $user = new JwtUser('user-42', ['read', 'admin']);
        $this->assertTrue($user->hasPermission('read'));
        $this->assertTrue($user->hasPermission('admin'));
    }

    public function testHasPermissionReturnsFalseForMissing(): void {
        $user = new JwtUser('user-42', ['read']);
        $this->assertFalse($user->hasPermission('admin'));
    }
}
