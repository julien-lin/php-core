<?php

declare(strict_types=1);

namespace JulienLinard\Core\Middleware;

use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * Middleware pour compresser les réponses HTTP (gzip)
 *
 * Exemple d'utilisation :
 *   $router->middleware(new CompressionMiddleware([
 *       'level' => 6,
 *       'minSize' => 1024,
 *   ]));
 */
class CompressionMiddleware implements MiddlewareInterface
{
    private int $compressionLevel;
    private int $minSize;
    private array $contentTypes;

    /**
     * @param array $config Configuration
     *   - level: Niveau de compression (1-9, défaut: 6)
     *   - minSize: Taille minimum en bytes pour compresser (défaut: 1024)
     *   - contentTypes: Types MIME à compresser (défaut: text/html, text/css, application/javascript, application/json)
     */
    public function __construct(array $config = [])
    {
        $this->compressionLevel = $config['level'] ?? 6;
        $this->minSize = $config['minSize'] ?? 1024;
        $this->contentTypes = $config['contentTypes'] ?? [
            'text/html',
            'text/css',
            'text/javascript',
            'application/javascript',
            'application/json',
            'text/xml',
            'application/xml',
        ];
    }

    public function handle(Request $request): ?Response
    {
        // Ce middleware ne bloque pas la requête
        // La compression sera appliquée à la réponse
        return null;
    }

    /**
     * Compresse une réponse si nécessaire
     *
     * @param Response $response Réponse à compresser
     * @return Response Réponse compressée ou originale
     */
    public function compress(Response $response): Response
    {
        // Vérifier si la compression est supportée
        if (!$this->isCompressionSupported()) {
            return $response;
        }

        $content = $response->getContent();
        $contentLength = strlen($content);

        // Ne pas compresser si trop petit
        if ($contentLength < $this->minSize) {
            return $response;
        }

        // Vérifier le Content-Type
        $contentType = $response->getHeader('Content-Type', '');
        if (!$this->shouldCompressContentType($contentType)) {
            return $response;
        }

        // Compresser le contenu
        $compressed = gzencode($content, $this->compressionLevel);

        if ($compressed === false) {
            return $response; // Échec de compression, retourner l'original
        }

        // Créer une nouvelle réponse avec le contenu compressé
        $compressedResponse = new Response(
            $response->getStatusCode(),
            $compressed
        );
        
        // Copier les headers existants
        foreach ($response->getHeaders() as $name => $value) {
            $compressedResponse->setHeader($name, $value);
        }

        // Ajouter les headers de compression
        $compressedResponse->setHeader('Content-Encoding', 'gzip');
        $compressedResponse->setHeader('Vary', 'Accept-Encoding');
        
        // Mettre à jour Content-Length
        $compressedResponse->setHeader('Content-Length', (string) strlen($compressed));

        return $compressedResponse;
    }

    /**
     * Vérifie si la compression est supportée par le client
     */
    private function isCompressionSupported(): bool
    {
        if (!function_exists('gzencode')) {
            return false;
        }

        $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        return str_contains($acceptEncoding, 'gzip');
    }

    /**
     * Vérifie si le Content-Type doit être compressé
     */
    private function shouldCompressContentType(string $contentType): bool
    {
        if (empty($contentType)) {
            return true; // Par défaut, compresser si pas de Content-Type spécifique
        }

        // Extraire le type MIME (avant le point-virgule)
        $mimeType = explode(';', $contentType)[0];
        $mimeType = trim($mimeType);

        foreach ($this->contentTypes as $allowedType) {
            if (str_contains($mimeType, $allowedType)) {
                return true;
            }
        }

        return false;
    }
}
