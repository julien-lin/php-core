<?php

namespace JulienLinard\Core;

use JulienLinard\Router\Router;
use JulienLinard\Core\Container\Container;
use JulienLinard\Core\Config\Config;
use JulienLinard\Core\ErrorHandler;
use JulienLinard\Core\Events\EventDispatcher;
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
    private ?ErrorHandler $errorHandler = null;
    private EventDispatcher $events;

    /**
     * Constructeur privé (utiliser create())
     */
    private function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, DIRECTORY_SEPARATOR);
        $this->container = new Container();
        $this->router = new Router();
        $this->config = new Config();
        $this->events = new EventDispatcher();
        
        // Passer le Container au Router pour l'injection de dépendances
        $this->router->setContainer($this->container);
        
        // Chemins par défaut
        $this->viewsPath = $this->basePath . DIRECTORY_SEPARATOR . 'views';
        $this->partialsPath = $this->viewsPath . DIRECTORY_SEPARATOR . '_templates';
        
        // Enregistrer l'application dans le container
        $this->container->singleton(Application::class, fn() => $this);
        $this->container->singleton(Router::class, fn() => $this->router);
        $this->container->singleton(Container::class, fn() => $this->container);
        $this->container->singleton(EventDispatcher::class, fn() => $this->events);
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
     * 
     * @return self|null L'instance de l'application ou null si elle n'a pas été créée
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Retourne l'instance unique de l'application ou la crée si elle n'existe pas
     * 
     * Cette méthode est utile dans les cas où l'application doit être accessible
     * même si elle n'a pas été explicitement initialisée (ex: gestion d'erreurs)
     * 
     * @param string|null $basePath Chemin de base de l'application (requis si l'instance n'existe pas)
     * @return self L'instance de l'application
     * @throws \RuntimeException Si l'instance n'existe pas et que $basePath n'est pas fourni
     */
    public static function getInstanceOrCreate(?string $basePath = null): self
    {
        if (self::$instance === null) {
            if ($basePath === null) {
                throw new \RuntimeException(
                    'L\'application n\'a pas été initialisée et aucun chemin de base n\'a été fourni. ' .
                    'Utilisez Application::create($basePath) ou Application::getInstanceOrCreate($basePath).'
                );
            }
            self::$instance = new self($basePath);
        }
        return self::$instance;
    }

    /**
     * Retourne l'instance unique de l'application ou lance une exception si elle n'existe pas
     * 
     * @return self L'instance de l'application
     * @throws \RuntimeException Si l'instance n'a pas été créée
     */
    public static function getInstanceOrFail(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException(
                'L\'application n\'a pas été initialisée. ' .
                'Utilisez Application::create($basePath) avant d\'appeler getInstanceOrFail().'
            );
        }
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
     * Charge la configuration depuis un répertoire de fichiers PHP
     * 
     * @param string $configPath Chemin vers le répertoire de configuration (ex: 'config')
     * @return self
     */
    public function loadConfig(string $configPath = 'config'): self
    {
        $fullPath = $this->basePath . DIRECTORY_SEPARATOR . $configPath;
        \JulienLinard\Core\Config\ConfigLoader::loadInto($this->config, $fullPath);
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
            
            // Déclencher l'événement request.started
            $this->events->dispatch('request.started', ['request' => $request]);
            
            $response = $this->router->handle($request);
            
            // Déclencher l'événement response.created
            $this->events->dispatch('response.created', ['response' => $response]);
            
            $response->send();
            
            // Déclencher l'événement response.sent
            $this->events->dispatch('response.sent', ['response' => $response]);
        } catch (\Throwable $e) {
            // Déclencher l'événement exception.thrown
            $this->events->dispatch('exception.thrown', ['exception' => $e]);
            
            $errorHandler = $this->getErrorHandler();
            $response = $errorHandler->handle($e);
            $response->send();
        }
    }

    /**
     * Retourne le gestionnaire d'erreurs
     */
    public function getErrorHandler(): ErrorHandler
    {
        if ($this->errorHandler === null) {
            $debug = $this->config->get('app.debug', false);
            $this->errorHandler = new ErrorHandler($this, null, $debug);
        }
        return $this->errorHandler;
    }

    /**
     * Définit le gestionnaire d'erreurs personnalisé
     */
    public function setErrorHandler(ErrorHandler $errorHandler): self
    {
        $this->errorHandler = $errorHandler;
        return $this;
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
     * Retourne le gestionnaire d'événements
     */
    public function getEvents(): EventDispatcher
    {
        return $this->events;
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

