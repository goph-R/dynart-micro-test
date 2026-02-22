<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\JwtAuth;
use Dynart\Micro\JwtUser;
use Dynart\Micro\JwtUserInterface;
use Dynart\Micro\AuthorizationException;
use Dynart\Micro\EventService;
use Dynart\Micro\WebApp;

class JwtAuthTestController {}

/**
 * @covers \Dynart\Micro\JwtAuth
 */
final class JwtAuthTest extends TestCase
{
    private EventService $eventService;
    private JwtAuth $jwtAuth;
    private JwtAuthTestController $controller;

    protected function setUp(): void {
        $this->eventService = new EventService();
        $this->jwtAuth = new JwtAuth($this->eventService);
        $this->controller = new JwtAuthTestController();
    }

    public function testUserIsNullByDefault(): void {
        $this->assertNull($this->jwtAuth->user());
    }

    public function testSetUserSetsUser(): void {
        $user = new JwtUser('user-1', ['read']);
        $this->jwtAuth->setUser($user);
        $this->assertSame($user, $this->jwtAuth->user());
    }

    public function testSetUserEmitsEvent(): void {
        $received = null;
        $this->eventService->subscribe(JwtAuth::EVENT_USER_SET, function(JwtUserInterface $u) use (&$received) {
            $received = $u;
        });
        $user = new JwtUser('user-1');
        $this->jwtAuth->setUser($user);
        $this->assertSame($user, $received);
    }

    public function testResolveUserWithNoResolverReturnsJwtUser(): void {
        $payload = (object)['sub' => 'user-1'];
        $user = $this->jwtAuth->resolveUser('user-1', $payload);
        $this->assertInstanceOf(JwtUser::class, $user);
        $this->assertEquals('user-1', $user->sub());
        $this->assertEquals([], $user->permissions());
    }

    public function testResolveUserCallsResolver(): void {
        $customUser = new JwtUser('user-1', ['admin']);
        $this->jwtAuth->setUserResolver(function(string $sub, object $payload) use ($customUser): JwtUserInterface {
            return $customUser;
        });
        $payload = (object)['sub' => 'user-1'];
        $result = $this->jwtAuth->resolveUser('user-1', $payload);
        $this->assertSame($customUser, $result);
    }

    public function testCheckAuthorizationWithNoRequirementsAllows(): void {
        // No auth registered for this class/method — should pass silently
        $this->jwtAuth->onRouteMatched([$this->controller, 'anyMethod'], []);
        $this->assertNull($this->jwtAuth->user()); // no exception thrown
    }

    public function testCheckAuthorizationAllowAnonymousSkipsAuth(): void {
        $this->jwtAuth->addMethodAuthorization(JwtAuthTestController::class, 'index', '');
        $this->jwtAuth->addAllowAnonymous(JwtAuthTestController::class, 'index');
        // No user set, but AllowAnonymous should prevent 401
        $this->jwtAuth->onRouteMatched([$this->controller, 'index'], []);
        $this->assertNull($this->jwtAuth->user()); // no exception
    }

    public function testCheckAuthorizationNullUserThrows401(): void {
        $this->jwtAuth->addMethodAuthorization(JwtAuthTestController::class, 'index', '');
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(401);
        $this->jwtAuth->onRouteMatched([$this->controller, 'index'], []);
    }

    public function testCheckAuthorizationNullUserEmitsDeniedEvent(): void {
        $this->jwtAuth->addMethodAuthorization(JwtAuthTestController::class, 'index', '');
        $emitted = false;
        $this->eventService->subscribe(JwtAuth::EVENT_AUTHORIZATION_DENIED, function() use (&$emitted) {
            $emitted = true;
        });
        try {
            $this->jwtAuth->onRouteMatched([$this->controller, 'index'], []);
        } catch (AuthorizationException $e) {}
        $this->assertTrue($emitted);
    }

    public function testCheckAuthorizationEmptyPermissionOnlyRequiresUser(): void {
        $this->jwtAuth->addMethodAuthorization(JwtAuthTestController::class, 'index', '');
        $this->jwtAuth->setUser(new JwtUser('user-1')); // no permissions
        // Should pass — empty permission means "just authenticated"
        $granted = false;
        $this->eventService->subscribe(JwtAuth::EVENT_AUTHORIZATION_GRANTED, function() use (&$granted) {
            $granted = true;
        });
        $this->jwtAuth->onRouteMatched([$this->controller, 'index'], []);
        $this->assertTrue($granted);
    }

    public function testCheckAuthorizationMissingPermissionThrows403(): void {
        $this->jwtAuth->addMethodAuthorization(JwtAuthTestController::class, 'index', 'admin');
        $this->jwtAuth->setUser(new JwtUser('user-1', ['read']));
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(403);
        $this->jwtAuth->onRouteMatched([$this->controller, 'index'], []);
    }

    public function testCheckAuthorizationWithPermissionPasses(): void {
        $this->jwtAuth->addMethodAuthorization(JwtAuthTestController::class, 'index', 'admin');
        $this->jwtAuth->setUser(new JwtUser('user-1', ['admin']));
        $granted = false;
        $this->eventService->subscribe(JwtAuth::EVENT_AUTHORIZATION_GRANTED, function() use (&$granted) {
            $granted = true;
        });
        $this->jwtAuth->onRouteMatched([$this->controller, 'index'], []);
        $this->assertTrue($granted);
    }

    public function testCheckAuthorizationClassLevelAuth(): void {
        $this->jwtAuth->addClassAuthorization(JwtAuthTestController::class, '');
        // No user — should deny
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(401);
        $this->jwtAuth->onRouteMatched([$this->controller, 'anyMethod'], []);
    }

    public function testCheckAuthorizationMethodOverridesClass(): void {
        // Class requires 'admin', but this method allows any authenticated user
        $this->jwtAuth->addClassAuthorization(JwtAuthTestController::class, 'admin');
        $this->jwtAuth->addMethodAuthorization(JwtAuthTestController::class, 'index', '');
        $this->jwtAuth->setUser(new JwtUser('user-1')); // no 'admin' permission
        // Method-level ('') overrides class-level ('admin') — should pass
        $this->jwtAuth->onRouteMatched([$this->controller, 'index'], []);
        $this->assertNotNull($this->jwtAuth->user()); // no exception
    }

    public function testClosureCallableSkipsAuthorization(): void {
        $this->jwtAuth->addClassAuthorization(JwtAuthTestController::class, 'admin');
        // Closure callable is not an array-based callable, should be skipped
        $closure = function() {};
        $this->jwtAuth->onRouteMatched($closure, []);
        $this->assertNull($this->jwtAuth->user()); // no exception
    }
}
