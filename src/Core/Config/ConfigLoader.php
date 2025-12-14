<?php

declare(strict_types=1);

namespace JulienLinard\Core\Config;

/**
 * Chargeur de configuration depuis des fichiers PHP
 */
class ConfigLoader
{
    /**
     * Cache statique pour éviter de recharger la configuration plusieurs fois
     */
    private static array $cache = [];

    /**
     * Charge la configuration depuis un répertoire
     * 
     * Les fichiers PHP dans le répertoire sont chargés et fusionnés dans la configuration.
     * Le nom du fichier (sans extension) devient la clé de configuration.
     * 
     * @param string $configPath Chemin vers le répertoire de configuration
     * @return array Tableau de configuration fusionné
     */
    public static function load(string $configPath): array
    {
        // Utiliser le cache si disponible
        if (isset(self::$cache[$configPath])) {
            return self::$cache[$configPath];
        }

        $config = [];

        if (!is_dir($configPath)) {
            return $config;
        }

        // ✅ Vérifier les permissions du répertoire
        if (!is_readable($configPath)) {
            throw new \RuntimeException(
                "Configuration directory is not readable: {$configPath}"
            );
        }

        // Utiliser opendir/readdir au lieu de glob() pour de meilleures performances
        $handle = opendir($configPath);
        if ($handle === false) {
            throw new \RuntimeException(
                "Cannot open configuration directory: {$configPath}"
            );
        }

        try {
            while (($file = readdir($handle)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $configPath . DIRECTORY_SEPARATOR . $file;

                // Vérifier l'extension
                if (!is_file($filePath)) {
                    continue;
                }

                if (pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
                    continue;
                }

                // ✅ Valider le nom de fichier (prévenir injection)
                $key = basename($file, '.php');

                // Autoriser: lettres, chiffres, tiret, underscore
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $key)) {
                    trigger_error(
                        "Skipping config file with invalid name: {$file}",
                        E_USER_WARNING
                    );
                    continue;
                }

                // ✅ Vérifier qu'on ne sort pas du répertoire autorisé
                $realPath = realpath($filePath);
                $realDir = realpath($configPath);
                if (
                    $realPath === false || $realDir === false ||
                    !str_starts_with($realPath, $realDir . DIRECTORY_SEPARATOR) &&
                    $realPath !== $realDir
                ) {
                    throw new \RuntimeException(
                        "Security error: Config file outside allowed directory"
                    );
                }

                // Charger la configuration
                $fileConfig = require $filePath;
                if (is_array($fileConfig)) {
                    $config[$key] = $fileConfig;
                }
            }
        } finally {
            closedir($handle);
        }

        // Mettre en cache
        self::$cache[$configPath] = $config;

        return $config;
    }

    /**
     * Charge la configuration depuis un répertoire et l'applique à une instance Config
     * 
     * @param Config $config Instance de Config
     * @param string $configPath Chemin vers le répertoire de configuration
     * @return void
     */
    public static function loadInto(Config $config, string $configPath): void
    {
        $loaded = self::load($configPath);

        foreach ($loaded as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $config->set("{$key}.{$subKey}", $subValue);
                }
            } else {
                $config->set($key, $value);
            }
        }
    }

    /**
     * Vide le cache de configuration
     * 
     * Utile pour recharger la configuration après modification des fichiers
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
