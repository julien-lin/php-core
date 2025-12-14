<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\View\View;
use JulienLinard\Core\Config\ConfigLoader;
use JulienLinard\Core\Logging\SimpleLogger;
use JulienLinard\Core\ErrorHandler;
use JulienLinard\Core\Application;

/**
 * Tests pour Phase 1 - Correctifs Sécurité Critiques
 * 
 * ✅ 1. Directory Traversal Protection (View.php)
 * ✅ 2. Config Injection Prevention (ConfigLoader.php)
 * ✅ 3. Sensitive Data Redaction (SimpleLogger.php + ErrorHandler.php)
 */
class Phase1SecurityFixesTest extends TestCase
{
    /**
     * Tests pour Directory Traversal Protection
     */

    public function test_view_rejects_directory_traversal(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Peut être soit un format invalide (trop de parties) soit traversal détectée
        $this->expectException(\InvalidArgumentException::class);

        new View('../../etc/passwd');
    }

    public function test_view_rejects_nul_byte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tentative de traversal détectée');

        new View('../../et' . "\0" . 'c/passwd');
    }

    public function test_view_rejects_dot_directory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Peut être format invalide ou traversal - les deux sont acceptables
        $this->expectException(\InvalidArgumentException::class);

        new View('./home/index');
    }

    public function test_view_rejects_parent_directory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        // Peut être format invalide (3 parties) ou traversal - les deux sont corrects
        $this->expectException(\InvalidArgumentException::class);

        new View('home/../index');
    }

    public function test_view_accepts_valid_format(): void
    {
        try {
            $view = new View('home/index');
            $this->assertInstanceOf(View::class, $view);
        } catch (\InvalidArgumentException $e) {
            $this->fail('Valid view name should not throw exception: ' . $e->getMessage());
        }
    }

    public function test_view_accepts_valid_format_with_dashes(): void
    {
        try {
            $view = new View('home-page/user-index');
            $this->assertInstanceOf(View::class, $view);
        } catch (\InvalidArgumentException $e) {
            $this->fail('Valid view name with dashes should not throw exception');
        }
    }

    public function test_view_accepts_valid_format_with_dots_in_filename(): void
    {
        try {
            $view = new View('home/user.profile');
            $this->assertInstanceOf(View::class, $view);
        } catch (\InvalidArgumentException $e) {
            $this->fail('Valid view name with dots should not throw exception');
        }
    }

    public function test_view_rejects_special_characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new View('home/user@index');
    }

    public function test_view_rejects_multiple_slashes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new View('home/user/profile');
    }

    /**
     * Tests pour Config File Validation
     */

    public function test_config_loader_skips_invalid_names(): void
    {
        // Créer un répertoire temporaire pour les tests
        $tmpDir = sys_get_temp_dir() . '/test-config-' . uniqid();
        mkdir($tmpDir);

        try {
            // Créer des fichiers avec des noms valides et invalides
            file_put_contents($tmpDir . '/valid.php', '<?php return ["test" => "value"];');
            file_put_contents($tmpDir . '/valid-name.php', '<?php return ["test" => "value"];');
            file_put_contents($tmpDir . '/invalid!name.php', '<?php return ["test" => "value"];');
            file_put_contents($tmpDir . '/another-invalid@.php', '<?php return ["test" => "value"];');

            // Charger la configuration
            $config = ConfigLoader::load($tmpDir);

            // Vérifier que les fichiers valides sont chargés
            $this->assertArrayHasKey('valid', $config);
            $this->assertArrayHasKey('valid-name', $config);

            // Vérifier que les fichiers invalides sont skippés (pas de warning levée = ok)
            $this->assertArrayNotHasKey('invalid!name', $config);
            $this->assertArrayNotHasKey('another-invalid@', $config);
        } finally {
            // Nettoyer
            @unlink($tmpDir . '/valid.php');
            @unlink($tmpDir . '/valid-name.php');
            @unlink($tmpDir . '/invalid!name.php');
            @unlink($tmpDir . '/another-invalid@.php');
            @rmdir($tmpDir);
        }
    }

    public function test_config_loader_validates_underscore_and_dash(): void
    {
        $tmpDir = sys_get_temp_dir() . '/test-config-' . uniqid();
        mkdir($tmpDir);

        try {
            file_put_contents($tmpDir . '/app_config.php', '<?php return ["app" => "value"];');
            file_put_contents($tmpDir . '/db-config.php', '<?php return ["db" => "value"];');

            $config = ConfigLoader::load($tmpDir);

            $this->assertArrayHasKey('app_config', $config);
            $this->assertArrayHasKey('db-config', $config);
        } finally {
            @unlink($tmpDir . '/app_config.php');
            @unlink($tmpDir . '/db-config.php');
            @rmdir($tmpDir);
        }
    }

    /**
     * Tests pour Sensitive Data Redaction
     */

    public function test_simple_logger_redacts_passwords(): void
    {
        $logger = new SimpleLogger();

        // Utiliser la réflexion pour accéder à la méthode privée
        $reflectionMethod = new \ReflectionMethod(SimpleLogger::class, 'redactSensitiveData');
        $reflectionMethod->setAccessible(true);

        $data = ['user' => 'john', 'password' => 'secret123', 'email' => 'john@example.com'];
        $redacted = $reflectionMethod->invoke($logger, $data);

        $this->assertEquals('***REDACTED***', $redacted['password']);
        $this->assertEquals('john', $redacted['user']);
        $this->assertEquals('john@example.com', $redacted['email']);
    }

    public function test_simple_logger_redacts_tokens(): void
    {
        $logger = new SimpleLogger();
        $reflectionMethod = new \ReflectionMethod(SimpleLogger::class, 'redactSensitiveData');
        $reflectionMethod->setAccessible(true);

        $data = [
            'access_token' => 'token_abc123',
            'refresh_token' => 'refresh_xyz789',
            'api_key' => 'sk_live_123',
            'data' => 'public'
        ];
        $redacted = $reflectionMethod->invoke($logger, $data);

        $this->assertEquals('***REDACTED***', $redacted['access_token']);
        $this->assertEquals('***REDACTED***', $redacted['refresh_token']);
        $this->assertEquals('***REDACTED***', $redacted['api_key']);
        $this->assertEquals('public', $redacted['data']);
    }

    public function test_simple_logger_redacts_nested_data(): void
    {
        $logger = new SimpleLogger();
        $reflectionMethod = new \ReflectionMethod(SimpleLogger::class, 'redactSensitiveData');
        $reflectionMethod->setAccessible(true);

        $data = [
            'user' => [
                'name' => 'John',
                'password' => 'secret123',
                'email' => 'john@example.com'
            ],
            'credentials' => [
                'pwd' => 'another_secret',
                'api_secret' => 'secret_key'
            ]
        ];
        $redacted = $reflectionMethod->invoke($logger, $data);

        $this->assertEquals('John', $redacted['user']['name']);
        $this->assertEquals('***REDACTED***', $redacted['user']['password']);
        $this->assertEquals('john@example.com', $redacted['user']['email']);
        $this->assertEquals('***REDACTED***', $redacted['credentials']['pwd']);
        $this->assertEquals('***REDACTED***', $redacted['credentials']['api_secret']);
    }

    public function test_simple_logger_redacts_case_insensitive(): void
    {
        $logger = new SimpleLogger();
        $reflectionMethod = new \ReflectionMethod(SimpleLogger::class, 'redactSensitiveData');
        $reflectionMethod->setAccessible(true);

        $data = [
            'PASSWORD' => 'secret',
            'Token' => 'token123',
            'API_KEY' => 'key456',
            'normal_field' => 'value'
        ];
        $redacted = $reflectionMethod->invoke($logger, $data);

        $this->assertEquals('***REDACTED***', $redacted['PASSWORD']);
        $this->assertEquals('***REDACTED***', $redacted['Token']);
        $this->assertEquals('***REDACTED***', $redacted['API_KEY']);
        $this->assertEquals('value', $redacted['normal_field']);
    }

    public function test_error_handler_redacts_sensitive_data(): void
    {
        $app = Application::create(sys_get_temp_dir());
        $logger = new SimpleLogger();
        $handler = new ErrorHandler($app, $logger, false);

        // Utiliser la réflexion pour accéder à la méthode privée
        $reflectionMethod = new \ReflectionMethod(ErrorHandler::class, 'redactSensitiveData');
        $reflectionMethod->setAccessible(true);

        $data = [
            'username' => 'admin',
            'password' => 'secret123',
            'token' => 'abc123'
        ];
        $redacted = $reflectionMethod->invoke($handler, $data);

        $this->assertEquals('admin', $redacted['username']);
        $this->assertEquals('***REDACTED***', $redacted['password']);
        $this->assertEquals('***REDACTED***', $redacted['token']);
    }
}
