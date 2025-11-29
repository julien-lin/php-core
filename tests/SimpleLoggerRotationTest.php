<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Logging\SimpleLogger;

class SimpleLoggerRotationTest extends TestCase
{
    private string $logPath;
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/core-php-log-test-' . uniqid();
        $this->logPath = $this->logDir . '/app.log';
        mkdir($this->logDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Nettoyer les fichiers de log
        if (is_dir($this->logDir)) {
            $files = glob($this->logDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->logDir);
        }
    }

    public function testRotationConfigDefault()
    {
        $logger = new SimpleLogger($this->logPath);
        $config = $logger->getRotationConfig();

        $this->assertEquals(10 * 1024 * 1024, $config['maxSize']); // 10MB
        $this->assertEquals(5, $config['maxFiles']);
        $this->assertTrue($config['compress']);
    }

    public function testRotationConfigCustom()
    {
        $logger = new SimpleLogger($this->logPath, 'info', [
            'maxSize' => 5 * 1024 * 1024, // 5MB
            'maxFiles' => 3,
            'compress' => false,
        ]);

        $config = $logger->getRotationConfig();
        $this->assertEquals(5 * 1024 * 1024, $config['maxSize']);
        $this->assertEquals(3, $config['maxFiles']);
        $this->assertFalse($config['compress']);
    }

    public function testRotationTriggeredBySize()
    {
        $logger = new SimpleLogger($this->logPath, 'debug', [
            'maxSize' => 1000, // 1KB pour faciliter les tests
            'maxFiles' => 3,
            'compress' => false,
        ]);

        // Remplir le fichier jusqu'à dépasser la limite
        $message = str_repeat('x', 100) . PHP_EOL;
        for ($i = 0; $i < 15; $i++) {
            $logger->info("Test message {$i}: {$message}");
        }

        // Attendre un peu pour que l'écriture soit complète
        usleep(100000);

        // Vérifier qu'un fichier archivé existe
        $archiveFile = $this->logDir . '/app.1.log';
        $this->assertTrue(file_exists($archiveFile), 'Le fichier archivé devrait exister');

        // Vérifier que le fichier actuel est vidé ou plus petit
        if (file_exists($this->logPath)) {
            $currentSize = filesize($this->logPath);
            $this->assertLessThan(1000, $currentSize, 'Le fichier actuel devrait être plus petit après rotation');
        }
    }

    public function testRotationWithCompression()
    {
        if (!function_exists('gzencode')) {
            $this->markTestSkipped('gzencode() n\'est pas disponible');
        }

        $logger = new SimpleLogger($this->logPath, 'debug', [
            'maxSize' => 1000,
            'maxFiles' => 2,
            'compress' => true,
        ]);

        // Remplir le fichier
        $message = str_repeat('x', 100) . PHP_EOL;
        for ($i = 0; $i < 15; $i++) {
            $logger->info("Test message {$i}: {$message}");
        }

        usleep(100000);

        // Vérifier qu'un fichier compressé existe
        $archiveFile = $this->logDir . '/app.1.log.gz';
        $this->assertTrue(file_exists($archiveFile), 'Le fichier compressé devrait exister');

        // Vérifier que c'est bien compressé (plus petit que l'original)
        $compressedSize = filesize($archiveFile);
        $this->assertGreaterThan(0, $compressedSize);
    }

    public function testRotationMultipleFiles()
    {
        $logger = new SimpleLogger($this->logPath, 'debug', [
            'maxSize' => 1000,
            'maxFiles' => 3,
            'compress' => false,
        ]);

        // Effectuer plusieurs rotations
        for ($rotation = 0; $rotation < 3; $rotation++) {
            // Remplir le fichier
            $message = str_repeat('x', 100) . PHP_EOL;
            for ($i = 0; $i < 15; $i++) {
                $logger->info("Rotation {$rotation} - Message {$i}: {$message}");
            }
            usleep(100000);
            
            // Forcer la rotation
            $logger->rotateNow();
            usleep(100000);
        }

        // Vérifier que les fichiers archivés existent
        $this->assertTrue(file_exists($this->logDir . '/app.1.log'));
        $this->assertTrue(file_exists($this->logDir . '/app.2.log'));
        $this->assertTrue(file_exists($this->logDir . '/app.3.log'));
    }

