<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Middleware\SecurityHeadersMiddleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class SecurityHeadersMiddlewareTest extends TestCase
{
    public function testDefaultHeaders()
    {
        $middleware = new SecurityHeadersMiddleware();
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertArrayHasKey('X-XSS-Protection', $headers);
    }

    public function testCustomCSP()
    {
        $middleware = new SecurityHeadersMiddleware([
            'csp' => "default-src 'self'; script-src 'self' 'unsafe-inline'",
        ]);
        $headers = $middleware->getHeaders();

        $this->assertEquals(
            "default-src 'self'; script-src 'self' 'unsafe-inline'",
            $headers['Content-Security-Policy']
        );
    }

    public function testHSTSHeader()
    {
        $middleware = new SecurityHeadersMiddleware([
            'hsts' => 'max-age=31536000; includeSubDomains',
        ]);

        // Simuler HTTPS
        $_SERVER['HTTPS'] = 'on';
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertEquals('max-age=31536000; includeSubDomains', $headers['Strict-Transport-Security']);

        // Nettoyer
        unset($_SERVER['HTTPS']);
    }

    public function testHSTSNotInHttp()
    {
        $middleware = new SecurityHeadersMiddleware([
            'hsts' => 'max-age=31536000',
        ]);

        // Simuler HTTP (pas HTTPS)
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;
        $headers = $middleware->getHeaders();

        $this->assertArrayNotHasKey('Strict-Transport-Security', $headers);

        // Nettoyer
        unset($_SERVER['SERVER_PORT']);
    }

    public function testXFrameOptions()
    {
        $middleware = new SecurityHeadersMiddleware([
            'xFrameOptions' => 'DENY',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertEquals('DENY', $headers['X-Frame-Options']);
    }

    public function testXContentTypeOptions()
    {
        $middleware = new SecurityHeadersMiddleware([
            'xContentTypeOptions' => 'nosniff',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);
    }

    public function testReferrerPolicy()
    {
        $middleware = new SecurityHeadersMiddleware([
            'referrerPolicy' => 'no-referrer',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertEquals('no-referrer', $headers['Referrer-Policy']);
    }

    public function testPermissionsPolicy()
    {
        $middleware = new SecurityHeadersMiddleware([
            'permissionsPolicy' => 'geolocation=(), microphone=()',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Permissions-Policy', $headers);
        $this->assertEquals('geolocation=(), microphone=()', $headers['Permissions-Policy']);
    }

    public function testXXssProtection()
    {
        $middleware = new SecurityHeadersMiddleware([
            'xXssProtection' => '1; mode=block',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertEquals('1; mode=block', $headers['X-XSS-Protection']);
    }

    public function testApplyToResponse()
    {
        $middleware = new SecurityHeadersMiddleware();
        $response = new Response(200, 'Test content');
        
        $response = $middleware->applyToResponse($response);
        
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('content-security-policy', $headers);
        $this->assertArrayHasKey('x-frame-options', $headers);
    }

    public function testHandleReturnsNull()
    {
        $middleware = new SecurityHeadersMiddleware();
        $request = new Request('/test', 'GET');
        
        $result = $middleware->handle($request);
        
        $this->assertNull($result);
    }

    public function testHttpsDetectionViaForwardedProto()
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['SERVER_PORT'] = 80;
        unset($_SERVER['HTTPS']);

        $middleware = new SecurityHeadersMiddleware([
            'hsts' => 'max-age=31536000',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Strict-Transport-Security', $headers);

        // Nettoyer
        unset($_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['SERVER_PORT']);
    }

    public function testHttpsDetectionViaPort()
    {
        $_SERVER['SERVER_PORT'] = 443;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);

        $middleware = new SecurityHeadersMiddleware([
            'hsts' => 'max-age=31536000',
        ]);
        $headers = $middleware->getHeaders();

        $this->assertArrayHasKey('Strict-Transport-Security', $headers);

        // Nettoyer
        unset($_SERVER['SERVER_PORT']);
    }

    public function testAllHeadersConfigured()
    {
        $middleware = new SecurityHeadersMiddleware([
            'csp' => "default-src 'self'",
            'hsts' => 'max-age=31536000',
            'xFrameOptions' => 'DENY',
            'xContentTypeOptions' => 'nosniff',
            'referrerPolicy' => 'strict-origin-when-cross-origin',
            'permissionsPolicy' => 'geolocation=()',
            'xXssProtection' => '1; mode=block',
        ]);

        $_SERVER['HTTPS'] = 'on';
        $headers = $middleware->getHeaders();

        $this->assertCount(7, $headers);
        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertArrayHasKey('Strict-Transport-Security', $headers);
        $this->assertArrayHasKey('X-Frame-Options', $headers);
        $this->assertArrayHasKey('X-Content-Type-Options', $headers);
        $this->assertArrayHasKey('Referrer-Policy', $headers);
        $this->assertArrayHasKey('Permissions-Policy', $headers);
        $this->assertArrayHasKey('X-XSS-Protection', $headers);

        // Nettoyer
        unset($_SERVER['HTTPS']);
    }
}
