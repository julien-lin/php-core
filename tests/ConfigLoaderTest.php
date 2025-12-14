<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Config\Config;
use JulienLinard\Core\Config\ConfigLoader;

class ConfigLoaderTest extends TestCase
{
    private string $testConfigPath;

    protected function setUp(): void
    {
        $this->testConfigPath = sys_get_temp_dir() . '/core-php-config-test';
        if (!is_dir($this->testConfigPath)) {
            mkdir($this->testConfigPath, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Nettoyer le cache du ConfigLoader
        ConfigLoader::clearCache();

        // Nettoyer les fichiers
        if (is_dir($this->testConfigPath)) {
            $this->deleteDirectory($this->testConfigPath);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testLoad()
    {
        // Créer un fichier de config
        file_put_contents(
            $this->testConfigPath . '/app.php',
            '<?php return ["name" => "Test App", "debug" => true];'
        );

        $config = ConfigLoader::load($this->testConfigPath);

        $this->assertIsArray($config);
        $this->assertEquals('Test App', $config['app']['name']);
        $this->assertTrue($config['app']['debug']);
    }

    public function testLoadMultipleFiles()
    {
        file_put_contents(
            $this->testConfigPath . '/app.php',
            '<?php return ["name" => "Test App"];'
        );

        file_put_contents(
            $this->testConfigPath . '/database.php',
            '<?php return ["host" => "localhost", "port" => 3306];'
        );

        $config = ConfigLoader::load($this->testConfigPath);

        $this->assertIsArray($config);
        $this->assertEquals('Test App', $config['app']['name']);
        $this->assertEquals('localhost', $config['database']['host']);
        $this->assertEquals(3306, $config['database']['port']);
    }

    public function testLoadInto()
    {
        file_put_contents(
            $this->testConfigPath . '/app.php',
            '<?php return ["name" => "Test App", "debug" => true];'
        );

        $config = new Config();
        ConfigLoader::loadInto($config, $this->testConfigPath);

        $this->assertEquals('Test App', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
    }

    public function testLoadNonExistentDirectory()
    {
        $config = ConfigLoader::load('/non/existent/path');

        $this->assertIsArray($config);
        $this->assertEmpty($config);
    }
}
