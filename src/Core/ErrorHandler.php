<?php

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

        // Gérer selon le type d'exception
        if ($e instanceof NotFoundException) {
            return $this->renderErrorPage(404, 'Page non trouvée', 'La ressource demandée n\'existe pas.');
        }

        if ($e instanceof ValidationException) {
            return $this->renderErrorPage(422, 'Erreur de validation', $e->getMessage(), $e->getErrors());
        }

        // Erreur serveur générique
        return $this->renderErrorPage(500, 'Erreur serveur', $this->debug ? $e->getMessage() : 'Une erreur est survenue.');
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
            $this->logger->warning($e->getMessage(), $context);
        } elseif ($e instanceof NotFoundException) {
            $this->logger->notice($e->getMessage(), $context);
        } else {
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
     */
    private function generateErrorPageHtml(int $code, string $title, string $message, array $errors = []): string
    {
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

        return $html;
    }
}

