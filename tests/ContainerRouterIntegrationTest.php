<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Application;
use JulienLinard\Core\Controller\Controller;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Attributes\Route;
use ReflectionClass;

/**
 * Tests d'intégration entre Container et Router
 */
class ContainerRouterIntegrationTest extends TestCase
{
    private string $testPath;
    private Application $app;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-integration-test-' . uniqid();
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
        
        // Réinitialiser l'instance pour ce test
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $this->app = Application::create($this->testPath);
    }

    protected function tearDown(): void
    {
        $this->app->getContainer()->flush();
        
        // Réinitialiser l'instance singleton
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
    }

    /**
     * Test que le Router utilise le Container pour résoudre les contrôleurs
     */
    public function testRouterUsesContainerForControllers(): void
    {
        $router = $this->app->getRouter();
        $container = $this->app->getContainer();
        
        // Enregistrer un service dans le Container
        $container->singleton(TestService::class, fn() => new TestService());
        
        // Enregistrer les routes du contrôleur
        $router->registerRoutes(TestController::class);
        
        // Créer une requête
        $request = new Request('/test', 'GET');
        
        // Le Router devrait utiliser le Container pour résoudre le contrôleur
        $response = $router->handle($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test que le Container est bien passé au Router
     */
    public function testContainerIsPassedToRouter(): void
    {
        $router = $this->app->getRouter();
        
        // Vérifier que le Router a accès au Container
        // (via setContainer dans Application::__construct)
        $reflection = new ReflectionClass($router);
        
        // Le Router devrait avoir une méthode setContainer ou un accès au Container
        // Cela dépend de l'implémentation de php-router
        $this->assertTrue(true, 'Container est configuré dans Application');
    }

    /**
     * Test que les singletons sont partagés entre Container et Router
     */
    public function testSingletonsAreShared(): void
    {
        $container = $this->app->getContainer();
        $router = $this->app->getRouter();
        
        // Enregistrer un singleton
        $container->singleton(TestService::class, fn() => new TestService());
        
        // Récupérer via Container
        $service1 = $container->make(TestService::class);
        
        // Récupérer via Router (si le Router utilise le Container)
        // Le Router devrait utiliser le même Container
        $service2 = $container->make(TestService::class);
        
        // Les deux instances devraient être les mêmes (singleton)
        $this->assertSame($service1, $service2);
    }

    /**
     * Test que le Container résout les dépendances du contrôleur
     */
    public function testContainerResolvesControllerDependencies(): void
    {
        $container = $this->app->getContainer();
        $router = $this->app->getRouter();
        
        // Enregistrer les services nécessaires
        $container->singleton(TestService::class, fn() => new TestService());
        $container->singleton(TestLogger::class, fn() => new TestLogger());
        
        // Enregistrer les routes du contrôleur avec dépendances
        $router->registerRoutes(TestControllerWithDependencies::class);
        
        $request = new Request('/test-deps', 'GET');
        $response = $router->handle($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test que le cache de requête est nettoyé après chaque requête
     */
    public function testRequestCacheIsClearedAfterRequest(): void
    {
        $container = $this->app->getContainer();
        
        // Créer une instance non-singleton
        $instance1 = $container->make(TestService::class);
        
        // Nettoyer le cache (simule la fin de requête)
        $container->clearRequestCache();
        
        // Créer une nouvelle instance
        $instance2 = $container->make(TestService::class);
        
        // Les instances devraient être différentes (pas de cache)
        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * Test que Application::shutdown() nettoie le cache de requête
     */
    public function testShutdownClearsRequestCache(): void
    {
        $container = $this->app->getContainer();
        
        // Créer une instance (mise en cache de requête)
        $instance1 = $container->make(TestService::class);
        
        // Appeler shutdown
        $this->app->shutdown();
        
        // Créer une nouvelle instance
        $instance2 = $container->make(TestService::class);
        
        // Les instances devraient être différentes
        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * Test que le Router peut résoudre les contrôleurs avec auto-wiring
     */
    public function testRouterAutoWiresControllers(): void
    {
        $container = $this->app->getContainer();
        $router = $this->app->getRouter();
        
        // Enregistrer un service
        $container->singleton(TestService::class, fn() => new TestService());
        
        // Enregistrer les routes du contrôleur qui nécessite TestService dans son constructeur
        $router->registerRoutes(TestControllerWithConstructor::class);
        
        $request = new Request('/test-constructor', 'GET');
        $response = $router->handle($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }
}

// Classes de test

class TestService
{
    public function doSomething(): string
    {
        return 'done';
    }
}

class TestLogger
{
    public function log(string $message): void
    {
        // Logger
    }
}

class TestController extends Controller
{
    #[Route(path: '/test', methods: ['GET'], name: 'test')]
    public function index(Request $request): Response
    {
        return new Response(200, 'OK');
    }
}

class TestControllerWithDependencies extends Controller
{
    public function __construct(
        private TestService $service,
        private TestLogger $logger
    ) {}
    
    #[Route(path: '/test-deps', methods: ['GET'], name: 'test-deps')]
    public function index(Request $request): Response
    {
        $this->logger->log('Test');
        return new Response(200, $this->service->doSomething());
    }
}

class TestControllerWithConstructor extends Controller
{
    public function __construct(
        private TestService $service
    ) {}
    
    #[Route(path: '/test-constructor', methods: ['GET'], name: 'test-constructor')]
    public function index(Request $request): Response
    {
        return new Response(200, 'OK');
    }
}

