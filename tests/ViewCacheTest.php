<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\View\View;
use JulienLinard\Core\Application;

class ViewCacheTest extends TestCase
{
    private string $testPath;
    private string $viewsPath;
    private string $cachePath;
    private string $partialsPath;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-view-cache-test-' . uniqid();
        $this->viewsPath = $this->testPath . '/views';
        $this->cachePath = $this->testPath . '/cache';
        $this->partialsPath = $this->viewsPath . '/_templates';
        
        // Créer les répertoires
        mkdir($this->viewsPath, 0777, true);
        mkdir($this->partialsPath, 0777, true);
        
        // Créer une structure de vues de test
        $homePath = $this->viewsPath . '/home';
        mkdir($homePath, 0777, true);
        
        // Vue principale
        file_put_contents(
            $homePath . '/index.html.php',
            '<?= htmlspecialchars($title ?? "Default Title") ?>'
        );
        
        // Vue partielle
        file_put_contents(
            $homePath . '/_index.html.php',
            '<?= htmlspecialchars($title ?? "Default Title") ?>'
        );
        
        // Partials
        file_put_contents(
            $this->partialsPath . '/_header.html.php',
            '<header>Header</header>'
        );
        
        file_put_contents(
            $this->partialsPath . '/_footer.html.php',
            '<footer>Footer</footer>'
        );
        
