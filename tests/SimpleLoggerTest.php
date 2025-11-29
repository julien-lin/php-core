<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Logging\SimpleLogger;

class SimpleLoggerTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/test-app.log';
        
        // Supprimer le fichier de log s'il existe
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    protected function tearDown(): void
    {
        // Nettoyer
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testEmergency()
    {
        $logger = new SimpleLogger($this->logPath);
        $logger->emergency('Emergency message', ['context' => 'test']);
        
        $this->assertFileExists($this->logPath);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('emergency', $content);
        $this->assertStringContainsString('Emergency message', $content);
    }

    public function testError()
    {
        $logger = new SimpleLogger($this->logPath);
        $logger->error('Error message', ['context' => 'test']);
        
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('error', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    public function testWarning()
    {
        $logger = new SimpleLogger($this->logPath);
        $logger->warning('Warning message');
        
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('warning', $content);
        $this->assertStringContainsString('Warning message', $content);
    }

    public function testInfo()
    {
        $logger = new SimpleLogger($this->logPath);
        $logger->info('Info message');
        
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('info', $content);
        $this->assertStringContainsString('Info message', $content);
    }

    public function testDebug()
    {
        $logger = new SimpleLogger($this->logPath);
        $logger->debug('Debug message');
        
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('debug', $content);
        $this->assertStringContainsString('Debug message', $content);
    }

    public function testMinLevel()
    {
        $logger = new SimpleLogger($this->logPath, 'error');
        
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        
        $content = file_get_contents($this->logPath);
        
        $this->assertStringNotContainsString('Debug message', $content);
        $this->assertStringNotContainsString('Info message', $content);
        $this->assertStringNotContainsString('Warning message', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    public function testLogWithContext()
    {
        $logger = new SimpleLogger($this->logPath);
        $logger->error('Error message', ['user_id' => 123, 'action' => 'test']);
        
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('user_id', $content);
        $this->assertStringContainsString('123', $content);
    }
}
