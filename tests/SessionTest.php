<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Session\Session;

class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        // Nettoyer la session avant chaque test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Nettoyer la session après chaque test
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public function testSetAndGet()
    {
        Session::set('test_key', 'test_value');
        
        $this->assertEquals('test_value', Session::get('test_key'));
    }

    public function testGetWithDefault()
    {
        $this->assertNull(Session::get('non_existent'));
        $this->assertEquals('default', Session::get('non_existent', 'default'));
    }

    public function testHas()
    {
        $this->assertFalse(Session::has('test_key'));
        
        Session::set('test_key', 'value');
        
        $this->assertTrue(Session::has('test_key'));
    }

    public function testRemove()
    {
        Session::set('test_key', 'value');
        $this->assertTrue(Session::has('test_key'));
        
        Session::remove('test_key');
        
        $this->assertFalse(Session::has('test_key'));
    }

    public function testFlash()
    {
        Session::flash('success', 'Operation successful');
        
        $this->assertTrue(Session::hasFlash('success'));
        $this->assertEquals('Operation successful', Session::getFlash('success'));
        
        // Le flash doit être supprimé après lecture
        $this->assertFalse(Session::hasFlash('success'));
    }

    public function testGetFlashWithDefault()
    {
        $this->assertNull(Session::getFlash('non_existent'));
        $this->assertEquals('default', Session::getFlash('non_existent', 'default'));
    }

    public function testRegenerate()
    {
        $oldId = session_id();
        
        Session::regenerate();
        
        $newId = session_id();
        $this->assertNotEquals($oldId, $newId);
    }

    public function testFlush()
    {
        Session::set('key1', 'value1');
        Session::set('key2', 'value2');
        
        $this->assertTrue(Session::has('key1'));
        $this->assertTrue(Session::has('key2'));
        
        Session::flush();
        
        $this->assertFalse(Session::has('key1'));
        $this->assertFalse(Session::has('key2'));
    }

    public function testDestroy()
    {
        Session::set('test_key', 'value');
        
        Session::destroy();
        
        $this->assertFalse(Session::has('test_key'));
    }

    public function testAll()
    {
        Session::set('key1', 'value1');
        Session::set('key2', 'value2');
        
        $all = Session::all();
        
        $this->assertIsArray($all);
        $this->assertEquals('value1', $all['key1']);
        $this->assertEquals('value2', $all['key2']);
    }

    public function testConstants()
    {
        $this->assertEquals('user', Session::USER);
        $this->assertEquals('form_result', Session::FORM_RESULT);
        $this->assertEquals('form_success', Session::FORM_SUCCESS);
        $this->assertEquals('flash_message', Session::FLASH_MESSAGE);
        $this->assertEquals('flash_error', Session::FLASH_ERROR);
    }
}
