<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\ErrorHandler;
use JulienLinard\Core\Application;
use JulienLinard\Core\Exceptions\NotFoundException;
use JulienLinard\Core\Exceptions\ValidationException;
use JulienLinard\Router\Response;

class ErrorHandlerTest extends TestCase
{
    private string $testPath;
    private Application $app;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-error-test';
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
        
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $this->app = Application::create($this->testPath);
        $this->app->setViewsPath($this->testPath . '/views');
        
        // Créer le répertoire views/errors
        $errorsPath = $this->testPath . '/views/errors';
        if (!is_dir($errorsPath)) {
            mkdir($errorsPath, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        $this->app->getContainer()->flush();
        
        // Réinitialiser l'instance singleton
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
    }

    public function testHandleNotFoundException()
    {
        $errorHandler = new ErrorHandler($this->app, null, false);
        $exception = new NotFoundException('Resource not found');
        
        $response = $errorHandler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Page non trouvée', $response->getContent());
    }

    public function testHandleValidationException()
    {
        $errorHandler = new ErrorHandler($this->app, null, false);
        $errors = ['email' => 'Invalid email', 'password' => 'Too short'];
        $exception = new ValidationException('Validation failed', $errors);
        
        $response = $errorHandler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(422, $response->getStatusCode());
        $this->assertStringContainsString('Erreur de validation', $response->getContent());
    }

    public function testHandleGenericException()
    {
        $errorHandler = new ErrorHandler($this->app, null, false);
        $exception = new \RuntimeException('Generic error');
        
        $response = $errorHandler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString('Erreur serveur', $response->getContent());
    }

    public function testHandleExceptionWithDebugMode()
    {
        $errorHandler = new ErrorHandler($this->app, null, true);
        $exception = new \RuntimeException('Debug error message');
        
        $response = $errorHandler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('Debug error message', $response->getContent());
    }

    public function testHandleExceptionWithoutDebugMode()
    {
        $errorHandler = new ErrorHandler($this->app, null, false);
        $exception = new \RuntimeException('Debug error message');
        
        $response = $errorHandler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringNotContainsString('Debug error message', $response->getContent());
        $this->assertStringContainsString('Une erreur est survenue', $response->getContent());
    }

    public function testCustomErrorPage()
    {
        // Créer une vue d'erreur personnalisée
        $errorViewPath = $this->testPath . '/views/errors/404.html.php';
        file_put_contents($errorViewPath, 'Custom 404 Page: <?= htmlspecialchars($message) ?>');
        
        $errorHandler = new ErrorHandler($this->app, null, false);
        $exception = new NotFoundException('Resource not found');
        
        $response = $errorHandler->handle($exception);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertStringContainsString('Custom 404 Page', $response->getContent());
        $this->assertStringContainsString('Resource not found', $response->getContent());
    }
}
