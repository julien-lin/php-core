<?php

declare(strict_types=1);

namespace JulienLinard\Core\Logging;

/**
 * Logger simple utilisant error_log
 */
class SimpleLogger implements LoggerInterface
{
    private string $logPath;
    private int $minLevel;
    private ?array $rotationConfig;

    private const LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    /**
     * @param string|null $logPath Chemin du fichier de log
     * @param string|int $minLevel Niveau minimum de log (string: 'debug', 'info', etc. ou int: 0-7)
     * @param array|null $rotationConfig Configuration de rotation
     *   - maxSize: Taille maximum en bytes (défaut: 10MB)
     *   - maxFiles: Nombre maximum de fichiers archivés (défaut: 5)
     *   - compress: Compresser les fichiers archivés (défaut: true)
     */
    public function __construct(?string $logPath = null, string|int $minLevel = 'debug', ?array $rotationConfig = null)
    {
        $this->logPath = $logPath ?? sys_get_temp_dir() . '/app.log';
        
        // Gérer le niveau minimum (string ou int)
        if (is_string($minLevel)) {
            $this->minLevel = self::LEVELS[$minLevel] ?? 0;
        } else {
            $this->minLevel = max(0, min(7, $minLevel));
        }
        
        // Configuration de rotation
        $this->rotationConfig = $rotationConfig ?? [
            'maxSize' => 10 * 1024 * 1024, // 10MB
            'maxFiles' => 5,
            'compress' => true,
        ];
        
        // Créer le répertoire parent si nécessaire
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ((self::LEVELS[$level] ?? 0) < $this->minLevel) {
            return;
        }

        // Vérifier et effectuer la rotation si nécessaire
        $this->rotateIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        // S'assurer que le répertoire existe avant d'écrire
        $logDir = dirname($this->logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        // Écrire dans le fichier de log
        @error_log($logMessage, 3, $this->logPath);
    }

    /**
     * Effectue la rotation du fichier de log si nécessaire
     */
    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logPath)) {
            return;
        }

        $fileSize = filesize($this->logPath);
        if ($fileSize < $this->rotationConfig['maxSize']) {
            return;
        }

        $this->rotate();
    }

    /**
     * Effectue la rotation du fichier de log
     */
    private function rotate(): void
    {
        $logDir = dirname($this->logPath);
        $logBaseName = basename($this->logPath, '.log');
        $extension = $this->rotationConfig['compress'] ? '.log.gz' : '.log';

        // Déplacer les fichiers existants
        for ($i = $this->rotationConfig['maxFiles'] - 1; $i >= 1; $i--) {
            $oldFile = $logDir . '/' . $logBaseName . '.' . $i . $extension;
            $newFile = $logDir . '/' . $logBaseName . '.' . ($i + 1) . $extension;

            if (file_exists($oldFile)) {
                @rename($oldFile, $newFile);
            }
        }

        // Archiver le fichier actuel
        $archiveFile = $logDir . '/' . $logBaseName . '.1' . $extension;
        
        if ($this->rotationConfig['compress']) {
            // Compresser le fichier actuel
            $content = file_get_contents($this->logPath);
            if ($content !== false) {
                $compressed = gzencode($content, 6);
                if ($compressed !== false) {
                    @file_put_contents($archiveFile, $compressed);
                }
            }
        } else {
            // Déplacer simplement
            @rename($this->logPath, $archiveFile);
        }

        // Vider le fichier de log actuel
        @file_put_contents($this->logPath, '');

        // Supprimer les fichiers au-delà de maxFiles
        $this->cleanupOldFiles($logDir, $logBaseName, $extension);
    }

    /**
     * Supprime les fichiers de log plus anciens que maxFiles
     */
    private function cleanupOldFiles(string $logDir, string $logBaseName, string $extension): void
    {
        for ($i = $this->rotationConfig['maxFiles'] + 1; $i <= 100; $i++) {
            $file = $logDir . '/' . $logBaseName . '.' . $i . $extension;
            if (file_exists($file)) {
                @unlink($file);
            } else {
                break; // Pas besoin de continuer si le fichier n'existe pas
            }
        }
    }

    /**
     * Force la rotation manuelle du fichier de log
     */
    public function rotateNow(): void
    {
        if (file_exists($this->logPath)) {
            $this->rotate();
        }
    }

    /**
     * Retourne la configuration de rotation
     */
    public function getRotationConfig(): array
    {
        return $this->rotationConfig;
    }

    /**
     * Définit la configuration de rotation
     */
    public function setRotationConfig(array $config): void
    {
        $this->rotationConfig = array_merge($this->rotationConfig ?? [], $config);
    }
}

