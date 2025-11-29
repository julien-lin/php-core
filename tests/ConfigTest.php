<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Config\Config;

class ConfigTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config();
    }

    public function testSetAndGet()
    {
        $this->config->set('app.name', 'My App');
        
        $this->assertEquals('My App', $this->config->get('app.name'));
    }

    public function testGetWithDefault()
    {
        $this->assertNull($this->config->get('non_existent'));
        $this->assertEquals('default', $this->config->get('non_existent', 'default'));
    }

    public function testHas()
    {
        $this->assertFalse($this->config->has('app.name'));
        
        $this->config->set('app.name', 'My App');
        
        $this->assertTrue($this->config->has('app.name'));
    }

    public function testNestedKeys()
    {
        $this->config->set('database.host', 'localhost');
        $this->config->set('database.port', 3306);
        $this->config->set('database.name', 'mydb');
        
        $this->assertEquals('localhost', $this->config->get('database.host'));
        $this->assertEquals(3306, $this->config->get('database.port'));
        $this->assertEquals('mydb', $this->config->get('database.name'));
    }

    public function testLoad()
    {
        $data = [
            'app' => [
                'name' => 'Test App',
                'debug' => true
            ],
            'database' => [
                'host' => 'localhost'
            ]
        ];
        
        $this->config->load($data);
        
        $this->assertEquals('Test App', $this->config->get('app.name'));
        $this->assertTrue($this->config->get('app.debug'));
        $this->assertEquals('localhost', $this->config->get('database.host'));
    }

    public function testAll()
    {
        $this->config->set('app.name', 'My App');
        $this->config->set('app.debug', true);
        
        $all = $this->config->all();
        
        $this->assertIsArray($all);
        $this->assertEquals('My App', $all['app']['name']);
        $this->assertTrue($all['app']['debug']);
    }

    public function testOverwriteValue()
    {
        $this->config->set('app.name', 'Old Name');
        $this->assertEquals('Old Name', $this->config->get('app.name'));
        
        $this->config->set('app.name', 'New Name');
        $this->assertEquals('New Name', $this->config->get('app.name'));
    }
}
