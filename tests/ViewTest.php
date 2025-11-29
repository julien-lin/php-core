<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\View\View;
use JulienLinard\Core\Application;

class ViewTest extends TestCase
{
    private string $testPath;
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-view-test';
        $this->viewsPath = $this->testPath . '/views';
        
        if (!is_dir($this->viewsPath)) {
            mkdir($this->viewsPath, 0777, true);
        }
        
        // Créer une structure de vues de test
        $homePath = $this->viewsPath . '/home';
        if (!is_dir($homePath)) {
            mkdir($homePath, 0777, true);
        }
        
        // Créer un fichier de vue de test
        file_put_contents(
            $homePath . '/index.html.php',
            '<?= htmlspecialchars($title ?? "Default Title") ?>'
        );
        
        // Créer un partial header
        $partialsPath = $this->viewsPath . '/_templates';
        if (!is_dir($partialsPath)) {
            mkdir($partialsPath, 0777, true);
        }
        
        file_put_contents(
            $partialsPath . '/_header.html.php',
            '<header>Header</header>'
        );
        
        file_put_contents(
            $partialsPath . '/_footer.html.php',
            '<footer>Footer</footer>'
        );
        
        // Créer l'application avec les chemins de vues
        // Réinitialiser l'instance pour ce test
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        $app = Application::create($this->testPath);
        $app->setViewsPath($this->viewsPath);
        $app->setPartialsPath($this->viewsPath . '/_templates');
    }

    protected function tearDown(): void
    {
        // Nettoyer
        $app = Application::getInstance();
        if ($app !== null) {
            $app->getContainer()->flush();
        }
        
        // Réinitialiser l'instance singleton
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null);
        
        // Supprimer les fichiers de test
        if (is_dir($this->testPath)) {
            $this->deleteDirectory($this->testPath);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testRenderCompleteView()
    {
        $view = new View('home/index', true);
        $content = $view->render(['title' => 'Test Title']);
        
        $this->assertStringContainsString('Test Title', $content);
        $this->assertStringContainsString('<header>Header</header>', $content);
        $this->assertStringContainsString('<footer>Footer</footer>', $content);
    }

    public function testRenderPartialView()
    {
        $view = new View('home/index', false);
        $content = $view->render(['title' => 'Test Title']);
        
        $this->assertStringContainsString('Test Title', $content);
        $this->assertStringNotContainsString('<header>Header</header>', $content);
        $this->assertStringNotContainsString('<footer>Footer</footer>', $content);
    }

    public function testRenderWithData()
    {
        $view = new View('home/index', false);
        $content = $view->render(['title' => 'Custom Title']);
        
        $this->assertStringContainsString('Custom Title', $content);
    }

    public function testRenderNonExistentView()
    {
        $view = new View('nonexistent/view', false);
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("La vue nonexistent/view n'existe pas");
        
        $view->render();
    }

    public function testInvalidViewName()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le nom de la vue doit être au format 'dossier/fichier'");
        
        new View('invalid', false);
    }

    public function testViewNameWithThreeParts()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le nom de la vue doit être au format 'dossier/fichier'");
        
        new View('too/many/parts', false);
    }
}
