<?php

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Application;

class ApplicationTest extends TestCase
{
    private string $testPath;

    protected function setUp(): void
    {
        $this->testPath = sys_get_temp_dir() . '/core-php-test';
        if (!is_dir($this->testPath)) {
            mkdir($this->testPath, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Nettoyer après les tests
        Application::getInstance()?->getContainer()->flush();
    }

    public function testCreateApplication(): void
    {
        $app = Application::create($this->testPath);
        
        $this->assertInstanceOf(Application::class, $app);
        $this->assertEquals($this->testPath, $app->getBasePath());
    }

    public function testApplicationSingleton(): void
    {
        $app1 = Application::create($this->testPath);
        $app2 = Application::getInstance();
        
        $this->assertSame($app1, $app2);
    }

    public function testGetRouter(): void
    {
        $app = Application::create($this->testPath);
        $router = $app->getRouter();
        
        $this->assertInstanceOf(\JulienLinard\Router\Router::class, $router);
    }

    public function testGetContainer(): void
    {
        $app = Application::create($this->testPath);
        $container = $app->getContainer();
        
        $this->assertInstanceOf(\JulienLinard\Core\Container\Container::class, $container);
    }

    public function testSetViewsPath(): void
    {
        $app = Application::create($this->testPath);
        $customPath = $this->testPath . '/custom-views';
        
        $app->setViewsPath($customPath);
        
        $this->assertEquals($customPath, $app->getViewsPath());
    }
}

