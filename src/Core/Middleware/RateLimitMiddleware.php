<?php

declare(strict_types=1);

namespace JulienLinard\Core\Middleware;

use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * Middleware de rate limiting simple (stockage fichier)
 *
 * Exemple d'utilisation :
 *   $router->middleware(new RateLimitMiddleware(100, 60)); // 100 requêtes/minute
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private int $maxRequests;
    private int $windowSeconds;
    private string $storagePath;
    
    /**
     * Cache mémoire simple (durée de la requête) pour améliorer les performances
     * Structure: ['key' => ['start' => timestamp, 'count' => int]]
     */
    private static array $memoryCache = [];
    
    /**
     * Dernière fois que le nettoyage du cache mémoire a été effectué
     */
    private static int $lastCleanup = 0;
    
    /**
     * Intervalle de nettoyage du cache mémoire (secondes)
     */
    private const CLEANUP_INTERVAL = 300; // 5 minutes

    /**
     * @param int $maxRequests Nombre max de requêtes
     * @param int $windowSeconds Fenêtre en secondes
     * @param string|null $storagePath Dossier de stockage (par défaut: sys_get_temp_dir())
     */
    public function __construct(int $maxRequests = 100, int $windowSeconds = 60, ?string $storagePath = null)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
        $this->storagePath = $storagePath ?? sys_get_temp_dir() . '/core-php-rate-limit';
        if (!is_dir($this->storagePath)) {
            // Permissions sécurisées: 0750 (rwxr-x---) au lieu de 0777
            mkdir($this->storagePath, 0750, true);
        }
    }

    public function handle(Request $request): ?Response
    {
        // Nettoyer périodiquement le cache mémoire
        $this->cleanupIfNeeded();
        
        $ip = $request->getClientIp() ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $route = $request->getPath();
        // Utiliser SHA256 au lieu de MD5 pour éviter les collisions
        $key = hash('sha256', $ip . '_' . $route);
        $now = time();

        // Vérifier d'abord le cache mémoire (beaucoup plus rapide)
        if (isset(self::$memoryCache[$key])) {
            $data = self::$memoryCache[$key];
            
            // Vérifier si la fenêtre est toujours valide
            if ($now - $data['start'] <= $this->windowSeconds) {
                $data['count']++;
                self::$memoryCache[$key] = $data;
                
                // Sauvegarder dans le fichier de manière asynchrone (non-bloquant)
                $this->saveToFileAsync($key, $data);
                
                if ($data['count'] > $this->maxRequests) {
                    return new Response(429, 'Trop de requêtes. Réessayez plus tard.');
                }
                return null; // Continue la chaîne de middlewares
            } else {
                // Fenêtre expirée, réinitialiser
                unset(self::$memoryCache[$key]);
            }
        }

        // Si pas dans le cache mémoire, utiliser le système de fichiers
        return $this->handleWithFile($key, $now);
    }

    /**
     * Gère le rate limiting avec le système de fichiers (méthode originale)
     */
    private function handleWithFile(string $key, int $now): ?Response
    {
        $file = $this->storagePath . '/' . $key . '.json';

        // Utiliser un verrouillage de fichier pour éviter les race conditions
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            // Si on ne peut pas ouvrir le fichier, autoriser la requête pour éviter de bloquer
            return null;
        }

        // Verrouiller le fichier en mode exclusif (LOCK_EX)
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            // Si on ne peut pas verrouiller, autoriser la requête
            return null;
        }

        // Lire les données existantes
        $content = stream_get_contents($fp);
        $data = [
            'start' => $now,
            'count' => 0
        ];
        
        if ($content !== false && $content !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        // Si la fenêtre est dépassée, on réinitialise
        if ($now - $data['start'] > $this->windowSeconds) {
            $data = ['start' => $now, 'count' => 0];
        }

        $data['count']++;

        // Mettre en cache mémoire pour les prochaines requêtes
        self::$memoryCache[$key] = $data;

        // Écrire les nouvelles données
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data));
        fflush($fp);

        // Déverrouiller et fermer
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($data['count'] > $this->maxRequests) {
            return new Response(429, 'Trop de requêtes. Réessayez plus tard.');
        }
        return null; // Continue la chaîne de middlewares
    }

    /**
     * Sauvegarde les données dans le fichier de manière asynchrone (non-bloquant)
     */
    private function saveToFileAsync(string $key, array $data): void
    {
        // Sauvegarder dans le fichier en arrière-plan (non-bloquant)
        // Note: En PHP, on ne peut pas vraiment faire d'async, mais on peut
        // utiliser un flag pour éviter de bloquer si le fichier est verrouillé
        $file = $this->storagePath . '/' . $key . '.json';
        @file_put_contents($file, json_encode($data), LOCK_EX | LOCK_NB);
    }

    /**
     * Nettoie le cache mémoire si nécessaire
     */
    private function cleanupIfNeeded(): void
    {
        $now = time();
        if ($now - self::$lastCleanup < self::CLEANUP_INTERVAL) {
            return;
        }
        
        // Nettoyer le cache mémoire (supprimer les entrées expirées)
        foreach (self::$memoryCache as $key => $data) {
            if ($now - $data['start'] > $this->windowSeconds) {
                unset(self::$memoryCache[$key]);
            }
        }
        
        self::$lastCleanup = $now;
    }
}
