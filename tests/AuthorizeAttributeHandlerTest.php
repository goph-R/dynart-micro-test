<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\AttributeHandler\AuthorizeAttributeHandler;
use Dynart\Micro\Attribute\Authorize;
use Dynart\Micro\JwtAuth;
use Dynart\Micro\JwtAuthInterface;
use Dynart\Micro\EventService;

class AuthorizeTestController {
    public function index() {}
}

/**
 * @covers \Dynart\Micro\AttributeHandler\AuthorizeAttributeHandler
 */
final class AuthorizeAttributeHandlerTest extends TestCase
{
    private JwtAuth $jwtAuth;
    private AuthorizeAttributeHandler $handler;

    protected function setUp(): void {
        $this->jwtAuth = new JwtAuth(new EventService());
        $this->handler = new AuthorizeAttributeHandler($this->jwtAuth);
    }

    public function testAttributeClassReturnsAuthorizeClass(): void {
        $this->assertEquals(Authorize::class, $this->handler->attributeClass());
    }

    public function testTargetsReturnsClassAndMethod(): void {
        $targets = $this->handler->targets();
        $this->assertContains(AttributeHandlerInterface::TARGET_CLASS, $targets);
        $this->assertContains(AttributeHandlerInterface::TARGET_METHOD, $targets);
    }

    public function testHandleClassLevelAttribute(): void {
        $attribute = new Authorize('admin');
        $refClass = new ReflectionClass(AuthorizeTestController::class);
        $this->handler->handle(AuthorizeTestController::class, $refClass, $attribute);

        // Verify by testing that auth check works for this class
        $controller = new AuthorizeTestController();
        $this->expectException(\Dynart\Micro\AuthorizationException::class);
        $this->expectExceptionCode(401);
        $this->jwtAuth->onRouteMatched([$controller, 'anyMethod'], []);
    }

    public function testHandleMethodLevelAttribute(): void {
        $attribute = new Authorize('');
        $refMethod = new ReflectionMethod(AuthorizeTestController::class, 'index');
        $this->handler->handle(AuthorizeTestController::class, $refMethod, $attribute);

        $controller = new AuthorizeTestController();
        $this->expectException(\Dynart\Micro\AuthorizationException::class);
        $this->expectExceptionCode(401);
        $this->jwtAuth->onRouteMatched([$controller, 'index'], []);
    }

    public function testHandleMethodLevelAttributeWithPermission(): void {
        $jwtAuthMock = $this->getMockBuilder(JwtAuthInterface::class)->getMock();
        $handler = new AuthorizeAttributeHandler($jwtAuthMock);

        $attribute = new Authorize('write');
        $refMethod = new ReflectionMethod(AuthorizeTestController::class, 'index');
        $jwtAuthMock->expects($this->once())->method('addMethodAuthorization')
            ->with(AuthorizeTestController::class, 'index', 'write');
        $handler->handle(AuthorizeTestController::class, $refMethod, $attribute);
    }

    public function testHandleClassLevelAttributeCallsAddClassAuthorization(): void {
        $jwtAuthMock = $this->getMockBuilder(JwtAuthInterface::class)->getMock();
        $handler = new AuthorizeAttributeHandler($jwtAuthMock);

        $attribute = new Authorize('editor');
        $refClass = new ReflectionClass(AuthorizeTestController::class);
        $jwtAuthMock->expects($this->once())->method('addClassAuthorization')
            ->with(AuthorizeTestController::class, 'editor');
        $handler->handle(AuthorizeTestController::class, $refClass, $attribute);
    }
}
