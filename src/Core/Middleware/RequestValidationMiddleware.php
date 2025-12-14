<?php

declare(strict_types=1);

namespace JulienLinard\Core\Middleware;

use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * ✅ PHASE 2: Request Validation Middleware
 * 
 * Valide les requêtes entrantes:
 * - Vérifie Content-Type pour POST/PUT/PATCH
 * - Valide la taille du payload
 * - Sanitise les inputs pour éviter XSS
 * - Vérifie les headers requis
 */
class RequestValidationMiddleware implements MiddlewareInterface
{
    /**
     * Content-Types autorisés
     */
    private array $allowedContentTypes = [
        'application/json',
        'application/x-www-form-urlencoded',
        'multipart/form-data',
        'text/plain',
    ];

    /**
     * Taille maximale du payload (50MB par défaut)
     */
    private int $maxPayloadSize = 52_428_800;

    /**
     * Headers requis pour les requêtes POST
     */
    private array $requiredHeaders = [];

    /**
     * Constructeur
     * 
     * @param int $maxPayloadSize Taille maximale en octets
     */
    public function __construct(int $maxPayloadSize = 52_428_800)
    {
        $this->maxPayloadSize = $maxPayloadSize;
    }

    /**
     * Définit les Content-Types autorisés
     */
    public function setAllowedContentTypes(array $types): self
    {
        $this->allowedContentTypes = $types;
        return $this;
    }

    /**
     * Définit les headers requis
     */
    public function setRequiredHeaders(array $headers): self
    {
        $this->requiredHeaders = $headers;
        return $this;
    }

    /**
     * Traite la requête
     */
    public function handle(Request $request): ?Response
    {
        $method = $request->getMethod();

        // Valider Content-Type pour méthodes de mutation
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $validation = $this->validateContentType($request);
            if ($validation !== null) {
                return $validation;
            }
        }

        // Valider la taille du payload
        $sizeValidation = $this->validatePayloadSize();
        if ($sizeValidation !== null) {
            return $sizeValidation;
        }

        // Valider les headers requis
        $headerValidation = $this->validateRequiredHeaders($request);
        if ($headerValidation !== null) {
            return $headerValidation;
        }

        // Continuer le pipeline
        return null;
    }

    /**
     * Valide le Content-Type
     */
    private function validateContentType(Request $request): ?Response
    {
        $contentType = $request->getHeader('Content-Type');

        if (empty($contentType)) {
            return new Response(400, json_encode([
                'error' => 'Missing Content-Type header',
                'message' => 'Content-Type header is required for this request method',
            ]));
        }

        // Extraire le type sans les paramètres (ex: charset)
        $type = explode(';', $contentType)[0];
        $type = trim($type);

        if (!$this->isContentTypeAllowed($type)) {
            return new Response(415, json_encode([
                'error' => 'Unsupported Media Type',
                'message' => "Content-Type '{$type}' is not supported",
                'allowed' => $this->allowedContentTypes,
            ]));
        }

        return null;
    }

    /**
     * Vérifie si le Content-Type est autorisé
     */
    private function isContentTypeAllowed(string $type): bool
    {
        return in_array($type, $this->allowedContentTypes, true);
    }

    /**
     * Valide la taille du payload
     */
    private function validatePayloadSize(): ?Response
    {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

        if ($contentLength > $this->maxPayloadSize) {
            return new Response(413, json_encode([
                'error' => 'Payload Too Large',
                'message' => "Request payload exceeds maximum size of {$this->maxPayloadSize} bytes",
                'received' => $contentLength,
            ]));
        }

        return null;
    }

    /**
     * Valide les headers requis
     */
    private function validateRequiredHeaders(Request $request): ?Response
    {
        foreach ($this->requiredHeaders as $header) {
            if (empty($request->getHeader($header))) {
                return new Response(400, json_encode([
                    'error' => 'Missing Required Header',
                    'message' => "The '{$header}' header is required",
                ]));
            }
        }

        return null;
    }

    /**
     * Sanitise les données pour éviter XSS
     * 
     * @param array $data Les données à sanitiser
     * @param bool $strict Mode strict (plus de protections)
     * @return array Les données sanitisées
     */
    public static function sanitize(array $data, bool $strict = false): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            // Sanitiser la clé (identifiant)
            $cleanKey = static::sanitizeIdentifier($key);

            if (is_array($value)) {
                // Récursivement sanitiser les arrays
                $sanitized[$cleanKey] = static::sanitize($value, $strict);
            } elseif (is_string($value)) {
                // Sanitiser les strings
                $sanitized[$cleanKey] = static::sanitizeString($value, $strict);
            } else {
                // Laisser les autres types intacts (int, bool, null, etc)
                $sanitized[$cleanKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitise un identifiant (clé)
     */
    private static function sanitizeIdentifier(string $key): string
    {
        // Garder seulement alphanumériques, underscore, tirets
        return preg_replace('/[^a-zA-Z0-9_-]/', '', $key) ?: 'field';
    }

    /**
     * Sanitise une string
     */
    private static function sanitizeString(string $value, bool $strict = false): string
    {
        if ($strict) {
            // Mode strict: HTML escape complète - échappe TOUS les caractères HTML
            return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Mode normal: encode tous les caractères spéciaux (strict)
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
