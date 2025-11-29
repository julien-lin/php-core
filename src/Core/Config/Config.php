<?php

declare(strict_types=1);

namespace JulienLinard\Core\Config;

/**
 * Gestionnaire de configuration
 */
class Config
{
    /**
     * Données de configuration
     */
    private array $config = [];

    /**
     * Constructeur
     */
    public function __construct()
    {
        // Charger les variables d'environnement par défaut
        $this->loadFromEnv();
    }

    /**
     * Charge la configuration depuis les variables d'environnement
     */
    private function loadFromEnv(): void
    {
        // Charger depuis $_ENV et $_SERVER
        foreach ($_ENV as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Définit une valeur de configuration
     *
     * @param string $key Clé (peut être en notation point: 'app.name')
     * @param mixed $value Valeur
     */
    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * Récupère une valeur de configuration
     *
     * @param string $key Clé (peut être en notation point: 'app.name')
     * @param mixed $default Valeur par défaut si la clé n'existe pas
     * @return mixed Valeur de configuration
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Vérifie si une clé de configuration existe
     *
     * @param string $key Clé
     * @return bool True si la clé existe
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Charge la configuration depuis un tableau
     *
     * @param array $config Tableau de configuration
     */
    public function load(array $config): void
    {
        $this->config = array_merge_recursive($this->config, $config);
    }

    /**
     * Retourne toute la configuration
     *
     * @return array Toute la configuration
     */
    public function all(): array
    {
        return $this->config;
    }
}

