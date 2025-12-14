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
            'id' => 1, // id est protégé par défaut (guarded), donc ne sera pas rempli
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        // id est protégé, donc reste null
        $this->assertNull($user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testConstructorWithData()
    {
        $user = new TestUser([
            'id' => 1, // id est protégé par défaut (guarded), donc ne sera pas rempli
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        
        // id est protégé, donc reste null
        $this->assertNull($user->id);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('john@example.com', $user->email);
    }

    public function testToArray()
    {
        $user = new TestUser();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->id = 1; // On peut assigner directement (pas via fill)
        
        $array = $user->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('John Doe', $array['name']);
        $this->assertEquals('john@example.com', $array['email']);
    }

    public function testToJson()
    {
        $user = new TestUser();
        $user->name = 'John Doe';
        $user->email = 'john@example.com';
        $user->id = 1; // On peut assigner directement (pas via fill)
        
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
        $user = new TestUser();
        $user->id = 123; // On peut assigner directement (pas via fill)
        
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
