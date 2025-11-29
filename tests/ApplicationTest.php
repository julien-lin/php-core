<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Application;

class ApplicationTest extends TestCase
{
    private string $testPath;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-test';
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Nettoyer après les tests
        $app = Application::getInstance();
        if ($app !== null) {
            $app->getContainer()->flush();
        }
        
        // Réinitialiser l'instance singleton pour les tests
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
    }

    public function testCreateApplication(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        
        $this->assertInstanceOf(Application::class, $app);
        $this->assertEquals($this->testPath, $app->getBasePath());
    }

    public function testApplicationSingleton(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app1 = Application::create($this->testPath);
        $app2 = Application::getInstance();
        
        $this->assertSame($app1, $app2);
    }

    public function testGetRouter(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $router = $app->getRouter();
        
        $this->assertInstanceOf(\JulienLinard\Router\Router::class, $router);
    }

    public function testGetContainer(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $container = $app->getContainer();
        
        $this->assertInstanceOf(\JulienLinard\Core\Container\Container::class, $container);
    }

    public function testSetViewsPath(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $customPath = $this->testPath . '/custom-views';
        
        $app->setViewsPath($customPath);
        
        $this->assertEquals($customPath, $app->getViewsPath());
    }

    public function testGetInstanceOrCreate(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::getInstanceOrCreate($this->testPath);
        
        $this->assertInstanceOf(Application::class, $app);
        $this->assertEquals($this->testPath, $app->getBasePath());
    }

    public function testGetInstanceOrCreateWithExistingInstance(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app1 = Application::create($this->testPath);
        $app2 = Application::getInstanceOrCreate();
        
        $this->assertSame($app1, $app2);
    }

    public function testGetInstanceOrCreateThrowsExceptionWithoutBasePath(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('L\'application n\'a pas été initialisée');
        
        Application::getInstanceOrCreate();
    }

    public function testGetInstanceOrFail(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        Application::create($this->testPath);
        $app = Application::getInstanceOrFail();
        
        $this->assertInstanceOf(Application::class, $app);
    }

    public function testGetInstanceOrFailThrowsException(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('L\'application n\'a pas été initialisée');
        
        Application::getInstanceOrFail();
    }

    public function testGetConfig(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $config = $app->getConfig();
        
        $this->assertInstanceOf(\JulienLinard\Core\Config\Config::class, $config);
    }

    public function testGetEvents(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $events = $app->getEvents();
        
        $this->assertInstanceOf(\JulienLinard\Core\Events\EventDispatcher::class, $events);
    }

    public function testSetAndGetPartialsPath(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $customPath = $this->testPath . '/custom-partials';
        
        $app->setPartialsPath($customPath);
        
        $this->assertEquals($customPath, $app->getPartialsPath());
    }

    public function testIsStarted(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        
        $this->assertFalse($app->isStarted());
        
        $app->start();
        
        $this->assertTrue($app->isStarted());
    }

    public function testGetErrorHandler(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $errorHandler = $app->getErrorHandler();
        
        $this->assertInstanceOf(\JulienLinard\Core\ErrorHandler::class, $errorHandler);
    }

    public function testSetErrorHandler(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $customErrorHandler = new \JulienLinard\Core\ErrorHandler($app);
        
        $app->setErrorHandler($customErrorHandler);
        
        $this->assertSame($customErrorHandler, $app->getErrorHandler());
    }

    public function testLoadConfig(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        
        // Créer un répertoire de configuration
        $configPath = $this->testPath . '/config';
        if (!is_dir($configPath)) {
            mkdir($configPath, 0777, true);
        }
        
        // Créer un fichier de configuration de test
        file_put_contents($configPath . '/app.php', '<?php return ["name" => "TestApp", "debug" => true];');
        file_put_contents($configPath . '/database.php', '<?php return ["host" => "localhost", "port" => 3306];');
        
        // Charger la configuration
        $app->loadConfig('config');
        
        // Vérifier que la configuration a été chargée
        $config = $app->getConfig();
        $this->assertEquals('TestApp', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertEquals('localhost', $config->get('database.host'));
        $this->assertEquals(3306, $config->get('database.port'));
        
        // Nettoyer
        unlink($configPath . '/app.php');
        unlink($configPath . '/database.php');
        rmdir($configPath);
    }

    public function testLoadEnv(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        
        // Créer un fichier .env de test
        $envContent = "APP_NAME=TestApp\nAPP_DEBUG=true\nDB_HOST=localhost\n";
        file_put_contents($this->testPath . '/.env', $envContent);
        
        // Charger le .env
        $app->loadEnv('.env');
        
        // Vérifier que les variables d'environnement ont été chargées
        $this->assertEquals('TestApp', $_ENV['APP_NAME'] ?? null);
        $this->assertEquals('true', $_ENV['APP_DEBUG'] ?? null);
        $this->assertEquals('localhost', $_ENV['DB_HOST'] ?? null);
        
        // Nettoyer
        unlink($this->testPath . '/.env');
    }

    public function testEventsAreTriggered(): void
    {
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $events = $app->getEvents();
        
        // Suivre les événements déclenchés
        $triggered = false;
        
        $events->listen('custom.event', function() use (&$triggered) {
            $triggered = true;
        });
        
        // Déclencher l'événement
        $events->dispatch('custom.event');
        
        // Vérifier que l'événement a été déclenché
        $this->assertTrue($triggered);
    }
}

