<?php

namespace JulienLinard\Core\Config;

/**
 * Chargeur de configuration depuis des fichiers PHP
 */
class ConfigLoader
{
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
        $config = [];
        
        if (!is_dir($configPath)) {
            return $config;
        }
        
        $files = glob($configPath . DIRECTORY_SEPARATOR . '*.php');
        
        foreach ($files as $file) {
            $key = basename($file, '.php');
            $config[$key] = require $file;
        }
        
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
}

