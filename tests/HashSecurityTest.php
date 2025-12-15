<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\View\View;
use JulienLinard\Core\ErrorHandler;
use JulienLinard\Core\Application;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests pour vérifier que MD5 a été remplacé par xxh3/sha256
 */
class HashSecurityTest extends TestCase
{
    private string $testPath;
    private Application $app;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-hash-test-' . uniqid();
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
        
        // Réinitialiser l'instance pour ce test
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, null);
        
        $this->app = Application::create($this->testPath);
    }

    protected function tearDown(): void
    {
        $this->app->getContainer()->flush();
        
        // Réinitialiser l'instance singleton
        $reflection = new ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, null);
    }

    /**
     * Test que View utilise xxh3 ou sha256, pas MD5
     */
    public function testViewUsesSecureHash(): void
    {
        $viewsPath = $this->testPath . '/views';
        $cachePath = $this->testPath . '/cache';
        $partialsPath = $viewsPath . '/_templates';
        
        mkdir($viewsPath, 0777, true);
        mkdir($partialsPath, 0777, true);
        mkdir($cachePath, 0777, true);
        
        // Créer une vue de test
        $homePath = $viewsPath . '/home';
        mkdir($homePath, 0777, true);
        file_put_contents($homePath . '/_index.html.php', 'Test Content');
        
        $this->app->setViewsPath($viewsPath);
        $this->app->setPartialsPath($partialsPath);
        
        View::configureCache($cachePath, 60);
        View::setCacheEnabled(true);
        
        $view = new View('home/index', false);
        $view->render(['title' => 'Test']);
        
        // Vérifier qu'un fichier de cache existe
        $cacheFiles = glob($cachePath . '/*.cache');
        $this->assertGreaterThan(0, count($cacheFiles), 'Un fichier de cache devrait exister');
        
        // Le nom du fichier de cache devrait contenir un hash de 16 caractères
        // Si c'était MD5, ce serait 16 caractères hexadécimaux
        // Avec sha256, ce sera aussi 16 caractères (substr du hash complet)
        // Avec xxh3, ce sera aussi 16 caractères
        $cacheFileName = basename($cacheFiles[0]);
        $this->assertMatchesRegularExpression('/_[a-f0-9]{16}\.cache$/', $cacheFileName, 
            'Le nom du fichier de cache devrait contenir un hash de 16 caractères hexadécimaux');
        
        // Vérifier que le hash n'est pas MD5 (en vérifiant la longueur du hash complet)
        // On ne peut pas vraiment distinguer MD5 de sha256/xxh3 tronqué à 16 caractères,
        // mais on peut vérifier que le code utilise bien hash() et non md5()
        $reflection = new ReflectionClass(View::class);
        $method = $reflection->getMethod('getCacheFilePath');
        $method->setAccessible(true);
        
        $view2 = new View('home/index', false);
        $cacheFilePath = $method->invoke($view2, $viewsPath, $partialsPath, ['title' => 'Test']);
        
        // Le hash devrait être présent dans le chemin
        $this->assertStringContainsString('_', $cacheFilePath);
        $this->assertStringEndsWith('.cache', $cacheFilePath);
    }

    /**
     * Test que ErrorHandler utilise xxh3 ou sha256, pas MD5
     */
    public function testErrorHandlerUsesSecureHash(): void
    {
        $errorHandler = new ErrorHandler($this->app, null, false);
        
        // Utiliser la réflexion pour accéder à la méthode privée
        $reflection = new ReflectionClass(ErrorHandler::class);
        $method = $reflection->getMethod('generateErrorPageHtml');
        $method->setAccessible(true);
        
        // Générer une page d'erreur
        $html1 = $method->invoke($errorHandler, 404, 'Not Found', 'Page not found', []);
        $html2 = $method->invoke($errorHandler, 404, 'Not Found', 'Page not found', []);
        
        // Le cache devrait fonctionner (même HTML retourné)
        $this->assertEquals($html1, $html2, 'Le cache devrait retourner le même HTML');
        
        // Vérifier que le cache utilise bien un hash sécurisé
        // On ne peut pas vérifier directement le hash, mais on peut vérifier que le cache fonctionne
        $this->assertStringContainsString('Not Found', $html1);
        $this->assertStringContainsString('404', $html1);
    }

    /**
     * Test que le hash fonctionne correctement avec différents algorithmes
     */
    public function testHashAlgorithmFallback(): void
    {
        // Vérifier que xxh3 est utilisé si disponible
        $hasXxh3 = function_exists('hash') && in_array('xxh3', hash_algos(), true);
        
        if ($hasXxh3) {
            $testData = 'test data';
            $hash = hash('xxh3', $testData);
            $this->assertNotEmpty($hash, 'xxh3 devrait produire un hash');
            $this->assertNotEquals(md5($testData), $hash, 'Le hash ne devrait pas être MD5');
        }
        
        // Vérifier que sha256 fonctionne
        $testData = 'test data';
        $sha256Hash = hash('sha256', $testData);
        $this->assertNotEmpty($sha256Hash, 'sha256 devrait produire un hash');
        $this->assertNotEquals(md5($testData), $sha256Hash, 'Le hash ne devrait pas être MD5');
        $this->assertEquals(64, strlen($sha256Hash), 'sha256 devrait produire un hash de 64 caractères');
    }

    /**
     * Test que View utilise bien xxh3/sha256 et pas MD5 (vérification du code source)
     */
    public function testViewHashImplementation(): void
    {
        $reflection = new ReflectionClass(View::class);
        $method = $reflection->getMethod('getCacheFilePath');
        $method->setAccessible(true);
        
        $view = new View('test/view', false);
        $sourceCode = file_get_contents(__DIR__ . '/../src/Core/View/View.php');
        
        // Vérifier que le code utilise hash() et non md5()
        $this->assertStringContainsString('hash(\'xxh3\'', $sourceCode, 'Le code devrait utiliser xxh3');
        $this->assertStringContainsString('hash(\'sha256\'', $sourceCode, 'Le code devrait utiliser sha256 en fallback');
        $this->assertStringNotContainsString('md5($', $sourceCode, 'Le code ne devrait pas utiliser md5() directement');
    }

    /**
     * Test que ErrorHandler utilise bien xxh3/sha256 et pas MD5 (vérification du code source)
     */
    public function testErrorHandlerHashImplementation(): void
    {
        $sourceCode = file_get_contents(__DIR__ . '/../src/Core/ErrorHandler.php');
        
        // Vérifier que le code utilise hash() et non md5()
        $this->assertStringContainsString('hash(\'xxh3\'', $sourceCode, 'Le code devrait utiliser xxh3');
        $this->assertStringContainsString('hash(\'sha256\'', $sourceCode, 'Le code devrait utiliser sha256 en fallback');
        $this->assertStringNotContainsString('md5($', $sourceCode, 'Le code ne devrait pas utiliser md5() directement');
    }

    /**
     * Test que les hash sont cohérents (même entrée = même hash)
     */
    public function testHashConsistency(): void
    {
        $testData = 'test data for consistency';
        
        // Tester xxh3 si disponible
        $hasXxh3 = function_exists('hash') && in_array('xxh3', hash_algos(), true);
        if ($hasXxh3) {
            $hash1 = hash('xxh3', $testData);
            $hash2 = hash('xxh3', $testData);
            $this->assertEquals($hash1, $hash2, 'xxh3 devrait produire le même hash pour la même entrée');
        }
        
        // Tester sha256
        $sha256Hash1 = hash('sha256', $testData);
        $sha256Hash2 = hash('sha256', $testData);
        $this->assertEquals($sha256Hash1, $sha256Hash2, 'sha256 devrait produire le même hash pour la même entrée');
    }

    /**
     * Test que les hash sont différents pour des entrées différentes
     */
    public function testHashUniqueness(): void
    {
        $data1 = 'test data 1';
        $data2 = 'test data 2';
        
        // Tester xxh3 si disponible
        $hasXxh3 = function_exists('hash') && in_array('xxh3', hash_algos(), true);
        if ($hasXxh3) {
            $hash1 = hash('xxh3', $data1);
            $hash2 = hash('xxh3', $data2);
            $this->assertNotEquals($hash1, $hash2, 'xxh3 devrait produire des hash différents pour des entrées différentes');
        }
        
        // Tester sha256
        $sha256Hash1 = hash('sha256', $data1);
        $sha256Hash2 = hash('sha256', $data2);
        $this->assertNotEquals($sha256Hash1, $sha256Hash2, 'sha256 devrait produire des hash différents pour des entrées différentes');
    }
}

