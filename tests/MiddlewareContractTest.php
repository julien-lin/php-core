<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use JulienLinard\Core\Middleware\CorsMiddleware;
use JulienLinard\Core\Middleware\RateLimitMiddleware;
use JulienLinard\Core\Middleware\RequestValidationMiddleware;
use JulienLinard\Core\Middleware\SecurityHeadersMiddleware;
use JulienLinard\Router\Middleware;
use PHPUnit\Framework\TestCase;

final class MiddlewareContractTest extends TestCase
{
    public function testCoreMiddlewaresImplementTheRouterContract(): void
    {
        $middlewares = [
            new CorsMiddleware(),
            new RateLimitMiddleware(10, 60, sys_get_temp_dir() . '/core-contract-' . bin2hex(random_bytes(4))),
            new RequestValidationMiddleware(),
            new SecurityHeadersMiddleware(),
        ];

        foreach ($middlewares as $middleware) {
            self::assertInstanceOf(Middleware::class, $middleware);
        }
    }
}
