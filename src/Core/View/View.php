<?php

declare(strict_types=1);

namespace JulienLinard\Core\View;

use JulienLinard\Core\Application;

/**
 * Moteur de templates simple
 */
class View
{
    /**
     * Dossier de cache des vues
     */
    private static ?string $cacheDir = null;

    /**
     * Durée de validité du cache (secondes)
     */
    private static int $cacheTTL = 3600; // 1 heure par défaut

    /**
     * Cache activé ou désactivé
     */
    private static bool $cacheEnabled = false;

    /**
     * Cache des métadonnées (mtimes) pour éviter les appels répétés à filemtime()
     * Structure: ['filepath' => ['mtime' => int, 'checked' => int]]
     */
    private static array $metadataCache = [];

    /**
     * Dernière fois que le cache de métadonnées a été nettoyé
     */
    private static int $lastMetadataCleanup = 0;

    /**
     * Intervalle de nettoyage du cache de métadonnées (secondes)
     */
    private const METADATA_CLEANUP_INTERVAL = 60; // 1 minute

    /**
     * Cache des chemins de fichiers de vues pour éviter les recalculs
     * Structure: ['viewName_complete' => 'fullPath']
     */
    private static array $viewPathCache = [];

    /**
     * Cache du contenu des fichiers de vues partiels (header/footer) pour éviter les relectures
     * Structure: ['filepath' => ['content' => string, 'mtime' => int]]
     */
    private static array $partialContentCache = [];

    /**
     * Permet de configurer le cache (dossier, TTL)
     * 
     * @param string|null $dir Dossier de cache (null pour désactiver)
     * @param int $ttl Durée de validité en secondes
     */
    public static function configureCache(?string $dir, int $ttl = 3600): void
    {
        self::$cacheDir = $dir;
        self::$cacheTTL = $ttl;
        self::$cacheEnabled = $dir !== null;
    }

    /**
     * Active ou désactive le cache
     */
    public static function setCacheEnabled(bool $enabled): void
    {
        self::$cacheEnabled = $enabled && self::$cacheDir !== null;
    }

    /**
     * Vide les caches internes (chemins, métadonnées, contenus partiels)
     */
    public static function clearInternalCaches(): void
    {
        self::$viewPathCache = [];
        self::$metadataCache = [];
        self::$partialContentCache = [];
        self::$lastMetadataCleanup = 0;
    }

