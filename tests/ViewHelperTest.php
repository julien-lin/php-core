<?php

declare(strict_types=1);

namespace JulienLinard\Core\Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Core\View\ViewHelper;

class ViewHelperTest extends TestCase
{
    public function testEscape()
    {
        $html = '<script>alert("xss")</script>';
        $escaped = ViewHelper::escape($html);
        
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;', $escaped);
    }

    public function testE()
    {
        $html = '<div>test</div>';
        $escaped = ViewHelper::e($html);
        
        $this->assertStringNotContainsString('<div>', $escaped);
        $this->assertStringContainsString('&lt;div&gt;', $escaped);
    }

    public function testDate()
    {
        $date = new \DateTime('2024-01-15 14:30:00');
        $formatted = ViewHelper::date($date, 'Y-m-d');
        
        $this->assertEquals('2024-01-15', $formatted);
    }

    public function testDateWithString()
    {
        $formatted = ViewHelper::date('2024-01-15 14:30:00', 'Y-m-d');
        
        $this->assertEquals('2024-01-15', $formatted);
    }

    public function testDateWithTimestamp()
    {
        $timestamp = strtotime('2024-01-15 14:30:00');
        $formatted = ViewHelper::date($timestamp, 'Y-m-d');
        
        $this->assertEquals('2024-01-15', $formatted);
    }

    public function testNumber()
    {
        $formatted = ViewHelper::number(1234.56, 2);
        
        // Format français : virgule pour décimales, espace pour milliers
        $this->assertEquals('1 234,56', $formatted);
    }

    public function testPrice()
    {
        $formatted = ViewHelper::price(99.99);
        
        // Format français : virgule pour décimales
        $this->assertStringContainsString('99,99', $formatted);
        $this->assertStringContainsString('€', $formatted);
    }

    public function testPriceWithCustomCurrency()
    {
        $formatted = ViewHelper::price(99.99, '$');
        
        // Format français : virgule pour décimales
        $this->assertStringContainsString('99,99', $formatted);
        $this->assertStringContainsString('$', $formatted);
    }

    public function testTruncate()
    {
        $longText = str_repeat('a', 200);
        $truncated = ViewHelper::truncate($longText, 100);
        
        $this->assertLessThanOrEqual(103, mb_strlen($truncated)); // 100 + "..."
        $this->assertStringEndsWith('...', $truncated);
    }

    public function testTruncateShortText()
    {
        $shortText = 'Short text';
        $truncated = ViewHelper::truncate($shortText, 100);
        
        $this->assertEquals($shortText, $truncated);
    }

    public function testCsrfToken()
    {
        $token = ViewHelper::csrfToken();
        
        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testCsrfField()
    {
        $field = ViewHelper::csrfField();
        
        $this->assertStringContainsString('<input', $field);
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="_token"', $field);
    }
}
