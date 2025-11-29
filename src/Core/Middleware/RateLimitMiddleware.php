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
            mkdir($this->storagePath, 0777, true);
        }
    }

    public function handle(Request $request): ?Response
    {
        $ip = $request->getClientIp() ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $route = $request->getPath();
        $key = md5($ip . '_' . $route);
        $file = $this->storagePath . '/' . $key . '.json';
        $now = time();

        $data = [
            'start' => $now,
            'count' => 0
        ];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? $data;
            // Si la fenêtre est dépassée, on réinitialise
            if ($now - $data['start'] > $this->windowSeconds) {
                $data = ['start' => $now, 'count' => 0];
            }
        }
        $data['count']++;
        file_put_contents($file, json_encode($data));

        if ($data['count'] > $this->maxRequests) {
            return new Response(429, 'Trop de requêtes. Réessayez plus tard.');
        }
        return null; // Continue la chaîne de middlewares
    }
}