    /**
     * Nettoie le cache (supprime les fichiers expirés)
     * 
     * @param int|null $maxAge Age maximum en secondes (null = utiliser TTL)
     * @return int Nombre de fichiers supprimés
     */
    public static function clearCache(?int $maxAge = null): int
    {
        if (self::$cacheDir === null || !is_dir(self::$cacheDir)) {
            return 0;
        }

        $maxAge = $maxAge ?? self::$cacheTTL;
        $now = time();
        $deleted = 0;

        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                // Si maxAge = 0, supprimer tous les fichiers
                // Sinon, vérifier l'âge
                if ($maxAge === 0 || ($now - filemtime($file)) > $maxAge) {
                    @unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
    /**
     * Titre par défaut de la page
     */
    public string $title = '';

    /**
     * Chemin vers le fichier de vue
     */
    private string $name;

    /**
     * Si true, inclut le layout (header/footer)
     */
    private bool $complete;

    /**
     * Constructeur
     *
     * @param string $name Nom de la vue (ex: 'home/index')
     * @param bool $complete Si true, inclut le layout
     */
    public function __construct(string $name, bool $complete = true)
    {
        // ✅ Vérifie les caractères non autorisés (nul byte) avant tout
        if (strpos($name, "\0") !== false) {
            throw new \InvalidArgumentException(
                "Tentative de traversal détectée"
            );
        }

        // ✅ Vérifier le format de base: doit contenir au moins un slash
        if (strpos($name, '/') === false) {
            throw new \InvalidArgumentException(
                "Format de vue invalide. Utilisez: 'dossier/fichier'"
            );
        }

        // ✅ Vérifier qu'il n'y a pas de tentative de traversal ou caractères sensibles
        $parts = explode('/', $name);

        // Doit avoir exactement 2 parties (dossier/fichier)
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                "Format de vue invalide. Utilisez: 'dossier/fichier'"
            );
        }

        foreach ($parts as $part) {
            // Partie vide ou '..' ou '.' 
            if (empty($part) || $part === '..' || $part === '.') {
                throw new \InvalidArgumentException(
                    "Tentative de traversal détectée"
                );
            }
            // Vérifier caractères valides: alphanumériques, tiret, underscore, point
            if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $part)) {
                throw new \InvalidArgumentException(
                    "Format de vue invalide. Utilisez: 'dossier/fichier'"
                );
            }
        }

        $this->name = $name;
        $this->complete = $complete;
    }

    /**
     * Rendu de la vue
     *
     * @param array $data Données à passer à la vue
     * @return string Contenu rendu de la vue
     */
    public function render(?array $data = []): string
    {
        $app = Application::getInstanceOrFail();
        $viewsPath = $app->getViewsPath();
        $partialsPath = $app->getPartialsPath();

        // Vérifier le cache si activé
        if (self::$cacheEnabled && self::$cacheDir !== null) {
            $cached = $this->getCachedContent($viewsPath, $partialsPath, $data);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Vérifier si Vision est disponible
        $visionAvailable = class_exists(\JulienLinard\Vision\Vision::class);

        if ($visionAvailable) {
            return $this->renderWithVision($viewsPath, $partialsPath, $data ?? []);
        } else {
            return $this->renderWithPhp($viewsPath, $partialsPath, $data ?? []);
        }
    }

    /**
     * Rend la vue en utilisant Vision (si disponible)
     *
     * @param string $viewsPath
     * @param string $partialsPath
     * @param array $data
     * @return string
     */
    private function renderWithVision(string $viewsPath, string $partialsPath, array $data): string
    {
        // Créer une instance de Vision pour parser les templates
        $vision = new \JulienLinard\Vision\Vision('', true); // Auto-escape activé par défaut

        // Construire le contenu final
        $content = '';

        // Inclure le header si vue complète (avec cache)
        if ($this->complete) {
            $headerPath = $partialsPath . DIRECTORY_SEPARATOR . '_header.html.php';
            $headerContent = $this->getCachedPartialContent($headerPath);
            if ($headerContent !== null) {
                $content .= $vision->renderString($headerContent, $data);
            }
        }

        // Inclure la vue principale
        $viewPath = $this->getViewPath($viewsPath);
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("La vue {$this->name} n'existe pas à l'emplacement: {$viewPath}");
        }

        // Utiliser le cache pour le contenu de la vue si possible
        $viewContent = $this->getCachedPartialContent($viewPath);
        if ($viewContent === null) {
            throw new \RuntimeException("Impossible de lire le fichier de vue: {$viewPath}");
        }

        $content .= $vision->renderString($viewContent, $data);

        // Inclure le footer si vue complète (avec cache)
        if ($this->complete) {
            $footerPath = $partialsPath . DIRECTORY_SEPARATOR . '_footer.html.php';
            $footerContent = $this->getCachedPartialContent($footerPath);
            if ($footerContent !== null) {
                $content .= $vision->renderString($footerContent, $data);
            }
        }

        // Sauvegarder dans le cache si activé
        if (self::$cacheEnabled && self::$cacheDir !== null) {
            $this->saveCachedContent($viewsPath, $partialsPath, $data, $content);
        }

        return $content;
    }

    /**
     * Rend la vue en utilisant la méthode PHP procédurale classique
     *
     * @param string $viewsPath
     * @param string $partialsPath
     * @param array $data
     * @return string
     */
    private function renderWithPhp(string $viewsPath, string $partialsPath, array $data): string
    {
        // Extraire les données pour qu'elles soient accessibles dans la vue
        if (!empty($data)) {
            extract($data);
        }

        // Ajouter des helpers utiles
        $escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        $e = $escape; // Alias court

        // Mise en cache du contenu
        ob_start();

        try {
            // Inclure le header si vue complète
            if ($this->complete) {
                $headerPath = $partialsPath . DIRECTORY_SEPARATOR . '_header.html.php';
                if (file_exists($headerPath)) {
                    require $headerPath;
                }
            }

            // Inclure la vue
            $viewPath = $this->getViewPath($viewsPath);
            if (!file_exists($viewPath)) {
                throw new \RuntimeException("La vue {$this->name} n'existe pas à l'emplacement: {$viewPath}");
            }
            require $viewPath;

            // Inclure le footer si vue complète
            if ($this->complete) {
                $footerPath = $partialsPath . DIRECTORY_SEPARATOR . '_footer.html.php';
                if (file_exists($footerPath)) {
                    require $footerPath;
                }
            }

            // Récupérer le contenu
            $content = ob_get_clean();

            // Sauvegarder dans le cache si activé
            if (self::$cacheEnabled && self::$cacheDir !== null) {
                $this->saveCachedContent($viewsPath, $partialsPath, $data, $content);
            }

            return $content;
        } catch (\Throwable $e) {
            // Nettoyer le buffer en cas d'exception
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Récupère le contenu depuis le cache si valide
     * 
     * @return string|null Contenu en cache ou null si invalide/inexistant
     */
    private function getCachedContent(string $viewsPath, string $partialsPath, array $data): ?string
    {
        $cacheFile = $this->getCacheFilePath($viewsPath, $partialsPath, $data);

        if (!file_exists($cacheFile)) {
            return null;
        }

        $cacheMTime = filemtime($cacheFile);
        $now = time();

        // Vérifier le TTL
        if (($now - $cacheMTime) >= self::$cacheTTL) {
            @unlink($cacheFile);
            return null;
        }

        // Nettoyer le cache de métadonnées si nécessaire
        $this->cleanupMetadataCache();

        // Vérifier si la vue principale a changé (avec cache de métadonnées)
        $viewPath = $this->getViewPath($viewsPath);
        if (file_exists($viewPath)) {
            $viewMTime = $this->getCachedMTime($viewPath);
            if ($viewMTime > $cacheMTime) {
                @unlink($cacheFile);
                return null;
            }
        }

        // Vérifier les partials si vue complète (avec cache de métadonnées)
        if ($this->complete) {
            $headerPath = $partialsPath . DIRECTORY_SEPARATOR . '_header.html.php';
            $footerPath = $partialsPath . DIRECTORY_SEPARATOR . '_footer.html.php';

            // Vérifier header
            if (file_exists($headerPath)) {
                $headerMTime = $this->getCachedMTime($headerPath);
                if ($headerMTime > $cacheMTime) {
                    @unlink($cacheFile);
                    return null;
                }
            }

            // Vérifier footer
            if (file_exists($footerPath)) {
                $footerMTime = $this->getCachedMTime($footerPath);
                if ($footerMTime > $cacheMTime) {
                    @unlink($cacheFile);
                    return null;
                }
            }
        }

        // Lire le cache avec verrouillage en lecture
        $fp = @fopen($cacheFile, 'rb');
        if ($fp === false) {
            return null;
        }

        // Verrouiller en lecture partagée
        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            return null;
        }

        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $content !== false ? $content : null;
    }

    /**
     * Sauvegarde le contenu dans le cache
     */
    private function saveCachedContent(string $viewsPath, string $partialsPath, array $data, string $content): void
    {
        if (self::$cacheDir === null) {
            return;
        }

        $cacheFile = $this->getCacheFilePath($viewsPath, $partialsPath, $data);

        // Créer le dossier si nécessaire
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            // Permissions sécurisées: 0750 (rwxr-x---) au lieu de 0777
            if (!mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
                throw new \RuntimeException("Impossible de créer le dossier de cache: {$cacheDir}");
            }
        }

        // Écrire avec verrouillage exclusif
        $fp = @fopen($cacheFile, 'cb');
        if ($fp === false) {
            // Logger l'erreur pour le débogage (mais continuer sans cache)
            error_log("View cache: Impossible d'ouvrir le fichier de cache: {$cacheFile}", E_USER_WARNING);
            return; // Continuer sans cache pour ne pas bloquer le rendu
        }

        if (flock($fp, LOCK_EX)) {
            ftruncate($fp, 0);
            fwrite($fp, $content);
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
    }

    /**
     * Génère le chemin du fichier de cache
     */
    private function getCacheFilePath(string $viewsPath, string $partialsPath, array $data): string
    {
        // Inclure les timestamps des fichiers dans le hash pour invalidation automatique
        $viewPath = $this->getViewPath($viewsPath);
        $viewMTime = $this->getCachedMTime($viewPath);

        $hashData = [
            'view' => $this->name,
            'complete' => $this->complete,
            'view_mtime' => $viewMTime,
            'data' => $data,
        ];

        // Ajouter les timestamps des partials si vue complète
        if ($this->complete) {
            $headerPath = $partialsPath . DIRECTORY_SEPARATOR . '_header.html.php';
            $footerPath = $partialsPath . DIRECTORY_SEPARATOR . '_footer.html.php';

            $hashData['header_mtime'] = $this->getCachedMTime($headerPath);
            $hashData['footer_mtime'] = $this->getCachedMTime($footerPath);
        }

        // Utiliser un hash rapide pour le cache (xxh3 si disponible, sinon md5)
        // MD5 est suffisant ici car ce n'est pas cryptographique, juste pour identifier le cache
        $json = json_encode($hashData, JSON_THROW_ON_ERROR);

        // Utiliser xxh3 si disponible (PHP 8.1+), sinon md5 (plus rapide que sha256)
        if (function_exists('hash') && in_array('xxh3', hash_algos(), true)) {
            $hash = hash('xxh3', $json);
        } else {
            // MD5 est suffisant pour un hash de cache (pas de problème de sécurité)
            $hash = md5($json);
        }

        // Limiter la longueur du hash pour des noms de fichiers plus courts
        $hash = substr($hash, 0, 16);
        $cacheKey = str_replace('/', '_', $this->name) . '_' . ($this->complete ? 'full' : 'partial') . '_' . $hash;

        return rtrim(self::$cacheDir, '/') . '/' . $cacheKey . '.cache';
    }

    /**
     * Récupère le mtime d'un fichier avec cache
     * 
     * @param string $filePath Chemin du fichier
     * @return int Mtime du fichier ou 0 si inexistant
     */
    private function getCachedMTime(string $filePath): int
    {
        $now = time();

        // Vérifier le cache
        if (isset(self::$metadataCache[$filePath])) {
            $cached = self::$metadataCache[$filePath];
            // Si le cache a moins de 5 secondes, le réutiliser
            if (($now - $cached['checked']) < 5) {
                return $cached['mtime'];
            }
        }

        // Récupérer le mtime réel
        $mtime = file_exists($filePath) ? filemtime($filePath) : 0;

        // Mettre en cache
        self::$metadataCache[$filePath] = [
            'mtime' => $mtime,
            'checked' => $now
        ];

        return $mtime;
    }

    /**
     * Nettoie le cache de métadonnées si nécessaire
     */
    private function cleanupMetadataCache(): void
    {
        $now = time();
        if ($now - self::$lastMetadataCleanup < self::METADATA_CLEANUP_INTERVAL) {
            return;
        }

        // Nettoyer les entrées de plus de 1 minute
        foreach (self::$metadataCache as $filePath => $data) {
            if (($now - $data['checked']) > 60) {
                unset(self::$metadataCache[$filePath]);
            }
        }

        self::$lastMetadataCleanup = $now;
    }

    /**
     * Retourne le chemin complet vers le fichier de vue
     * Utilise un cache pour éviter les recalculs
     */
    private function getViewPath(string $viewsPath): string
    {
        // Créer une clé de cache
        $cacheKey = $this->name . '_' . ($this->complete ? 'full' : 'partial');

        // Vérifier le cache
        if (isset(self::$viewPathCache[$cacheKey])) {
            $cachedPath = self::$viewPathCache[$cacheKey];
            // Vérifier que le fichier existe toujours
            if (file_exists($cachedPath)) {
                return $cachedPath;
            }
            // Si le fichier n'existe plus, retirer du cache
            unset(self::$viewPathCache[$cacheKey]);
        }

        // Séparer le nom de la vue (ex: 'home/index' -> ['home', 'index'])
        $parts = explode('/', $this->name);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                "Le nom de la vue doit être au format 'dossier/fichier' (ex: 'home/index')"
            );
        }

        [$category, $filename] = $parts;

        // Préfixe pour les vues partielles
        $prefix = $this->complete ? '' : '_';

        $fullPath = $viewsPath
            . DIRECTORY_SEPARATOR
            . $category
            . DIRECTORY_SEPARATOR
            . $prefix
            . $filename
            . '.html.php';

        // Mettre en cache
        self::$viewPathCache[$cacheKey] = $fullPath;

        return $fullPath;
    }

    /**
     * Récupère le contenu d'un fichier partiel avec cache
     * 
     * @param string $filePath Chemin du fichier
     * @return string|null Contenu du fichier ou null si inexistant
     */
    private function getCachedPartialContent(string $filePath): ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $mtime = $this->getCachedMTime($filePath);

        // Vérifier le cache
        if (isset(self::$partialContentCache[$filePath])) {
            $cached = self::$partialContentCache[$filePath];
            // Si le mtime correspond, utiliser le cache
            if ($cached['mtime'] === $mtime) {
                return $cached['content'];
            }
        }

        // Lire le fichier
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Mettre en cache (limiter la taille pour éviter la surconsommation mémoire)
        if (count(self::$partialContentCache) < 100) {
            self::$partialContentCache[$filePath] = [
                'content' => $content,
                'mtime' => $mtime
            ];
        }

        return $content;
    }

    /**
     * Crée une nouvelle vue (méthode statique helper)
     * 
     * @deprecated Utiliser render() qui retourne le contenu au lieu de l'afficher
     */
    public static function make(string $name, array $data = [], bool $complete = true): void
    {
        $view = new self($name, $complete);
        echo $view->render($data);
        exit();
    }
}
