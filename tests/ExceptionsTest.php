<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Exceptions\FrameworkException;
use JulienLinard\Core\Exceptions\NotFoundException;
use JulienLinard\Core\Exceptions\ValidationException;

class ExceptionsTest extends TestCase
{
    public function testFrameworkException()
    {
        $exception = new FrameworkException('Test message');
        
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertEquals('Test message', $exception->getMessage());
    }

    public function testNotFoundException()
    {
        $exception = new NotFoundException('Resource not found');
        
        $this->assertInstanceOf(FrameworkException::class, $exception);
        $this->assertEquals('Resource not found', $exception->getMessage());
    }

    public function testNotFoundExceptionWithCode()
    {
        $exception = new NotFoundException('Resource not found', 404);
        
        $this->assertEquals('Resource not found', $exception->getMessage());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testValidationException()
    {
        $errors = ['email' => 'Invalid email', 'password' => 'Too short'];
        $exception = new ValidationException('Validation failed', $errors);
        
        $this->assertInstanceOf(FrameworkException::class, $exception);
        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals($errors, $exception->getErrors());
    }

    public function testValidationExceptionWithEmptyErrors()
    {
        $exception = new ValidationException('Validation failed');
        
        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertIsArray($exception->getErrors());
        $this->assertEmpty($exception->getErrors());
    }

    public function testValidationExceptionGetErrorsForField()
    {
        $errors = [
            'email' => ['Invalid email', 'Email already exists'],
            'password' => ['Too short']
        ];
        $exception = new ValidationException('Validation failed', $errors);
        
        $emailErrors = $exception->getErrorsForField('email');
        $this->assertCount(2, $emailErrors);
        $this->assertContains('Invalid email', $emailErrors);
        
        $passwordErrors = $exception->getErrorsForField('password');
        $this->assertCount(1, $passwordErrors);
        
        $unknownErrors = $exception->getErrorsForField('unknown');
        $this->assertEmpty($unknownErrors);
    }
}
