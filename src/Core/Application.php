<?php

namespace JulienLinard\Core;

use JulienLinard\Router\Router;
use JulienLinard\Core\Container\Container;
use JulienLinard\Core\Config\Config;
use JulienLinard\Dotenv\Dotenv;

/**
 * Classe principale du framework Core PHP
 */
class Application
{
    private static ?self $instance = null;
    private Container $container;
    private Router $router;
    private Config $config;
    private string $basePath;
    private string $viewsPath;
    private string $partialsPath;
    private bool $started = false;

    /**
     * Constructeur privé (utiliser create())
     */
    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->container = new Container();
        $this->router = new Router();
        $this->config = new Config();
        
        // Chemins par défaut
        $this->viewsPath = $this->basePath . DIRECTORY_SEPARATOR . 'views';
        $this->partialsPath = $this->viewsPath . DIRECTORY_SEPARATOR . '_templates';
        
        // Enregistrer l'application dans le container
        $this->container->singleton(Application::class, fn() => $this);
        $this->container->singleton(Router::class, fn() => $this->router);
        $this->container->singleton(Container::class, fn() => $this->container);
    }

    /**
     * Crée une nouvelle instance de l'application
     */
    public static function create(string $basePath): self
    {
        if (self::$instance === null) {
            self::$instance = new self($basePath);
        }
        return self::$instance;
    }

    /**
     * Retourne l'instance unique de l'application
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Charge la configuration depuis un fichier .env
     */
    public function loadEnv(string $file = '.env'): self
    {
        $dotenv = Dotenv::createImmutable($this->basePath, $file);
        $dotenv->load();
        return $this;
    }

    /**
     * Démarre l'application
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        // Démarrer la session si elle n'est pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->started = true;
    }

    /**
     * Traite une requête HTTP
     */
    public function handle(): void
    {
        $this->start();
        
        try {
            $request = new \JulienLinard\Router\Request();
            $response = $this->router->handle($request);
            $response->send();
        } catch (\Throwable $e) {
            $this->handleException($e);
        }
    }

    /**
     * Gère les exceptions
     */
    private function handleException(\Throwable $e): void
    {
        if (php_sapi_name() === 'cli') {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            return;
        }

        http_response_code(500);
        echo '<h1>Erreur serveur</h1>';
        if ($this->config->get('app.debug', false)) {
            echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
    }

    /**
     * Retourne le container DI
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Retourne le router
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Retourne la configuration
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Définit le chemin des vues
     */
    public function setViewsPath(string $path): self
    {
        $this->viewsPath = $path;
        return $this;
    }

    /**
     * Retourne le chemin des vues
     */
    public function getViewsPath(): string
    {
        return $this->viewsPath;
    }

    /**
     * Définit le chemin des vues partielles
     */
    public function setPartialsPath(string $path): self
    {
        $this->partialsPath = $path;
        return $this;
    }

    /**
     * Retourne le chemin des vues partielles
     */
    public function getPartialsPath(): string
    {
        return $this->partialsPath;
    }

    /**
     * Retourne le chemin de base de l'application
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Vérifie si l'application a démarré
     */
    public function isStarted(): bool
    {
        return $this->started;
    }
}

