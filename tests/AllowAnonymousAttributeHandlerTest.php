<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\AttributeHandlerInterface;
use Dynart\Micro\AttributeHandler\AllowAnonymousAttributeHandler;
use Dynart\Micro\Attribute\AllowAnonymous;
use Dynart\Micro\JwtAuth;
use Dynart\Micro\JwtAuthInterface;
use Dynart\Micro\EventService;

class AllowAnonymousTestController {
    public function index() {}
}

/**
 * @covers \Dynart\Micro\AttributeHandler\AllowAnonymousAttributeHandler
 */
final class AllowAnonymousAttributeHandlerTest extends TestCase
{
    private JwtAuth $jwtAuth;
    private AllowAnonymousAttributeHandler $handler;

    protected function setUp(): void {
        $this->jwtAuth = new JwtAuth(new EventService());
        $this->handler = new AllowAnonymousAttributeHandler($this->jwtAuth);
    }

    public function testAttributeClassReturnsAllowAnonymousClass(): void {
        $this->assertEquals(AllowAnonymous::class, $this->handler->attributeClass());
    }

    public function testTargetsReturnsMethodOnly(): void {
        $this->assertEquals([AttributeHandlerInterface::TARGET_METHOD], $this->handler->targets());
    }

    public function testHandleAddsAllowAnonymous(): void {
        $jwtAuthMock = $this->getMockBuilder(JwtAuthInterface::class)->getMock();
        $handler = new AllowAnonymousAttributeHandler($jwtAuthMock);

        $attribute = new AllowAnonymous();
        $refMethod = new ReflectionMethod(AllowAnonymousTestController::class, 'index');
        $jwtAuthMock->expects($this->once())->method('addAllowAnonymous')
            ->with(AllowAnonymousTestController::class, 'index');
        $handler->handle(AllowAnonymousTestController::class, $refMethod, $attribute);
    }

    public function testHandleAllowAnonymousOverridesClassAuth(): void {
        // Register class-level auth requirement
        $this->jwtAuth->addClassAuthorization(AllowAnonymousTestController::class, '');

        // Then register allow-anonymous for specific method
        $attribute = new AllowAnonymous();
        $refMethod = new ReflectionMethod(AllowAnonymousTestController::class, 'index');
        $this->handler->handle(AllowAnonymousTestController::class, $refMethod, $attribute);

        // With no user, this method should still pass (no exception)
        $controller = new AllowAnonymousTestController();
        $this->jwtAuth->onRouteMatched([$controller, 'index'], []);
        $this->assertNull($this->jwtAuth->user()); // no exception thrown
    }
}