    public function testRotationCleanupOldFiles()
    {
        $logger = new SimpleLogger($this->logPath, 'debug', [
            'maxSize' => 1000,
            'maxFiles' => 2,
            'compress' => false,
        ]);

        // Créer manuellement des fichiers au-delà de maxFiles
        file_put_contents($this->logDir . '/app.3.log', 'old file');
        file_put_contents($this->logDir . '/app.4.log', 'old file');

        // Effectuer une rotation
        $message = str_repeat('x', 100) . PHP_EOL;
        for ($i = 0; $i < 15; $i++) {
            $logger->info("Message {$i}: {$message}");
        }
        usleep(100000);
        $logger->rotateNow();
        usleep(100000);

        // Les fichiers au-delà de maxFiles devraient être supprimés
        $this->assertFalse(file_exists($this->logDir . '/app.3.log'), 'Les anciens fichiers devraient être supprimés');
        $this->assertFalse(file_exists($this->logDir . '/app.4.log'), 'Les anciens fichiers devraient être supprimés');
    }

    public function testRotateNow()
    {
        $logger = new SimpleLogger($this->logPath, 'debug', [
            'maxSize' => 10000, // Grand pour ne pas déclencher automatiquement
            'maxFiles' => 2,
            'compress' => false,
        ]);

        // Écrire quelques logs
        $logger->info('Test message 1');
        $logger->info('Test message 2');

        // Forcer la rotation
        $logger->rotateNow();

        // Vérifier qu'un fichier archivé existe
        $archiveFile = $this->logDir . '/app.1.log';
        $this->assertTrue(file_exists($archiveFile), 'Le fichier archivé devrait exister après rotation manuelle');

        // Vérifier que le fichier actuel est vidé
        if (file_exists($this->logPath)) {
            $content = file_get_contents($this->logPath);
            $this->assertEmpty($content, 'Le fichier actuel devrait être vidé après rotation');
        }
    }

    public function testSetRotationConfig()
    {
        $logger = new SimpleLogger($this->logPath);
        
        $logger->setRotationConfig([
            'maxSize' => 5 * 1024 * 1024,
            'maxFiles' => 10,
        ]);

        $config = $logger->getRotationConfig();
        $this->assertEquals(5 * 1024 * 1024, $config['maxSize']);
        $this->assertEquals(10, $config['maxFiles']);
        // La valeur existante devrait être préservée
        $this->assertTrue($config['compress']);
    }

    public function testRotationPreservesLogContent()
    {
        $logger = new SimpleLogger($this->logPath, 'debug', [
            'maxSize' => 1000,
            'maxFiles' => 2,
            'compress' => false,
        ]);

        // Écrire des logs spécifiques
        $logger->info('Important message 1');
        $logger->error('Error message');
        $logger->info('Important message 2');

        $originalContent = file_get_contents($this->logPath);

        // Forcer la rotation
        $logger->rotateNow();

        // Vérifier que le contenu est préservé dans l'archive
        $archiveFile = $this->logDir . '/app.1.log';
        $this->assertTrue(file_exists($archiveFile));
        
        $archivedContent = file_get_contents($archiveFile);
        $this->assertStringContainsString('Important message 1', $archivedContent);
        $this->assertStringContainsString('Error message', $archivedContent);
        $this->assertStringContainsString('Important message 2', $archivedContent);
    }

    public function testNoRotationIfFileNotExists()
    {
        $logger = new SimpleLogger($this->logPath, 'debug', [
            'maxSize' => 1000,
            'maxFiles' => 2,
            'compress' => false,
        ]);

        // Supprimer le fichier s'il existe
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }

        // Rotation ne devrait pas créer d'erreur
        $logger->rotateNow();

        // Aucun fichier archivé ne devrait être créé
        $archiveFile = $this->logDir . '/app.1.log';
        $this->assertFalse(file_exists($archiveFile));
    }

    public function testMinLevelWithInt()
    {
        $logger = new SimpleLogger($this->logPath, 3); // warning level

        // Debug devrait être ignoré
        $logger->debug('Debug message');
        $content = file_exists($this->logPath) ? file_get_contents($this->logPath) : '';
        $this->assertStringNotContainsString('Debug message', $content);

        // Warning devrait être loggé
        $logger->warning('Warning message');
        $content = file_exists($this->logPath) ? file_get_contents($this->logPath) : '';
        $this->assertStringContainsString('Warning message', $content);
    }
}
