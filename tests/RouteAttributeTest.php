<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Attribute\Route;

/**
 * @covers \Dynart\Micro\Attribute\Route
 */
final class RouteAttributeTest extends TestCase
{
    public function testConstructorSetsMethodAndPath() {
        $route = new Route('GET', '/users/?');
        $this->assertEquals('GET', $route->method);
        $this->assertEquals('/users/?', $route->path);
    }

    public function testPostMethod() {
        $route = new Route('POST', '/users/save');
        $this->assertEquals('POST', $route->method);
        $this->assertEquals('/users/save', $route->path);
    }

    public function testAttributeTargetIsMethod() {
        $refClass = new ReflectionClass(Route::class);
        $attributes = $refClass->getAttributes(\Attribute::class);
        $this->assertCount(1, $attributes);
        $attr = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_METHOD, $attr->flags);
    }
}
