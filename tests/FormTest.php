<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\Form\Validator;
use JulienLinard\Core\Form\FormResult;
use JulienLinard\Core\Form\FormError;
use JulienLinard\Core\Form\FormSuccess;

class FormTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testRequired()
    {
        $this->assertTrue($this->validator->required('value'));
        $this->assertFalse($this->validator->required(''));
        $this->assertFalse($this->validator->required(null));
    }

    public function testEmail()
    {
        $this->assertTrue($this->validator->email('test@example.com'));
        $this->assertFalse($this->validator->email('invalid-email'));
    }

    public function testMin()
    {
        $this->assertTrue($this->validator->min('hello', 5));
        $this->assertFalse($this->validator->min('hi', 5));
    }

    public function testMax()
    {
        $this->assertTrue($this->validator->max('hello', 10));
        $this->assertFalse($this->validator->max('this is too long', 10));
    }

    public function testLength()
    {
        $this->assertTrue($this->validator->length('hello', 3, 10));
        $this->assertFalse($this->validator->length('hi', 3, 10));
        $this->assertFalse($this->validator->length('this is too long', 3, 10));
    }

    public function testNumeric()
    {
        $this->assertTrue($this->validator->numeric('123'));
        $this->assertTrue($this->validator->numeric('123.45'));
        $this->assertFalse($this->validator->numeric('abc'));
    }

    public function testInteger()
    {
        $this->assertTrue($this->validator->integer('123'));
        $this->assertFalse($this->validator->integer('123.45'));
        $this->assertFalse($this->validator->integer('abc'));
    }

    public function testFloat()
    {
        $this->assertTrue($this->validator->float('123.45'));
        $this->assertTrue($this->validator->float('123'));
        $this->assertFalse($this->validator->float('abc'));
    }

    public function testPattern()
    {
        $this->assertTrue($this->validator->pattern('ABC123', '/^[A-Z0-9]+$/'));
        $this->assertFalse($this->validator->pattern('abc123', '/^[A-Z0-9]+$/'));
    }

    public function testIn()
    {
        $allowed = ['red', 'green', 'blue'];
        $this->assertTrue($this->validator->in('red', $allowed));
        $this->assertFalse($this->validator->in('yellow', $allowed));
    }

    public function testUrl()
    {
        $this->assertTrue($this->validator->url('https://example.com'));
        $this->assertTrue($this->validator->url('http://example.com'));
        $this->assertFalse($this->validator->url('not-a-url'));
    }

    public function testValidateWithRules()
    {
        $data = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];
        
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8'
        ];
        
        $result = $this->validator->validate($data, $rules);
        
        $this->assertInstanceOf(FormResult::class, $result);
        $this->assertFalse($result->hasErrors());
    }

    public function testValidateWithErrors()
    {
        $data = [
            'email' => 'invalid-email',
            'password' => 'short'
        ];
        
        $rules = [
            'email' => 'required|email',
            'password' => 'required|min:8'
        ];
        
        $result = $this->validator->validate($data, $rules);
        
        $this->assertTrue($result->hasErrors());
        $this->assertGreaterThan(0, count($result->getErrors()));
    }

    public function testFormResultAddError()
    {
        $result = new FormResult();
        $error = new FormError('Test error', 'field');
        
        $result->addError($error);
        
        $this->assertTrue($result->hasErrors());
        $this->assertEquals(1, count($result->getErrors()));
    }

    public function testFormResultAddSuccess()
    {
        $result = new FormResult();
        $success = new FormSuccess('Operation successful');
        
        $result->addSuccess($success);
        
        $this->assertTrue($result->hasSuccess());
        $this->assertNotNull($result->getSuccess());
    }

    public function testFormResultGetErrorsForField()
    {
        $result = new FormResult();
        $result->addError(new FormError('Error 1', 'email'));
        $result->addError(new FormError('Error 2', 'email'));
        $result->addError(new FormError('Error 3', 'password'));
        
        $emailErrors = $result->getErrorsForField('email');
        
        $this->assertCount(2, $emailErrors);
    }

    public function testFormResultClear()
    {
        $result = new FormResult();
        $result->addError(new FormError('Error'));
        $result->addSuccess(new FormSuccess('Success'));
        
        $result->clear();
        
        $this->assertFalse($result->hasErrors());
        $this->assertFalse($result->hasSuccess());
    }
}
