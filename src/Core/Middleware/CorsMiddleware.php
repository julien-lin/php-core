<?php

declare(strict_types=1);

namespace JulienLinard\Core\Middleware;

use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * ✅ PHASE 2: CORS Middleware
 * 
 * Ajoute les headers CORS appropriés pour autoriser les requêtes cross-origin.
 * Supporte la configuration des origines autorisées, credentials, et méthodes.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Origines autorisées (wildcard: *)
     */
    private string $allowedOrigins = '*';

    /**
     * Méthodes autorisées
     */
    private string $allowedMethods = 'GET,POST,PUT,DELETE,PATCH,OPTIONS';

    /**
     * Headers autorisés
     */
    private string $allowedHeaders = 'Content-Type,Authorization,X-Requested-With';

    /**
     * Autoriser les credentials (cookies, etc)
     */
    private bool $allowCredentials = false;

    /**
     * Max age pour le cache du preflight (en secondes)
     */
    private int $maxAge = 3600;

    /**
     * Constructeur
     * 
     * @param string $allowedOrigins Origines autorisées (défaut: '*' pour toutes)
     * @param bool $allowCredentials Autoriser les credentials (défaut: false)
     */
    public function __construct(string $allowedOrigins = '*', bool $allowCredentials = false)
    {
        $this->allowedOrigins = $allowedOrigins;
        $this->allowCredentials = $allowCredentials;

        // Si credentials activés, on ne peut pas utiliser '*' pour origin
        if ($this->allowCredentials && $this->allowedOrigins === '*') {
            throw new \InvalidArgumentException(
                'Cannot use wildcard origin ("*") when credentials are allowed'
            );
        }
    }

    /**
     * Définit les méthodes autorisées
     */
    public function setAllowedMethods(string $methods): self
    {
        $this->allowedMethods = $methods;
        return $this;
    }

    /**
     * Définit les headers autorisés
     */
    public function setAllowedHeaders(string $headers): self
    {
        $this->allowedHeaders = $headers;
        return $this;
    }

    /**
     * Définit le max age pour cache
     */
    public function setMaxAge(int $seconds): self
    {
        $this->maxAge = $seconds;
        return $this;
    }

    /**
     * Traite la requête
     */
    public function handle(Request $request): ?Response
    {
        // Déterminer l'origine de la requête
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Vérifier si l'origine est autorisée
        if (!$this->isOriginAllowed($origin)) {
            // Rejeter silencieusement ou laisser passer sans headers CORS
            return null;
        }

        // Gérer les requêtes preflight (OPTIONS)
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(204); // No Content
            $this->addCorsHeaders($response, $origin);
            return $response;
        }

        // Pour les autres méthodes, retourner null pour continuer
        // mais enregistrer l'origine pour un traitement ultérieur
        $_SERVER['_CORS_ORIGIN'] = $origin;
        return null;
    }

    /**
     * Vérifie si l'origine est autorisée
     */
    private function isOriginAllowed(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        if ($this->allowedOrigins === '*') {
            return true;
        }

        // Vérifier si l'origine est dans la liste blanche (séparée par virgules)
        $origins = array_map('trim', explode(',', $this->allowedOrigins));
        return in_array($origin, $origins, true);
    }

    /**
     * Ajoute les headers CORS à la réponse
     */
    private function addCorsHeaders(Response $response, string $origin): void
    {
        // Toujours ajouter ces headers
        $response->setHeader('Access-Control-Allow-Origin', $origin);
        $response->setHeader('Access-Control-Allow-Methods', $this->allowedMethods);
        $response->setHeader('Access-Control-Allow-Headers', $this->allowedHeaders);
        $response->setHeader('Access-Control-Max-Age', (string) $this->maxAge);

        // Ajouter le header credentials si nécessaire
        if ($this->allowCredentials) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Header pour exposer les headers personnalisés à JavaScript
        $response->setHeader('Access-Control-Expose-Headers', 'Content-Length,X-JSON-Response');
    }
}
