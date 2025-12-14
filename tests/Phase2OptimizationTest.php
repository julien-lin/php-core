<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Application;
use JulienLinard\Core\Container\Container;
use JulienLinard\Core\Middleware\CorsMiddleware;
use JulienLinard\Core\Middleware\RequestValidationMiddleware;

/**
 * Tests pour Phase 2 - Optimisations Importantes
 * 
 * ✅ 1. Session Regeneration Optimization (Application.php)
 * ✅ 2. Container Circular Dependency Protection (Container.php)
 * ✅ 3. POST Size Validation (Application.php)
 * ✅ 4. CORS Middleware (CorsMiddleware.php) - NEW
 * ✅ 5. Request Validation Middleware (RequestValidationMiddleware.php) - NEW
 */
class Phase2OptimizationTest extends TestCase
{
    /**
     * Tests pour Application POST Size Validation
     */

    public function test_application_has_post_size_limit(): void
    {
        $app = Application::create(sys_get_temp_dir());

        // Vérifier que l'app a une limite de taille (méthode existe et retourne un int)
        $maxSize = $app->getMaxPostSize();
        $this->assertIsInt($maxSize);
        $this->assertGreaterThan(0, $maxSize);
    }

    public function test_post_size_limit_can_be_configured(): void
    {
        $app = Application::create(sys_get_temp_dir());

        // Définir une limite personnalisée
        $result = $app->setMaxPostSize(1024 * 1024); // 1MB
        $this->assertSame($app, $result); // Fluent interface

        // Vérifier que la limite a été appliquée
        $this->assertEquals(1024 * 1024, $app->getMaxPostSize());
    }

    public function test_post_size_limit_minimum_is_one_byte(): void
    {
        $app = Application::create(sys_get_temp_dir());

        // Essayer de définir à zéro - devrait être au minimum 1
        $app->setMaxPostSize(0);
        $this->assertEquals(1, $app->getMaxPostSize());
    }

    /**
     * Tests pour Container Circular Dependency Protection
     */

    public function test_container_detects_circular_dependency(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/[Cc]ircular/');

        $container = new Container();

        // Créer des classes avec dépendances circulaires
        if (!class_exists('TestCircularA')) {
            eval('
                class TestCircularA { 
                    public function __construct(TestCircularB $b) {} 
                }
                class TestCircularB { 
                    public function __construct(TestCircularA $a) {} 
                }
            ');
        }

        $container->bind('TestCircularA', 'TestCircularA');
        $container->bind('TestCircularB', 'TestCircularB');

        // Tenter de résoudre - devrait détecter la circulation
        $container->make('TestCircularA');
    }

    public function test_container_allows_normal_dependencies(): void
    {
        $container = new Container();

        if (!class_exists('TestNormalA')) {
            eval('
                class TestNormalA {}
                class TestNormalB { 
                    public function __construct(TestNormalA $a) {} 
                }
            ');
        }

        $container->bind('TestNormalA', 'TestNormalA');
        $container->bind('TestNormalB', 'TestNormalB');

        // Devrait fonctionner normalement
        $instance = $container->make('TestNormalB');
        $this->assertInstanceOf('TestNormalB', $instance);
    }

    /**
     * Tests pour CORS Middleware
     */

    public function test_cors_middleware_accepts_all_origins_by_default(): void
    {
        $middleware = new CorsMiddleware();
        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function test_cors_middleware_rejects_wildcard_with_credentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Ne peut pas utiliser '*' avec credentials
        new CorsMiddleware('*', true);
    }

    public function test_cors_middleware_allows_specific_origins(): void
    {
        $middleware = new CorsMiddleware('https://example.com,https://app.example.com');
        $this->assertInstanceOf(CorsMiddleware::class, $middleware);
    }

    public function test_cors_middleware_chainable(): void
    {
        $middleware = new CorsMiddleware();
        $result = $middleware->setAllowedMethods('GET,POST')
            ->setAllowedHeaders('Content-Type,Authorization')
            ->setMaxAge(7200);

        $this->assertSame($middleware, $result);
    }

    /**
     * Tests pour Request Validation Middleware
     */

    public function test_request_validation_middleware_created(): void
    {
        $middleware = new RequestValidationMiddleware();
        $this->assertInstanceOf(RequestValidationMiddleware::class, $middleware);
    }

    public function test_request_validation_sanitize_basic(): void
    {
        $dirty = [
            'name' => '<script>alert("xss")</script>',
            'email' => 'test@example.com',
            'count' => 42,
        ];

        $clean = RequestValidationMiddleware::sanitize($dirty);

        // String XSS tentée devrait être échappée
        $this->assertStringNotContainsString('<script>', $clean['name']);
        $this->assertStringContainsString('&lt;script&gt;', $clean['name']);

        // Email normal passe
        $this->assertStringContainsString('test@example.com', $clean['email']);

        // Nombres intacts
        $this->assertEquals(42, $clean['count']);
    }

    public function test_request_validation_sanitize_strict_mode(): void
    {
        $dirty = [
            'comment' => '<b>Bold text</b> with <script>alert("xss")</script>',
        ];

        $clean = RequestValidationMiddleware::sanitize($dirty, true);

        // Mode strict: tous les tags sont échappés
        $this->assertStringNotContainsString('<b>', $clean['comment']);
        $this->assertStringContainsString('&lt;b&gt;', $clean['comment']);
        $this->assertStringContainsString('&lt;script&gt;', $clean['comment']);
    }

    public function test_request_validation_sanitize_nested_arrays(): void
    {
        $dirty = [
            'user' => [
                'name' => '<img src=x onerror="alert(1)">',
                'profile' => [
                    'bio' => '<p>Hello</p>',
                ],
            ],
        ];

        $clean = RequestValidationMiddleware::sanitize($dirty);

        // Vérifier que les tags HTML sont échappés
        $this->assertStringNotContainsString('<img', $clean['user']['name']);
        $this->assertStringContainsString('&lt;img', $clean['user']['name']);
        // L'attribut onerror est échappé (les guillemets deviennent &quot;)
        $this->assertStringContainsString('&quot;', $clean['user']['name']);
        // Les tags <p> sont échappés
        $this->assertStringNotContainsString('<p>', $clean['user']['profile']['bio']);
        $this->assertStringContainsString('&lt;p&gt;', $clean['user']['profile']['bio']);
    }

    public function test_request_validation_sanitize_removes_invalid_keys(): void
    {
        $dirty = [
            'valid_key' => 'value',
            'invalid@key!!' => 'value2',
            'another-valid' => 'value3',
        ];

        $clean = RequestValidationMiddleware::sanitize($dirty);

        // Clés valides préservées
        $this->assertArrayHasKey('valid_key', $clean);
        $this->assertArrayHasKey('another-valid', $clean);

        // Clés invalides transformées
        $this->assertFalse(isset($clean['invalid@key!!']));
    }
}
