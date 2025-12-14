<?php

declare(strict_types=1);

namespace JulienLinard\Core;

use JulienLinard\Core\Exceptions\NotFoundException;
use JulienLinard\Core\Exceptions\ValidationException;
use JulienLinard\Core\Logging\LoggerInterface;
use JulienLinard\Core\Logging\SimpleLogger;
use JulienLinard\Router\Response;

/**
 * Gestionnaire d'erreurs amélioré
 */
class ErrorHandler
{
    private LoggerInterface $logger;
    private bool $debug;
    private ?string $viewsPath;
    private Application $app;
    private static array $errorPageCache = [];

    /**
     * Clés sensibles à redacter du logging
     */
    private const SENSITIVE_KEYS = [
        'password',
        'passwd',
        'pwd',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'api_secret',
        'secret',
        'private_key',
        'credit_card',
        'authorization',
        'cookie',
        'session_id',
        'jwt',
    ];

    public function __construct(Application $app, ?LoggerInterface $logger = null, bool $debug = false, ?string $viewsPath = null)
    {
        $this->app = $app;
        $this->logger = $logger ?? new SimpleLogger();
        $this->debug = $debug;
        $this->viewsPath = $viewsPath ?? $app->getViewsPath();
    }

    /**
     * Gère une exception et retourne une réponse HTTP appropriée
     */
    public function handle(\Throwable $e): Response
    {
        // Logger l'erreur
        $this->logException($e);

        // PRIORITÉ 1: Si c'est une exception API, toujours retourner du JSON
        // Vérifier d'abord par le nom de classe (plus fiable si la classe n'est pas encore chargée)
        $exceptionClass = get_class($e);
        $isApiException = str_contains($exceptionClass, 'Api\\Exception\\ApiException') ||
            str_contains($exceptionClass, 'ApiException');
        $isApiValidationException = (str_contains($exceptionClass, 'Api\\Exception\\ValidationException') ||
            (str_contains($exceptionClass, 'ValidationException') && str_contains($exceptionClass, 'Api')));

        if ($isApiException || $isApiValidationException) {
            return $this->renderApiError($e);
        }

        // Vérifier aussi avec instanceof si la classe est chargée
        if (class_exists('JulienLinard\Api\Exception\ApiException') && $e instanceof \JulienLinard\Api\Exception\ApiException) {
            return $this->renderApiError($e);
        }

        if (class_exists('JulienLinard\Api\Exception\ValidationException') && $e instanceof \JulienLinard\Api\Exception\ValidationException) {
            return $this->renderApiError($e);
        }

        // PRIORITÉ 2: Vérifier si c'est une requête API (Content-Type: application/json ou route /api/*)
        $isApiRequest = $this->isApiRequest();

        // Gérer selon le type d'exception
        if ($e instanceof NotFoundException) {
            $message = $e->getMessage() ?: 'La ressource demandée n\'existe pas.';
            if ($isApiRequest) {
                return $this->renderApiJsonError(404, 'Not Found', $message);
            }
            return $this->renderErrorPage(404, 'Page non trouvée', $message);
        }

        if ($e instanceof ValidationException) {
            if ($isApiRequest) {
                return $this->renderApiJsonError(422, 'Validation Error', $e->getMessage(), $e->getErrors());
            }
            return $this->renderErrorPage(422, 'Erreur de validation', $e->getMessage(), $e->getErrors());
        }

        // Erreur serveur générique
        $message = $this->debug ? $e->getMessage() : 'Une erreur est survenue.';
        if ($isApiRequest) {
            return $this->renderApiJsonError(500, 'Internal Server Error', $message);
        }
        return $this->renderErrorPage(500, 'Erreur serveur', $message);
    }

    /**
     * Vérifie si la requête est une requête API
     */
    private function isApiRequest(): bool
    {
        // Vérifier le Content-Type ou Accept header
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($contentType, 'application/json') || str_contains($accept, 'application/json')) {
            return true;
        }

        // Vérifier si l'URI commence par /api (mais pas /api/docs qui est Swagger UI)
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($requestUri, PHP_URL_PATH) ?? $requestUri;

        // Si c'est une route API (commence par /api) mais pas Swagger UI
        if (str_starts_with($path, '/api')) {
            // Exclure Swagger UI qui peut retourner du HTML
            if (!str_starts_with($path, '/api/docs') && !str_starts_with($path, '/api/swagger')) {
                return true;
            }
        }

