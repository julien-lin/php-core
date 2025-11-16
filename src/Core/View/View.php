<?php

namespace JulienLinard\Core\View;

use JulienLinard\Core\Application;

/**
 * Moteur de templates simple
 */
class View
{
    /**
     * Titre par défaut de la page
     */
    public string $title = '';

    /**
     * Chemin vers le fichier de vue
     */
    private string $name;

    /**
     * Si true, inclut le layout (header/footer)
     */
    private bool $complete;

    /**
     * Constructeur
     *
     * @param string $name Nom de la vue (ex: 'home/index')
     * @param bool $complete Si true, inclut le layout
     */
    public function __construct(string $name, bool $complete = true)
    {
        $this->name = $name;
        $this->complete = $complete;
    }

    /**
     * Rendu de la vue
     *
     * @param array $data Données à passer à la vue
     * @return void
     */
    public function render(?array $data = []): void
    {
        $app = Application::getInstance();
        
        if ($app === null) {
            throw new \RuntimeException('L\'application n\'a pas été initialisée.');
        }

        $viewsPath = $app->getViewsPath();
        $partialsPath = $app->getPartialsPath();

        // Extraire les données pour qu'elles soient accessibles dans la vue
        if (!empty($data)) {
            extract($data);
        }

        // Ajouter des helpers utiles
        $escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        $e = $escape; // Alias court

        // Mise en cache du contenu
        ob_start();

        // Inclure le header si vue complète
        if ($this->complete) {
            $headerPath = $partialsPath . DIRECTORY_SEPARATOR . '_header.html.php';
            if (file_exists($headerPath)) {
                require $headerPath;
            }
        }

        // Inclure la vue
        $viewPath = $this->getViewPath($viewsPath);
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("La vue {$this->name} n'existe pas à l'emplacement: {$viewPath}");
        }
        require_once $viewPath;

        // Inclure le footer si vue complète
        if ($this->complete) {
            $footerPath = $partialsPath . DIRECTORY_SEPARATOR . '_footer.html.php';
            if (file_exists($footerPath)) {
                require $footerPath;
            }
        }

        // Libérer le cache et afficher
        ob_end_flush();
    }

    /**
     * Retourne le chemin complet vers le fichier de vue
     */
    private function getViewPath(string $viewsPath): string
    {
        // Séparer le nom de la vue (ex: 'home/index' -> ['home', 'index'])
        $parts = explode('/', $this->name);
        
        if (count($parts) !== 2) {
            throw new \InvalidArgumentException(
                "Le nom de la vue doit être au format 'dossier/fichier' (ex: 'home/index')"
            );
        }

        [$category, $filename] = $parts;
        
        // Préfixe pour les vues partielles
        $prefix = $this->complete ? '' : '_';
        
        return $viewsPath 
            . DIRECTORY_SEPARATOR 
            . $category 
            . DIRECTORY_SEPARATOR 
            . $prefix 
            . $filename 
            . '.html.php';
    }

    /**
     * Crée une nouvelle vue (méthode statique helper)
     */
    public static function make(string $name, array $data = [], bool $complete = true): void
    {
        $view = new self($name, $complete);
        $view->render($data);
    }
}

