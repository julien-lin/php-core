<?php

namespace JulienLinard\Core\Controller;

use JulienLinard\Router\Response;
use JulienLinard\Core\View\View;
use JulienLinard\Core\Application;

/**
 * Classe de base pour tous les contrôleurs
 */
abstract class Controller implements ControllerInterface
{
    /**
     * Retourne une vue
     *
     * @param string $name Nom de la vue (ex: 'home/index')
     * @param array $data Données à passer à la vue
     * @param bool $complete Si true, inclut le layout (header/footer)
     * @return void
     */
    protected function view(string $name, array $data = [], bool $complete = true): void
    {
        $view = new View($name, $complete);
        $view->render($data);
    }

    /**
     * Redirige vers une URL
     *
     * @param string $uri URI de destination
     * @param int $status Code de statut HTTP (302 par défaut)
     * @param array $headers Headers additionnels
     * @return void
     */
    protected function redirect(string $uri, int $status = 302, array $headers = []): void
    {
        // Définir le code de statut HTTP
        http_response_code($status);

        // Ajouter les headers personnalisés (sécurisés)
        foreach ($headers as $name => $value) {
            $name = $this->sanitizeHeaderName($name);
            $value = $this->sanitizeHeaderValue($value);
            header("$name: $value");
        }

        // Rediriger vers l'URI
        $uri = $this->sanitizeHeaderValue($uri);
        header("Location: $uri");
        exit();
    }

    /**
     * Retourne une réponse JSON
     *
     * @param array $data Données à encoder en JSON
     * @param int $status Code de statut HTTP (200 par défaut)
     * @return void
     */
    protected function json(array $data, int $status = 200): void
    {
        $response = Response::json($data, $status);
        $response->send();
        exit();
    }

    /**
     * Retourne à la page précédente (si disponible)
     */
    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Nettoie le nom d'un header pour éviter les injections
     */
    private function sanitizeHeaderName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9\-]/', '', $name);
    }

    /**
     * Nettoie la valeur d'un header pour éviter les injections CRLF
     */
    private function sanitizeHeaderValue(string $value): string
    {
        // Supprimer les retours à la ligne et les caractères de contrôle
        $value = str_replace(["\r", "\n"], '', $value);
        // Supprimer les caractères de contrôle (0x00-0x1F sauf tab)
        $value = preg_replace('/[\x00-\x08\x0B-\x1F]/', '', $value);
        return $value;
    }

    /**
     * Retourne l'instance de l'application
     */
    protected function app(): Application
    {
        return Application::getInstance();
    }
}

