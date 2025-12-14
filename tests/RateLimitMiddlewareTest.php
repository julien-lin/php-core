<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Middleware\RateLimitMiddleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class RateLimitMiddlewareTest extends TestCase
{
    private string $storagePath;

    protected function setUp(): void
    {
        $this->storagePath = sys_get_temp_dir() . '/core-php-rate-limit-test';
        if (is_dir($this->storagePath)) {
            foreach (glob($this->storagePath . '/*.json') as $file) {
                unlink($file);
            }
            rmdir($this->storagePath);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->storagePath)) {
            foreach (glob($this->storagePath . '/*.json') as $file) {
                unlink($file);
            }
            rmdir($this->storagePath);
        }
    }

    public function testAllowsRequestsUnderLimit()
    {
        $middleware = new RateLimitMiddleware(5, 60, $this->storagePath);
        $request = $this->mockRequest('127.0.0.1', '/test');
        $response = null;
        // Tester avec 4 requêtes (sous la limite de 5)
        for ($i = 0; $i < 4; $i++) {
            $response = $middleware->handle($request);
            $this->assertNull($response, "La requête {$i} devrait être autorisée");
        }
    }

    public function testBlocksRequestsOverLimit()
    {
        $middleware = new RateLimitMiddleware(3, 60, $this->storagePath);
        $request = $this->mockRequest('127.0.0.1', '/test');
        for ($i = 0; $i < 3; $i++) {
            $middleware->handle($request);
        }
        $response = $middleware->handle($request);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(429, $response->getStatusCode());
        $this->assertStringContainsString('Trop de requêtes', $response->getContent());
    }

    public function testResetsAfterWindow()
    {
        $middleware = new RateLimitMiddleware(2, 1, $this->storagePath);
        $request = $this->mockRequest('127.0.0.1', '/test');
        $middleware->handle($request);
        $middleware->handle($request);
        sleep(2); // attendre la fin de la fenêtre
        $response = $middleware->handle($request);
        $this->assertNull($response);
    }

    private function mockRequest(string $ip, string $path): Request
    {
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->method('getClientIp')->willReturn($ip);
        $request->method('getPath')->willReturn($path);
        return $request;
    }
}
