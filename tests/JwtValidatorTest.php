<?php

use PHPUnit\Framework\TestCase;
use Dynart\Micro\Middleware\JwtValidator;
use Dynart\Micro\AuthorizationException;
use Dynart\Micro\ConfigInterface;
use Dynart\Micro\JwtAuthInterface;
use Dynart\Micro\JwtUser;
use Dynart\Micro\RequestInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * @covers \Dynart\Micro\Middleware\JwtValidator
 */
final class JwtValidatorTest extends TestCase
{
    private const SECRET = 'test-secret-key-with-at-least-32-chars-ok';
    private const ALGORITHM = 'HS256';

    private $request;
    private $jwtAuth;
    private $config;
    private JwtValidator $validator;

    protected function setUp(): void {
        $this->request = $this->getMockBuilder(RequestInterface::class)->getMock();
        $this->jwtAuth = $this->getMockBuilder(JwtAuthInterface::class)->getMock();
        $this->config = $this->getMockBuilder(ConfigInterface::class)->getMock();
        $this->config->method('get')->willReturnCallback(function(string $name, mixed $default = null) {
            if ($name === 'jwt.secret') return self::SECRET;
            if ($name === 'jwt.algorithm') return self::ALGORITHM;
            return $default;
        });
        $this->validator = new JwtValidator($this->request, $this->jwtAuth, $this->config);
    }

    private function makeToken(array $payload): string {
        return JWT::encode($payload, self::SECRET, self::ALGORITHM);
    }

    public function testRunWithNoBearerHeaderDoesNothing(): void {
        $this->request->method('header')->willReturn(null);
        $this->jwtAuth->expects($this->never())->method('setUser');
        $this->validator->run();
    }

    public function testRunWithNonBearerAuthHeaderDoesNothing(): void {
        $this->request->method('header')->willReturn('Basic dXNlcjpwYXNz');
        $this->jwtAuth->expects($this->never())->method('setUser');
        $this->validator->run();
    }

    public function testRunWithValidTokenResolvesAndSetsUser(): void {
        $token = $this->makeToken(['sub' => 'user-1', 'exp' => time() + 3600]);
        $this->request->method('header')->willReturn('Bearer ' . $token);
        $user = new JwtUser('user-1');
        $this->jwtAuth->expects($this->once())->method('resolveUser')
            ->with('user-1', $this->anything())
            ->willReturn($user);
        $this->jwtAuth->expects($this->once())->method('setUser')->with($user);
        $this->validator->run();
    }

    public function testRunWithInvalidTokenThrows401(): void {
        $this->request->method('header')->willReturn('Bearer invalid.token.value');
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(401);
        $this->validator->run();
    }

    public function testRunWithExpiredTokenThrows401(): void {
        $token = $this->makeToken(['sub' => 'user-1', 'exp' => time() - 3600]);
        $this->request->method('header')->willReturn('Bearer ' . $token);
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(401);
        $this->validator->run();
    }

    public function testRunWithWrongSecretThrows401(): void {
        $token = JWT::encode(['sub' => 'user-1', 'exp' => time() + 3600], 'wrong-secret-but-also-at-least-32-chars', self::ALGORITHM);
        $this->request->method('header')->willReturn('Bearer ' . $token);
        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(401);
        $this->validator->run();
    }
}
