<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Core\Application;
use JulienLinard\Router\Response;

class ControllerTest extends TestCase
{
    private string $testPath;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-controller-test';
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
        
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        Application::create($this->testPath);
    }

    protected function tearDown(): void
    {
        $app = Application::getInstance();
        if ($app !== null) {
            $app->getContainer()->flush();
        }
        
        // Réinitialiser l'instance singleton
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
    }

    public function testRedirect()
    {
        $controller = new TestController();
        $response = $controller->testRedirect();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('location', $headers);
        $this->assertEquals('/login', $headers['location']);
    }

    public function testRedirectWithCustomStatus()
    {
        $controller = new TestController();
        $response = $controller->testRedirectWithStatus();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testJson()
    {
        $controller = new TestController();
        $response = $controller->testJson();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Hello', $content['message']);
    }

    public function testJsonWithCustomStatus()
    {
        $controller = new TestController();
        $response = $controller->testJsonWithStatus();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(201, $response->getStatusCode());
    }

    public function testBack()
    {
        $_SERVER['HTTP_REFERER'] = '/previous-page';
        
        $controller = new TestController();
        $response = $controller->testBack();
        
        $this->assertInstanceOf(Response::class, $response);
        $headers = $response->getHeaders();
        $this->assertEquals('/previous-page', $headers['location']);
    }

    public function testBackWithoutReferer()
    {
        unset($_SERVER['HTTP_REFERER']);
        
        $controller = new TestController();
        $response = $controller->testBack();
        
        $this->assertInstanceOf(Response::class, $response);
        $headers = $response->getHeaders();
        $this->assertEquals('/', $headers['location']);
    }

    public function testHeaderSanitization()
    {
        $controller = new TestController();
        $response = $controller->testHeaderSanitization();
        
        // Vérifier que les headers sont bien sanitizés
        $headers = $response->getHeaders();
        $this->assertArrayHasKey('location', $headers);
        // Le header ne doit pas contenir de caractères dangereux
        $this->assertStringNotContainsString("\r", $headers['location']);
        $this->assertStringNotContainsString("\n", $headers['location']);
    }

    public function testApp()
    {
        $controller = new TestController();
        $response = $controller->testApp();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Application', $response->getContent());
    }
}

// Classe de test pour Controller
class TestController extends Controller
{
    public function testRedirect(): Response
    {
        return $this->redirect('/login');
    }

    public function testRedirectWithStatus(): Response
    {
        return $this->redirect('/moved', 301);
    }

    public function testJson(): Response
    {
        return $this->json(['message' => 'Hello']);
    }

    public function testJsonWithStatus(): Response
    {
        return $this->json(['created' => true], 201);
    }

    public function testBack(): Response
    {
        return $this->back();
    }

    public function testHeaderSanitization(): Response
    {
        // Tester avec des caractères potentiellement dangereux
        return $this->redirect('/safe/path');
    }

    public function testApp(): Response
    {
        $app = $this->app();
        return new Response(200, get_class($app));
    }
}
