<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Middleware\CsrfMiddleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class CsrfMiddlewareTest extends TestCase
{
    private CsrfMiddleware $middleware;
    private string $sessionKey = '_csrf_token';

    protected function setUp(): void
    {
        // Nettoyer la session avant chaque test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        
        $this->middleware = new CsrfMiddleware();
    }

    protected function tearDown(): void
    {
        // Nettoyer la session après chaque test
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testGetToken()
    {
        $token1 = CsrfMiddleware::getToken();
        $token2 = CsrfMiddleware::getToken();
        
        $this->assertIsString($token1);
        $this->assertEquals(64, strlen($token1)); // 32 bytes = 64 hex chars
        $this->assertEquals($token1, $token2); // Même token dans la même session
    }

    public function testField()
    {
        $field = CsrfMiddleware::field('_token');
        
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    public function testHandleGetRequest()
    {
        $request = new Request('/test', 'GET');
        
        $response = $this->middleware->handle($request);
        
        // GET ne doit pas être bloqué, un token doit être généré
        $this->assertNull($response);
        $this->assertTrue(isset($_SESSION[$this->sessionKey]));
    }

    public function testHandlePostWithValidToken()
    {
        // Générer un token
        $token = CsrfMiddleware::getToken();
        
        // Simuler une requête POST avec le token dans les données POST
        $_POST['_token'] = $token;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request('/test', 'POST');
        
        $response = $this->middleware->handle($request);
        
        // Token valide, doit continuer
        $this->assertNull($response);
        
        // Nettoyer
        unset($_POST['_token'], $_SERVER['REQUEST_METHOD']);
    }

    public function testHandlePostWithInvalidToken()
    {
        // Générer un token
        CsrfMiddleware::getToken();
        
        // Simuler une requête POST avec un token invalide
        $_POST['_token'] = 'invalid-token';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request('/test', 'POST');
        
        $response = $this->middleware->handle($request);
        
        // Token invalide, doit retourner une erreur 403
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        
        // Nettoyer
        unset($_POST['_token'], $_SERVER['REQUEST_METHOD']);
    }

    public function testHandlePostWithMissingToken()
    {
        // Simuler une requête POST sans token
        $_SERVER['REQUEST_METHOD'] = 'POST';
        unset($_POST['_token']);
        $request = new Request('/test', 'POST');
        
        $response = $this->middleware->handle($request);
        
        // Token manquant, doit retourner une erreur 403
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
        
        // Nettoyer
        unset($_SERVER['REQUEST_METHOD']);
    }

    public function testHandlePostWithTokenInHeader()
    {
        // Générer un token
        $token = CsrfMiddleware::getToken();
        
        // Simuler une requête POST avec le token dans le header
        $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request('/test', 'POST');
        
        $response = $this->middleware->handle($request);
        
        // Token valide dans header, doit continuer
        $this->assertNull($response);
        
        // Nettoyer
        unset($_SERVER['HTTP_X_CSRF_TOKEN'], $_SERVER['REQUEST_METHOD']);
    }

    public function testTokenRegenerationAfterValidation()
    {
        // Générer un token initial
        $token1 = CsrfMiddleware::getToken();
        
        // Valider avec POST
        $_POST['_token'] = $token1;
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $request = new Request('/test', 'POST');
        $this->middleware->handle($request);
        
        // Un nouveau token doit être généré
        $token2 = $_SESSION[$this->sessionKey];
        $this->assertNotEquals($token1, $token2);
        
        // Nettoyer
        unset($_POST['_token'], $_SERVER['REQUEST_METHOD']);
    }
}
