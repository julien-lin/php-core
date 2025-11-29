<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Model\Model;

class ModelTest extends TestCase
{
    public function testFill()
    {
        $user = new TestUser();
        $user->fill([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $this->assertEquals(1, $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testConstructorWithData()
    {
        $user = new TestUser([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $this->assertEquals(1, $user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testToArray()
    {
        $user = new TestUser([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $array = $user->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
    }

    public function testToJson()
    {
        $user = new TestUser([
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        $json = $user->toJson();
        
        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals(1, $decoded['id']);
        $this->assertEquals('John Doe', $decoded['name']);
    }

    public function testExists()
    {
        $user = new TestUser();
        $this->assertFalse($user->exists());
        
        $user->id = 1;
        $this->assertTrue($user->exists());
    }

    public function testToString()
    {
        $user = new TestUser(['id' => 123]);
        
        $this->assertStringContainsString('TestUser', (string)$user);
        $this->assertStringContainsString('123', (string)$user);
    }

    public function testToStringWithNew()
    {
        $user = new TestUser();
        
        $this->assertStringContainsString('TestUser', (string)$user);
        $this->assertStringContainsString('new', (string)$user);
    }
}

// Classe de test
class TestUser extends Model
{
    public ?int $id = null;
    public string $name = '';
    public string $email = '';
}
