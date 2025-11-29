<?php

declare(strict_types=1);

namespace JulienLinard\Core\Middleware;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * Middleware de protection CSRF
 */
class CsrfMiddleware implements Middleware
{
    private string $tokenName;
    private string $sessionKey;

    public function __construct(string $tokenName = '_token', string $sessionKey = '_csrf_token')
    {
        $this->tokenName = $tokenName;
        $this->sessionKey = $sessionKey;
    }

    /**
     * Gère la requête et vérifie le token CSRF
     */
    public function handle(Request $request): ?Response
    {
        // Démarrer la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Méthodes qui nécessitent une vérification CSRF
        $methodsToCheck = ['POST', 'PUT', 'PATCH', 'DELETE'];

        if (!in_array(strtoupper($request->getMethod()), $methodsToCheck)) {
            // Pour les autres méthodes, générer un nouveau token
            $this->generateToken();
            return null; // Continuer l'exécution
        }

        // Vérifier le token CSRF
        $token = $this->getTokenFromRequest($request);
        $sessionToken = $_SESSION[$this->sessionKey] ?? null;

        if ($token === null || $sessionToken === null || !hash_equals($sessionToken, $token)) {
            // Token invalide ou manquant - retourner une réponse d'erreur
            return Response::json([
                'error' => 'CSRF token mismatch',
                'message' => 'Le token CSRF est invalide ou manquant.'
            ], 403);
        }

        // Token valide, générer un nouveau token pour la prochaine requête
        $this->generateToken();
        return null; // Continuer l'exécution
    }

    /**
     * Génère un nouveau token CSRF et le stocke en session
     */
    private function generateToken(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[$this->sessionKey])) {
            $_SESSION[$this->sessionKey] = bin2hex(random_bytes(32));
        }
    }

    /**
     * Récupère le token CSRF depuis la requête
     */
    private function getTokenFromRequest(Request $request): ?string
    {
        // Essayer depuis les données POST
        $postData = $request->getPost();
        if (isset($postData[$this->tokenName])) {
            return $postData[$this->tokenName];
        }

        // Essayer depuis les headers
        $headerToken = $request->getHeader('X-CSRF-TOKEN');
        if ($headerToken !== null) {
            return $headerToken;
        }

        return null;
    }

    /**
     * Retourne le token CSRF actuel (pour les formulaires)
     */
    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionKey = '_csrf_token';
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }

        return $_SESSION[$sessionKey];
    }

    /**
     * Génère un champ hidden pour les formulaires
     */
    public static function field(string $tokenName = '_token'): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="' . htmlspecialchars($tokenName) . '" value="' . htmlspecialchars($token) . '">';
    }
}

