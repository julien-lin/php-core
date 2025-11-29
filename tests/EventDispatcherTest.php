<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Events\EventDispatcher;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testListenAndDispatch()
    {
        $called = false;
        
        $this->dispatcher->listen('test.event', function(array $data) use (&$called) {
            $called = true;
            $this->assertEquals('test-data', $data['value']);
        });
        
        $this->dispatcher->dispatch('test.event', ['value' => 'test-data']);
        
        $this->assertTrue($called);
    }

    public function testMultipleListeners()
    {
        $callCount = 0;
        
        $this->dispatcher->listen('test.event', function() use (&$callCount) {
            $callCount++;
        });
        
        $this->dispatcher->listen('test.event', function() use (&$callCount) {
            $callCount++;
        });
        
        $this->dispatcher->dispatch('test.event');
        
        $this->assertEquals(2, $callCount);
    }

    public function testDispatchNonExistentEvent()
    {
        // Ne doit pas lever d'exception
        $this->dispatcher->dispatch('non.existent');
        
        $this->assertTrue(true); // Test passe si pas d'exception
    }

    public function testForget()
    {
        $called = false;
        
        $this->dispatcher->listen('test.event', function() use (&$called) {
            $called = true;
        });
        
        $this->dispatcher->forget('test.event');
        $this->dispatcher->dispatch('test.event');
        
        $this->assertFalse($called);
    }

    public function testFlush()
    {
        $this->dispatcher->listen('event1', fn() => null);
        $this->dispatcher->listen('event2', fn() => null);
        
        $this->assertTrue($this->dispatcher->hasListeners('event1'));
        $this->assertTrue($this->dispatcher->hasListeners('event2'));
        
        $this->dispatcher->flush();
        
        $this->assertFalse($this->dispatcher->hasListeners('event1'));
        $this->assertFalse($this->dispatcher->hasListeners('event2'));
    }

    public function testHasListeners()
    {
        $this->assertFalse($this->dispatcher->hasListeners('test.event'));
        
        $this->dispatcher->listen('test.event', fn() => null);
        
        $this->assertTrue($this->dispatcher->hasListeners('test.event'));
    }

    public function testGetEvents()
    {
        $this->dispatcher->listen('event1', fn() => null);
        $this->dispatcher->listen('event2', fn() => null);
        
        $events = $this->dispatcher->getEvents();
        
        $this->assertIsArray($events);
        $this->assertContains('event1', $events);
        $this->assertContains('event2', $events);
    }
}
