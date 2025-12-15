<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Application;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests pour la régénération de session avec intervalle de 15 minutes
 */
class SessionRegenerationTest extends TestCase
{
    private string $testPath;
    private Application $app;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-session-regen-test-' . uniqid();
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
        
        // Nettoyer la session avant chaque test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        
        // Réinitialiser l'instance pour ce test
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $this->app = Application::create($this->testPath);
    }

    protected function tearDown(): void
    {
        // Nettoyer la session après chaque test
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        $this->app->getContainer()->flush();
        
        // Réinitialiser l'instance singleton
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
    }

    /**
     * Test que la constante SESSION_REGENERATION_INTERVAL est bien à 900 secondes (15 minutes)
     */
    public function testSessionRegenerationIntervalConstant(): void
    {
        $reflection = new ReflectionClass(Application::class);
        $constant = $reflection->getConstant('SESSION_REGENERATION_INTERVAL');
        
        $this->assertEquals(900, $constant, 'SESSION_REGENERATION_INTERVAL devrait être 900 secondes (15 minutes)');
        $this->assertEquals(15 * 60, $constant, 'SESSION_REGENERATION_INTERVAL devrait être 15 minutes');
    }

    /**
     * Test que la session est régénérée à la première initialisation
     */
    public function testSessionRegeneratedOnFirstInitialization(): void
    {
        // Démarrer l'application (démarre la session)
        $this->app->start();
        
        // Vérifier que la session est initialisée
        $this->assertTrue(isset($_SESSION['_initialized']), 'La session devrait être initialisée');
        $this->assertTrue(isset($_SESSION['_created_at']), 'La date de création devrait être enregistrée');
        $this->assertTrue(isset($_SESSION['_last_regen']), 'La date de dernière régénération devrait être enregistrée');
        
        // Vérifier que les timestamps sont cohérents
        $this->assertGreaterThanOrEqual(time() - 1, $_SESSION['_created_at']);
        $this->assertGreaterThanOrEqual(time() - 1, $_SESSION['_last_regen']);
    }

    /**
     * Test que la session n'est pas régénérée si l'intervalle n'est pas écoulé
     */
    public function testSessionNotRegeneratedBeforeInterval(): void
    {
        // Démarrer l'application (première initialisation)
        $this->app->start();
        
        $firstSessionId = session_id();
        $firstLastRegen = $_SESSION['_last_regen'];
        
        // Simuler une nouvelle requête (mais moins de 15 minutes se sont écoulées)
        // On simule en modifiant manuellement le timestamp pour qu'il soit récent
        $_SESSION['_last_regen'] = time() - 100; // Il y a 100 secondes (bien moins que 900)
        
        // Sauvegarder le timestamp modifié
        $expectedLastRegen = $_SESSION['_last_regen'];
        
        // Réinitialiser l'application pour simuler une nouvelle requête
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('started');
        $property->setAccessible(true);
        $property->setValue($this->app, false);
        
        // Redémarrer (ne devrait pas régénérer car moins de 15 minutes)
        $this->app->start();
        
        $secondSessionId = session_id();
        $secondLastRegen = $_SESSION['_last_regen'];
        
        // L'ID de session ne devrait pas avoir changé
        $this->assertEquals($firstSessionId, $secondSessionId, 'L\'ID de session ne devrait pas changer si moins de 15 minutes');
        // Le timestamp de dernière régénération ne devrait pas avoir changé (devrait rester celui qu'on a modifié)
        $this->assertEquals($expectedLastRegen, $secondLastRegen, 'Le timestamp ne devrait pas changer si moins de 15 minutes');
    }

    /**
     * Test que la session est régénérée si l'intervalle est écoulé
     */
    public function testSessionRegeneratedAfterInterval(): void
    {
        // Démarrer l'application (première initialisation)
        $this->app->start();
        
        $firstSessionId = session_id();
        
        // Simuler que 15 minutes se sont écoulées
        $_SESSION['_last_regen'] = time() - 901; // Il y a 901 secondes (plus que 900)
        
        // Réinitialiser l'application pour simuler une nouvelle requête
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('started');
        $property->setAccessible(true);
        $property->setValue($this->app, false);
        
        // Redémarrer (devrait régénérer car plus de 15 minutes)
        $this->app->start();
        
        $secondSessionId = session_id();
        $secondLastRegen = $_SESSION['_last_regen'];
        
        // L'ID de session devrait avoir changé
        $this->assertNotEquals($firstSessionId, $secondSessionId, 'L\'ID de session devrait changer après 15 minutes');
        // Le timestamp de dernière régénération devrait avoir été mis à jour
        $this->assertGreaterThanOrEqual(time() - 1, $secondLastRegen, 'Le timestamp devrait être mis à jour');
        $this->assertLessThanOrEqual(time() + 1, $secondLastRegen, 'Le timestamp devrait être récent');
    }

    /**
     * Test que la méthode manageSessionRegeneration utilise bien la constante
     */
    public function testManageSessionRegenerationUsesConstant(): void
    {
        $reflection = new ReflectionClass(Application::class);
        $method = $reflection->getMethod('manageSessionRegeneration');
        $method->setAccessible(true);
        
        // Démarrer la session
        $this->app->start();
        
        // Vérifier que la méthode utilise bien la constante
        $sourceCode = file_get_contents(__DIR__ . '/../src/Core/Application.php');
        
        $this->assertStringContainsString('SESSION_REGENERATION_INTERVAL', $sourceCode, 
            'La méthode devrait utiliser la constante SESSION_REGENERATION_INTERVAL');
        $this->assertStringContainsString('time() - $lastRegen > self::SESSION_REGENERATION_INTERVAL', $sourceCode,
            'La méthode devrait comparer avec la constante');
    }

    /**
     * Test que la régénération est appelée dans start()
     */
    public function testRegenerationCalledInStart(): void
    {
        $sourceCode = file_get_contents(__DIR__ . '/../src/Core/Application.php');
        
        // Vérifier que manageSessionRegeneration est appelée dans start()
        $this->assertStringContainsString('$this->manageSessionRegeneration()', $sourceCode,
            'manageSessionRegeneration() devrait être appelée dans start()');
    }

    /**
     * Test que la régénération utilise session_regenerate_id(true)
     */
    public function testRegenerationUsesDeleteOldSession(): void
    {
        $sourceCode = file_get_contents(__DIR__ . '/../src/Core/Application.php');
        
        // Vérifier que session_regenerate_id(true) est utilisé
        $this->assertStringContainsString('session_regenerate_id(true)', $sourceCode,
            'La régénération devrait utiliser session_regenerate_id(true) pour supprimer l\'ancienne session');
    }

    /**
     * Test que la régénération fonctionne avec _created_at comme fallback
     */
    public function testRegenerationUsesCreatedAtAsFallback(): void
    {
        // Démarrer l'application
        $this->app->start();
        
        // Simuler un cas où _last_regen n'existe pas mais _created_at existe
        unset($_SESSION['_last_regen']);
        $_SESSION['_created_at'] = time() - 901; // Il y a 901 secondes
        
        // Réinitialiser l'application
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('started');
        $property->setAccessible(true);
        $property->setValue($this->app, false);
        
        $firstSessionId = session_id();
        
        // Redémarrer (devrait régénérer car utilise _created_at comme fallback)
        $this->app->start();
        
        $secondSessionId = session_id();
        
        // L'ID devrait avoir changé
        $this->assertNotEquals($firstSessionId, $secondSessionId, 
            'L\'ID devrait changer en utilisant _created_at comme fallback');
        // _last_regen devrait maintenant exister
        $this->assertTrue(isset($_SESSION['_last_regen']), '_last_regen devrait être créé après régénération');
    }
}

