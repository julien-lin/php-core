<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Container\Container;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testSimpleBinding()
    {
        $this->container->bind('test', fn() => 'test-value');
        
        $result = $this->container->make('test');
        
        $this->assertEquals('test-value', $result);
    }

    public function testSingletonBinding()
    {
        $this->container->singleton('singleton', fn() => new \stdClass());
        
        $instance1 = $this->container->make('singleton');
        $instance2 = $this->container->make('singleton');
        
        $this->assertSame($instance1, $instance2);
    }

    public function testNonSingletonBinding()
    {
        $this->container->bind('non-singleton', fn() => new \stdClass(), false);
        
        $instance1 = $this->container->make('non-singleton');
        $instance2 = $this->container->make('non-singleton');
        
        $this->assertNotSame($instance1, $instance2);
    }

    public function testAutoWiringSimpleClass()
    {
        $instance = $this->container->make(TestService::class);
        
        $this->assertInstanceOf(TestService::class, $instance);
    }

    public function testAutoWiringWithDependencies()
    {
        $instance = $this->container->make(TestServiceWithDependency::class);
        
        $this->assertInstanceOf(TestServiceWithDependency::class, $instance);
        $this->assertInstanceOf(TestService::class, $instance->dependency);
    }

    public function testAutoWiringWithNestedDependencies()
    {
        $instance = $this->container->make(TestServiceWithNestedDependency::class);
        
        $this->assertInstanceOf(TestServiceWithNestedDependency::class, $instance);
        $this->assertInstanceOf(TestServiceWithDependency::class, $instance->dependency);
        $this->assertInstanceOf(TestService::class, $instance->dependency->dependency);
    }

    public function testAutoWiringWithProvidedParameters()
    {
        $customValue = new \stdClass();
        $instance = $this->container->make(TestServiceWithParameter::class, ['value' => $customValue]);
        
        $this->assertInstanceOf(TestServiceWithParameter::class, $instance);
        $this->assertSame($customValue, $instance->value);
    }

    public function testAutoWiringWithDefaultValue()
    {
        $instance = $this->container->make(TestServiceWithDefault::class);
        
        $this->assertInstanceOf(TestServiceWithDefault::class, $instance);
        $this->assertEquals('default', $instance->value);
    }

    public function testHasBinding()
    {
        $this->assertFalse($this->container->has('test'));
        
        $this->container->bind('test', fn() => 'value');
        
        $this->assertTrue($this->container->has('test'));
    }

    public function testForgetBinding()
    {
        $this->container->bind('test', fn() => 'value');
        $this->assertTrue($this->container->has('test'));
        
        $this->container->forget('test');
        
        $this->assertFalse($this->container->has('test'));
    }

    public function testFlush()
    {
        $this->container->bind('test1', fn() => 'value1');
        $this->container->singleton('test2', fn() => new \stdClass());
        
        $this->assertTrue($this->container->has('test1'));
        $this->assertTrue($this->container->has('test2'));
        
        $this->container->flush();
        
        $this->assertFalse($this->container->has('test1'));
        $this->assertFalse($this->container->has('test2'));
    }

    public function testMakeNonExistentClass()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("La classe NonExistentClass n'existe pas.");
        
        $this->container->make('NonExistentClass');
    }

    public function testMakeNonInstantiableClass()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("La classe AbstractTestClass n'est pas instanciable.");
        
        $this->container->make(AbstractTestClass::class);
    }

    public function testResolveUnresolvableDependency()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Impossible de résoudre");
        
        $this->container->make(TestServiceWithUnresolvable::class);
    }
}

// Classes de test
class TestService
{
}

class TestServiceWithDependency
{
    public function __construct(
        public TestService $dependency
    ) {
    }
}

class TestServiceWithNestedDependency
{
    public function __construct(
        public TestServiceWithDependency $dependency
    ) {
    }
}

class TestServiceWithParameter
{
    public function __construct(
        public \stdClass $value
    ) {
    }
}

class TestServiceWithDefault
{
    public function __construct(
        public string $value = 'default'
    ) {
    }
}

abstract class AbstractTestClass
{
}

class TestServiceWithUnresolvable
{
    public function __construct(
        public UnresolvableService $service
    ) {
    }
}

class UnresolvableService
{
    public function __construct(
        public string $requiredParam // Pas de valeur par défaut
    ) {
    }
}
