<?php

declare(strict_types=1);

namespace JulienLinard\Core\Middleware;

use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * Middleware pour ajouter les headers de sécurité HTTP
 *
 * Exemple d'utilisation :
 *   $router->middleware(new SecurityHeadersMiddleware([
 *       'csp' => "default-src 'self'",
 *       'hsts' => 'max-age=31536000; includeSubDomains',
 *   ]));
 */
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private array $headers;

    /**
     * @param array $config Configuration des headers
     *   - csp: Content-Security-Policy
     *   - hsts: Strict-Transport-Security
     *   - xFrameOptions: X-Frame-Options (DENY, SAMEORIGIN, ALLOW-FROM)
     *   - xContentTypeOptions: X-Content-Type-Options (nosniff)
     *   - referrerPolicy: Referrer-Policy
     *   - permissionsPolicy: Permissions-Policy
     *   - xXssProtection: X-XSS-Protection (0, 1, 1; mode=block)
     */
    public function __construct(array $config = [])
    {
        $this->headers = [
            'csp' => $config['csp'] ?? "default-src 'self'",
            'hsts' => $config['hsts'] ?? null,
            'xFrameOptions' => $config['xFrameOptions'] ?? 'SAMEORIGIN',
            'xContentTypeOptions' => $config['xContentTypeOptions'] ?? 'nosniff',
            'referrerPolicy' => $config['referrerPolicy'] ?? 'strict-origin-when-cross-origin',
            'permissionsPolicy' => $config['permissionsPolicy'] ?? null,
            'xXssProtection' => $config['xXssProtection'] ?? '1; mode=block',
        ];
    }

    public function handle(Request $request): ?Response
    {
        // Ce middleware ne bloque pas la requête, il ajoute juste des headers
        // Les headers seront ajoutés à la réponse dans le router
        return null;
    }

    /**
     * Retourne les headers de sécurité à ajouter à la réponse
     *
     * @return array Headers HTTP [nom => valeur]
     */
    public function getHeaders(): array
    {
        $headers = [];

        // Content-Security-Policy
        if ($this->headers['csp'] !== null) {
            $headers['Content-Security-Policy'] = $this->headers['csp'];
        }

        // Strict-Transport-Security (seulement en HTTPS)
        if ($this->headers['hsts'] !== null && $this->isHttps()) {
            $headers['Strict-Transport-Security'] = $this->headers['hsts'];
        }

        // X-Frame-Options
        if ($this->headers['xFrameOptions'] !== null) {
            $headers['X-Frame-Options'] = $this->headers['xFrameOptions'];
        }

        // X-Content-Type-Options
        if ($this->headers['xContentTypeOptions'] !== null) {
            $headers['X-Content-Type-Options'] = $this->headers['xContentTypeOptions'];
        }

        // Referrer-Policy
        if ($this->headers['referrerPolicy'] !== null) {
            $headers['Referrer-Policy'] = $this->headers['referrerPolicy'];
        }

        // Permissions-Policy (anciennement Feature-Policy)
        if ($this->headers['permissionsPolicy'] !== null) {
            $headers['Permissions-Policy'] = $this->headers['permissionsPolicy'];
        }

        // X-XSS-Protection (déprécié mais encore utile pour anciens navigateurs)
        if ($this->headers['xXssProtection'] !== null) {
            $headers['X-XSS-Protection'] = $this->headers['xXssProtection'];
        }

        return $headers;
    }

    /**
     * Vérifie si la requête est en HTTPS
     */
    private function isHttps(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );
    }

    /**
     * Applique les headers à une réponse existante
     *
     * @param Response $response Réponse à modifier
     * @return Response Réponse modifiée
     */
    public function applyToResponse(Response $response): Response
    {
        $headers = $this->getHeaders();
        
        foreach ($headers as $name => $value) {
            $response->setHeader($name, $value);
        }

        return $response;
    }
}