        // Vérifier aussi via la variable d'environnement ou le contexte de l'application
        // Si on a une exception ApiException, c'est forcément une requête API
        return false;
    }

    /**
     * Rend une erreur API au format JSON
     */
    private function renderApiError(\Throwable $e): Response
    {
        if (class_exists('JulienLinard\Api\Exception\ProblemDetails')) {
            $baseUrl = 'http://localhost'; // Par défaut
            try {
                $config = $this->app->getConfig();
                if ($config && method_exists($config, 'get')) {
                    $baseUrl = $config->get('app.url', $baseUrl);
                }
            } catch (\Throwable $ex) {
                // Ignorer si getConfig() n'existe pas
            }

            $problem = \JulienLinard\Api\Exception\ProblemDetails::fromException($e, $baseUrl);
            $response = new Response($problem->status, json_encode($problem->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $response->setHeader('Content-Type', 'application/json');
            return $response;
        }

        // Fallback si ProblemDetails n'est pas disponible
        return $this->renderApiJsonError(
            $e instanceof \JulienLinard\Api\Exception\ApiException ? $e->getStatusCode() : 500,
            'Error',
            $e->getMessage()
        );
    }

    /**
     * Rend une erreur API simple au format JSON
     */
    private function renderApiJsonError(int $code, string $title, string $message, array $errors = []): Response
    {
        $data = [
            'error' => $title,
            'message' => $message,
            'status' => $code,
        ];

        if (!empty($errors)) {
            $data['errors'] = $errors;
        }

        if ($this->debug) {
            $data['debug'] = [
                'file' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5),
            ];
        }

        $response = new Response($code, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $response->setHeader('Content-Type', 'application/json');
        return $response;
    }

    /**
     * Log une exception
     */
    private function logException(\Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        if ($e instanceof ValidationException) {
            $context['errors'] = $e->getErrors();
            // ✅ Redacter les données sensibles avant de logger
            $context = $this->redactSensitiveData($context);
            $this->logger->warning($e->getMessage(), $context);
        } elseif ($e instanceof NotFoundException) {
            $this->logger->notice($e->getMessage(), $context);
        } else {
            // ✅ Redacter les données sensibles avant de logger
            $context = $this->redactSensitiveData($context);
            $this->logger->error($e->getMessage(), $context);
        }
    }

    /**
     * Rend une page d'erreur
     */
    private function renderErrorPage(int $code, string $title, string $message, array $errors = []): Response
    {
        // Essayer de charger une vue d'erreur personnalisée
        $errorViewPath = $this->viewsPath . DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR . $code . '.html.php';

        if (file_exists($errorViewPath)) {
            ob_start();
            extract([
                'title' => $title,
                'message' => $message,
                'errors' => $errors,
                'code' => $code,
            ]);
            require $errorViewPath;
            $content = ob_get_clean();
            return new Response($code, $content);
        }

        // Sinon, générer une page d'erreur par défaut
        $html = $this->generateErrorPageHtml($code, $title, $message, $errors);
        return new Response($code, $html);
    }

    /**
     * Génère le HTML d'une page d'erreur par défaut
     * Utilise un cache pour éviter de régénérer le même HTML
     */
    private function generateErrorPageHtml(int $code, string $title, string $message, array $errors = []): string
    {
        // Créer une clé de cache basée sur le code, titre, message et erreurs
        $cacheKey = md5($code . '_' . $title . '_' . $message . '_' . serialize($errors));

        // Vérifier le cache
        if (isset(self::$errorPageCache[$cacheKey])) {
            return self::$errorPageCache[$cacheKey];
        }

        $html = '<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #667eea;
            margin: 0;
        }
        .error-title {
            font-size: 24px;
            color: #333;
            margin: 20px 0 10px;
        }
        .error-message {
            color: #666;
            margin: 20px 0;
        }
        .error-list {
            text-align: left;
            margin: 20px 0;
            padding: 0;
            list-style: none;
        }
        .error-list li {
            padding: 8px;
            background: #f5f5f5;
            margin: 5px 0;
            border-radius: 4px;
        }
        .debug-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: left;
            font-size: 12px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-code">' . $code . '</h1>
        <h2 class="error-title">' . htmlspecialchars($title) . '</h2>
        <p class="error-message">' . htmlspecialchars($message) . '</p>';

        if (!empty($errors)) {
            $html .= '<ul class="error-list">';
            foreach ($errors as $field => $errorMessages) {
                if (is_array($errorMessages)) {
                    foreach ($errorMessages as $errorMessage) {
                        $html .= '<li><strong>' . htmlspecialchars($field) . ':</strong> ' . htmlspecialchars($errorMessage) . '</li>';
                    }
                } else {
                    $html .= '<li><strong>' . htmlspecialchars($field) . ':</strong> ' . htmlspecialchars($errorMessages) . '</li>';
                }
            }
            $html .= '</ul>';
        }

        $html .= '
    </div>
</body>
</html>';

        // Mettre en cache (limiter la taille du cache pour éviter la surconsommation mémoire)
        if (count(self::$errorPageCache) < 50) {
            self::$errorPageCache[$cacheKey] = $html;
        }

        return $html;
    }

    /**
     * ✅ Vide le cache des pages d'erreur
     */
    public static function clearErrorCache(): void
    {
        self::$errorPageCache = [];
    }

    /**
     * ✅ Redacte les données sensibles d'un tableau
     * Remplace les valeurs des clés sensibles par '***REDACTED***'
     *
     * @param array $data Données à redacter
     * @param int $depth Profondeur actuelle (pour éviter les boucles infinies)
     * @return array Données redactées
     */
    private function redactSensitiveData(array $data, int $depth = 0): array
    {
        // Limiter la profondeur pour éviter les boucles infinies
        if ($depth > 10) {
            return [];
        }

        $redacted = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            // Vérifier si la clé est sensible
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (strpos($lowerKey, strtolower($sensitiveKey)) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $redacted[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactSensitiveData($value, $depth + 1);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }
}
