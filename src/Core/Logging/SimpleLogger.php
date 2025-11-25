<?php

namespace JulienLinard\Core\Logging;

/**
 * Logger simple utilisant error_log
 */
class SimpleLogger implements LoggerInterface
{
    private string $logPath;
    private int $minLevel;

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

    public function __construct(?string $logPath = null, string $minLevel = 'debug')
    {
        $this->logPath = $logPath ?? sys_get_temp_dir() . '/app.log';
        $this->minLevel = self::LEVELS[$minLevel] ?? 0;
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

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;

        error_log($logMessage, 3, $this->logPath);
    }
}