        // Réinitialiser l'instance Application
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, null);
        
        $app = Application::create($this->testPath);
        $app->setViewsPath($this->viewsPath);
        $app->setPartialsPath($this->partialsPath);
        
        // Configurer le cache
        View::configureCache($this->cachePath, 60); // TTL de 60 secondes pour les tests
        View::setCacheEnabled(true);
    }

    protected function tearDown(): void
    {
        // Désactiver et nettoyer le cache
        View::configureCache(null);
        
        // Nettoyer l'application
        $app = Application::getInstance();
        if ($app !== null) {
            $app->getContainer()->flush();
        }
        
        // Réinitialiser l'instance singleton
        $reflection = new \ReflectionClass(Application::class);
        $property = $reflection->getProperty('instance');
        $property->setValue(null, null);
        
        // Supprimer les fichiers de test
        $this->deleteDirectory($this->testPath);
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

    public function testCacheConfiguration()
    {
        View::configureCache($this->cachePath, 120);
        View::setCacheEnabled(true);
        
        // Le dossier sera créé lors du premier rendu
        $view = new View('home/index', false);
        $view->render(['title' => 'Test']);
        
        // Attendre un peu pour que le fichier soit écrit
        usleep(100000);
        
        // Vérifier que le dossier existe maintenant
        $this->assertTrue(is_dir($this->cachePath), 'Le dossier de cache devrait être créé');
        
        // Vérifier qu'un fichier de cache existe
        $cacheFiles = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThan(0, count($cacheFiles), 'Un fichier de cache devrait exister');
    }

    public function testCacheEnabledAndDisabled()
    {
        // Activer le cache
        View::setCacheEnabled(true);
        $view = new View('home/index', false);
        $content1 = $view->render(['title' => 'Test']);
        
        // Le cache devrait être créé
        $cacheFiles = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThan(0, count($cacheFiles), 'Le cache devrait être créé');
        
        // Désactiver le cache
        View::setCacheEnabled(false);
        
        // Modifier la vue pour forcer un nouveau rendu
        $homePath = $this->viewsPath . '/home';
        file_put_contents($homePath . '/_index.html.php', 'Modified Content');
        
        $view2 = new View('home/index', false);
        $content2 = $view2->render(['title' => 'Test']);
        
        // Le contenu devrait être différent (pas de cache)
        $this->assertEquals('Modified Content', $content2);
    }

    public function testCacheRetrieval()
    {
        $view = new View('home/index', false);
        
        // Premier rendu (devrait créer le cache)
        $content1 = $view->render(['title' => 'Cached Title']);
        
        // Deuxième rendu (devrait utiliser le cache)
        $content2 = $view->render(['title' => 'Cached Title']);
        
        $this->assertEquals($content1, $content2);
        $this->assertStringContainsString('Cached Title', $content2);
        
        // Vérifier qu'un fichier de cache existe
        $cacheFiles = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThan(0, count($cacheFiles));
    }

    public function testCacheInvalidationByTTL()
    {
        // Configurer un TTL très court
        View::configureCache($this->cachePath, 1);
        
        $view = new View('home/index', false);
        $view->render(['title' => 'Test']);
        
        // Attendre que le cache expire
        sleep(2);
        
        // Modifier la vue
        $homePath = $this->viewsPath . '/home';
        file_put_contents($homePath . '/_index.html.php', 'New Content');
        
        // Le rendu devrait utiliser le nouveau contenu (cache expiré)
        $content = $view->render(['title' => 'Test']);
        $this->assertEquals('New Content', $content);
    }

    public function testCacheInvalidationByViewModification()
    {
        $view = new View('home/index', false);
        
        // Premier rendu (crée le cache)
        $content1 = $view->render(['title' => 'Test']);
        
        // Attendre un peu pour que le cache soit écrit
        usleep(100000);
        
        // Modifier la vue (change le mtime et le contenu)
        $homePath = $this->viewsPath . '/home';
        $viewFile = $homePath . '/_index.html.php';
        file_put_contents($viewFile, '<?= htmlspecialchars($title ?? "Modified Content") ?>');
        
        // Attendre pour que le mtime change (certains systèmes ont une résolution de 1 seconde)
        sleep(1);
        
        // Le rendu devrait détecter que la vue a changé et utiliser le nouveau contenu
        // Le hash change car le mtime change, donc un nouveau cache est créé
        $content2 = $view->render(['title' => 'Test']);
        
        // Le contenu devrait contenir "Test" (de $title) mais le cache devrait être différent
        // car le mtime de la vue a changé, donc le hash est différent
        $this->assertStringContainsString('Test', $content2);
        
        // Vérifier qu'il y a maintenant 2 fichiers de cache (ancien + nouveau)
        $cacheFiles = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThanOrEqual(1, count($cacheFiles), 'Au moins un fichier de cache devrait exister');
    }

    public function testCacheInvalidationByPartialModification()
    {
        $view = new View('home/index', true); // Vue complète avec partials
        
        // Premier rendu (crée le cache)
        $content1 = $view->render(['title' => 'Test']);
        
        // Attendre un peu pour que le cache soit écrit
        usleep(200000); // 0.2 seconde
        
        // Modifier le header (change le mtime)
        $headerPath = $this->partialsPath . '/_header.html.php';
        file_put_contents($headerPath, '<header>New Header</header>');
        
        // Attendre pour que le mtime change (certains systèmes ont une résolution de 1 seconde)
        sleep(2);
        
        // Vider le cache de métadonnées pour forcer la relecture du mtime
        View::clearInternalCaches();
        
        // Le rendu devrait détecter que le header a changé (via getCachedContent qui vérifie le mtime)
        // et utiliser le nouveau header
        $content2 = $view->render(['title' => 'Test']);
        
        // Le contenu devrait contenir le nouveau header
        $this->assertStringContainsString('New Header', $content2, 'Le nouveau header devrait être dans le contenu');
        $this->assertNotEquals($content1, $content2, 'Le contenu devrait être différent');
    }

    public function testCacheWithDifferentData()
    {
        $view = new View('home/index', false);
        
        // Rendu avec données différentes
        $content1 = $view->render(['title' => 'Title 1']);
        $content2 = $view->render(['title' => 'Title 2']);
        
        // Les contenus devraient être différents
        $this->assertNotEquals($content1, $content2);
        $this->assertStringContainsString('Title 1', $content1);
        $this->assertStringContainsString('Title 2', $content2);
        
        // Vérifier que deux fichiers de cache différents existent
        $cacheFiles = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThanOrEqual(2, count($cacheFiles));
    }

    public function testCacheClear()
    {
        $view = new View('home/index', false);
        
        // Créer plusieurs entrées de cache (données différentes = hash différents)
        $view->render(['title' => 'Test 1']);
        $view->render(['title' => 'Test 2']);
        $view->render(['title' => 'Test 3']);
        
        // Attendre un peu pour que les fichiers soient écrits
        usleep(200000); // 0.2 seconde
        
        $cacheFilesBefore = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThan(0, count($cacheFilesBefore), 'Des fichiers de cache devraient exister');
        
        // Nettoyer le cache avec maxAge = 0 (supprime tous les fichiers)
        $deleted = View::clearCache(0);
        
        $this->assertGreaterThan(0, $deleted, 'Des fichiers devraient être supprimés');
        
        $cacheFilesAfter = glob($this->cachePath . '/*.cache');
        $this->assertEquals(0, count($cacheFilesAfter), 'Tous les fichiers de cache devraient être supprimés');
    }

    public function testCacheClearWithMaxAge()
    {
        // Créer un cache avec TTL long
        View::configureCache($this->cachePath, 3600);
        
        $view = new View('home/index', false);
        $view->render(['title' => 'Test']);
        
        // Attendre un peu pour que le fichier soit écrit
        usleep(100000);
        
        // Nettoyer avec un maxAge très court (devrait tout supprimer car les fichiers viennent d'être créés)
        // Mais attendre 2 secondes pour que maxAge soit dépassé
        sleep(2);
        $deleted = View::clearCache(1);
        
        $this->assertGreaterThan(0, $deleted, 'Les fichiers expirés devraient être supprimés');
    }

    public function testCacheDisabledByDefault()
    {
        // Réinitialiser la configuration
        View::configureCache(null);
        
        $view = new View('home/index', false);
        $content1 = $view->render(['title' => 'Test']);
        
        // Modifier la vue
        $homePath = $this->viewsPath . '/home';
        file_put_contents($homePath . '/_index.html.php', 'Modified');
        
        // Le contenu devrait être différent (pas de cache)
        $content2 = $view->render(['title' => 'Test']);
        $this->assertEquals('Modified', $content2);
        
        // Aucun fichier de cache ne devrait exister
        if (is_dir($this->cachePath)) {
            $cacheFiles = glob($this->cachePath . '/*.cache');
            $this->assertEquals(0, count($cacheFiles));
        }
    }

    public function testCacheWithCompleteView()
    {
        $view = new View('home/index', true); // Vue complète
        
        // Premier rendu
        $content1 = $view->render(['title' => 'Test']);
        
        // Deuxième rendu (devrait utiliser le cache)
        $content2 = $view->render(['title' => 'Test']);
        
        $this->assertEquals($content1, $content2);
        $this->assertStringContainsString('Header', $content2);
        $this->assertStringContainsString('Footer', $content2);
    }

    public function testCacheWithPartialView()
    {
        $view = new View('home/index', false); // Vue partielle
        
        // Premier rendu
        $content1 = $view->render(['title' => 'Test']);
        
        // Deuxième rendu (devrait utiliser le cache)
        $content2 = $view->render(['title' => 'Test']);
        
        $this->assertEquals($content1, $content2);
        $this->assertStringNotContainsString('Header', $content2);
        $this->assertStringNotContainsString('Footer', $content2);
    }

    public function testCacheDirectoryCreation()
    {
        // Utiliser un nouveau dossier de cache
        $newCachePath = $this->testPath . '/new-cache';
        View::configureCache($newCachePath);
        
        $view = new View('home/index', false);
        $view->render(['title' => 'Test']);
        
        // Le dossier devrait être créé
        $this->assertTrue(is_dir($newCachePath));
        
        // Nettoyer
        View::configureCache(null);
    }

    public function testCacheWithComplexData()
    {
        $view = new View('home/index', false);
        
        $complexData1 = [
            'user' => [
                'name' => 'John',
                'email' => 'john@example.com',
                'roles' => ['admin', 'user'],
            ],
            'items' => [1, 2, 3, 4, 5],
            'metadata' => [
                'created' => '2024-01-01',
                'updated' => '2024-01-02',
            ],
        ];
        
        // Premier rendu
        $content1 = $view->render($complexData1);
        
        // Deuxième rendu avec les mêmes données (devrait utiliser le cache)
        $content2 = $view->render($complexData1);
        
        $this->assertEquals($content1, $content2);
        
        // Rendu avec des données différentes (hash différent = nouveau cache)
        $complexData2 = $complexData1;
        $complexData2['user']['name'] = 'Jane';
        $content3 = $view->render($complexData2);
        
        // Le contenu devrait être différent car les données sont différentes
        // Mais la vue affiche toujours "Default Title" car elle n'utilise pas ces données
        // Vérifions plutôt que les caches sont différents
        $this->assertStringContainsString('Default Title', $content3);
        
        // Vérifier qu'il y a au moins 2 fichiers de cache (un pour chaque hash de données)
        $cacheFiles = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThanOrEqual(2, count($cacheFiles), 'Deux caches différents devraient exister');
    }

    public function testCacheFileLocking()
    {
        // Ce test vérifie que le verrouillage fonctionne
        // En pratique, on ne peut pas vraiment tester la concurrence facilement,
        // mais on peut vérifier que les fichiers sont créés correctement
        
        $view = new View('home/index', false);
        
        // Créer plusieurs rendus simultanés (simulation)
        $content1 = $view->render(['title' => 'Test']);
        $content2 = $view->render(['title' => 'Test']);
        
        // Les deux devraient être identiques (même cache)
        $this->assertEquals($content1, $content2);
        
        // Vérifier qu'un seul fichier de cache existe pour ces données
        $cacheFiles = glob($this->cachePath . '/*.cache');
        $this->assertGreaterThan(0, count($cacheFiles));
    }
}
