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
        // Valider le format du nom de vue (doit être 'dossier/fichier')
        $parts = explode('/', $name);
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                "Le nom de la vue doit être au format 'dossier/fichier' (ex: 'home/index')"
            );
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

        // Inclure le header si vue complète
        if ($this->complete) {
            $headerPath = $partialsPath . DIRECTORY_SEPARATOR . '_header.html.php';
            if (file_exists($headerPath)) {
                $headerContent = file_get_contents($headerPath);
                if ($headerContent !== false) {
                    $content .= $vision->renderString($headerContent, $data);
                }
            }
        }

        // Inclure la vue principale
        $viewPath = $this->getViewPath($viewsPath);
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("La vue {$this->name} n'existe pas à l'emplacement: {$viewPath}");
        }
        
        $viewContent = file_get_contents($viewPath);
        if ($viewContent === false) {
            throw new \RuntimeException("Impossible de lire le fichier de vue: {$viewPath}");
        }
        
        $content .= $vision->renderString($viewContent, $data);

        // Inclure le footer si vue complète
        if ($this->complete) {
            $footerPath = $partialsPath . DIRECTORY_SEPARATOR . '_footer.html.php';
            if (file_exists($footerPath)) {
                $footerContent = file_get_contents($footerPath);
                if ($footerContent !== false) {
                    $content .= $vision->renderString($footerContent, $data);
                }
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

        // Vérifier si la vue principale a changé
        $viewPath = $this->getViewPath($viewsPath);
        if (file_exists($viewPath) && filemtime($viewPath) > $cacheMTime) {
            @unlink($cacheFile);
            return null;
        }

        // Vérifier les partials si vue complète
        if ($this->complete) {
            $headerPath = $partialsPath . DIRECTORY_SEPARATOR . '_header.html.php';
            $footerPath = $partialsPath . DIRECTORY_SEPARATOR . '_footer.html.php';
            
            if (file_exists($headerPath) && filemtime($headerPath) > $cacheMTime) {
                @unlink($cacheFile);
                return null;
            }
            
            if (file_exists($footerPath) && filemtime($footerPath) > $cacheMTime) {
                @unlink($cacheFile);
                return null;
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
            if (!mkdir($cacheDir, 0777, true) && !is_dir($cacheDir)) {
                throw new \RuntimeException("Impossible de créer le dossier de cache: {$cacheDir}");
            }
        }

        // Écrire avec verrouillage exclusif
        $fp = @fopen($cacheFile, 'cb');
        if ($fp === false) {
            return; // Échec silencieux pour ne pas bloquer le rendu
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
        $viewMTime = file_exists($viewPath) ? filemtime($viewPath) : 0;
        
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
            
            $hashData['header_mtime'] = file_exists($headerPath) ? filemtime($headerPath) : 0;
            $hashData['footer_mtime'] = file_exists($footerPath) ? filemtime($footerPath) : 0;
        }

        // Utiliser un hash rapide (sha256 pour éviter les collisions, md5 serait plus rapide mais moins sûr)
        // Pour de meilleures performances, on pourrait utiliser xxh3 si l'extension est disponible
        $json = json_encode($hashData, JSON_THROW_ON_ERROR);
        $hash = hash('sha256', $json);
        // Limiter la longueur du hash pour des noms de fichiers plus courts
        $hash = substr($hash, 0, 16);
        $cacheKey = str_replace('/', '_', $this->name) . '_' . ($this->complete ? 'full' : 'partial') . '_' . $hash;
        
        return rtrim(self::$cacheDir, '/') . '/' . $cacheKey . '.cache';
    }

    /**
     * Retourne le chemin complet vers le fichier de vue
     */
    private function getViewPath(string $viewsPath): string
    {
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
        
        return $viewsPath 
            . DIRECTORY_SEPARATOR 
            . $category 
            . DIRECTORY_SEPARATOR 
            . $prefix 
            . $filename 
            . '.html.php';
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

