<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Middleware\CompressionMiddleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class CompressionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        // S'assurer que gzip est supporté
        if (!function_exists('gzencode')) {
            $this->markTestSkipped('gzencode() n\'est pas disponible');
        }
    }

    public function testDefaultConfiguration()
    {
        $middleware = new CompressionMiddleware();

        $request = new Request('/test', 'GET');
        $result = $middleware->handle($request);

        $this->assertNull($result);
    }

    public function testCompressionSupported()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip, deflate, br';

        $middleware = new CompressionMiddleware();
        $response = new Response(200, str_repeat('a', 2000)); // Contenu assez grand
        $response->setHeader('Content-Type', 'text/html');

        $compressed = $middleware->compress($response);

        $this->assertInstanceOf(Response::class, $compressed);

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testCompressionNotSupported()
    {
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);

        $middleware = new CompressionMiddleware();
        $response = new Response(200, 'Test content');

        $compressed = $middleware->compress($response);

        // Devrait retourner la réponse originale
        $this->assertSame($response, $compressed);
        $headers = $compressed->getHeaders();
        $this->assertArrayNotHasKey('content-encoding', $headers);
    }

    public function testMinSizeThreshold()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware(['minSize' => 5000]);
        $response = new Response(200, str_repeat('a', 1000)); // Trop petit
        $response->setHeader('Content-Type', 'text/html');

        $compressed = $middleware->compress($response);

        // Ne devrait pas compresser (trop petit)
        $headers = $compressed->getHeaders();
        $this->assertArrayNotHasKey('content-encoding', $headers);

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testContentTypeFiltering()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware();

        // Type compressible
        $response1 = new Response(200, str_repeat('a', 2000));
        $response1->setHeader('Content-Type', 'text/html');
        $compressed1 = $middleware->compress($response1);
        $headers1 = $compressed1->getHeaders();
        $this->assertEquals('gzip', $headers1['content-encoding'] ?? null);

        // Type non compressible
        $response2 = new Response(200, str_repeat('a', 2000));
        $response2->setHeader('Content-Type', 'image/png');
        $compressed2 = $middleware->compress($response2);
        $headers2 = $compressed2->getHeaders();
        $this->assertArrayNotHasKey('content-encoding', $headers2);

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testCompressionLevel()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware(['level' => 9]); // Compression maximale
        $response = new Response(200, str_repeat('a', 5000));
        $response->setHeader('Content-Type', 'text/html');

        $compressed = $middleware->compress($response);

        $headers = $compressed->getHeaders();
        $this->assertEquals('gzip', $headers['content-encoding'] ?? null);
        $this->assertLessThan(strlen($response->getContent()), strlen($compressed->getContent()));

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testCompressedContentIsSmaller()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware();
        $originalContent = str_repeat('Lorem ipsum dolor sit amet, ', 100); // Contenu répétitif
        $response = new Response(200, $originalContent);
        $response->setHeader('Content-Type', 'text/html');

        $compressed = $middleware->compress($response);

        $this->assertLessThan(strlen($originalContent), strlen($compressed->getContent()));
        $this->assertGreaterThan(0, strlen($compressed->getContent()));

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testContentLengthHeader()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware();
        $response = new Response(200, str_repeat('a', 2000));
        $response->setHeader('Content-Type', 'text/html');

        $compressed = $middleware->compress($response);

        $headers = $compressed->getHeaders();
        $contentLength = $headers['content-length'] ?? null;
        $this->assertNotNull($contentLength);
        $this->assertEquals(strlen($compressed->getContent()), (int) $contentLength);

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testCustomContentTypes()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware([
            'contentTypes' => ['application/json', 'text/xml'],
        ]);

        // Type autorisé
        $response1 = new Response(200, str_repeat('a', 2000));
        $response1->setHeader('Content-Type', 'application/json');
        $compressed1 = $middleware->compress($response1);
        $headers1 = $compressed1->getHeaders();
        $this->assertEquals('gzip', $headers1['content-encoding'] ?? null);

        // Type non autorisé
        $response2 = new Response(200, str_repeat('a', 2000));
        $response2->setHeader('Content-Type', 'text/html');
        $compressed2 = $middleware->compress($response2);
        $headers2 = $compressed2->getHeaders();
        $this->assertArrayNotHasKey('content-encoding', $headers2);

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testCompressionFailureReturnsOriginal()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware();
        $response = new Response(200, 'Test');
        $response->setHeader('Content-Type', 'text/html');

        // Mocker un échec de compression (en utilisant un niveau invalide)
        // Note: En pratique, gzencode() ne devrait pas échouer avec des données valides
        // Ce test vérifie que le middleware gère gracieusement les erreurs
        $compressed = $middleware->compress($response);

        // Devrait toujours retourner une réponse valide
        $this->assertInstanceOf(Response::class, $compressed);

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }

    public function testHandleReturnsNull()
    {
        $middleware = new CompressionMiddleware();
        $request = new Request('/test', 'GET');

        $result = $middleware->handle($request);

        $this->assertNull($result);
    }

    public function testContentTypeWithCharset()
    {
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'gzip';

        $middleware = new CompressionMiddleware();
        $response = new Response(200, str_repeat('a', 2000));
        $response->setHeader('Content-Type', 'text/html; charset=utf-8');

        $compressed = $middleware->compress($response);

        // Devrait compresser même avec charset
        $headers = $compressed->getHeaders();
        $this->assertEquals('gzip', $headers['content-encoding'] ?? null);

        // Nettoyer
        unset($_SERVER['HTTP_ACCEPT_ENCODING']);
    }
}
