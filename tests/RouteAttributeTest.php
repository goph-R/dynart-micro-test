<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Attribute\Route;

/**
 * @covers \Dynart\Micro\Attribute\Route
 */
final class RouteAttributeTest extends TestCase
{
    public function testConstructorSetsMethodAndPath(): void {
        $route = new Route('GET', '/users/?');
        $this->assertEquals('GET', $route->method);
        $this->assertEquals('/users/?', $route->path);
    }

    public function testPostMethod(): void {
        $route = new Route('POST', '/users/save');
        $this->assertEquals('POST', $route->method);
        $this->assertEquals('/users/save', $route->path);
    }

    public function testAttributeTargetIsMethod(): void {
        $refClass = new ReflectionClass(Route::class);
        $attributes = $refClass->getAttributes(\Attribute::class);
        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_METHOD, $attr->flags & \Attribute::TARGET_METHOD);
    }

    /**
     * One action commonly answers more than one HTTP method - a form is GET to render it and
     * POST to process it - and both belong on the same method.
     */
    public function testItIsRepeatable(): void {
        $refClass = new ReflectionClass(Route::class);
        $attr = $refClass->getAttributes(\Attribute::class)[0]->newInstance();
        $this->assertEquals(\Attribute::IS_REPEATABLE, $attr->flags & \Attribute::IS_REPEATABLE);
    }

    public function testTwoRoutesOnOneMethodAreBothSeen(): void {
        $subject = new class {
            #[Route('GET', '/thing')]
            #[Route('POST', '/thing')]
            public function thing(): void {}
        };
        $attributes = (new ReflectionMethod($subject, 'thing'))->getAttributes(Route::class);
        $this->assertCount(2, $attributes);
        $this->assertEquals('GET', $attributes[0]->newInstance()->method);
        $this->assertEquals('POST', $attributes[1]->newInstance()->method);
    }
}
