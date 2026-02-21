<?php

declare(strict_types=1);

use AgVote\Service\MailerService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Services/MailerService.php';

/**
 * Unit tests for MailerService (symfony/mailer backend).
 *
 * Tests configuration checks, input validation, and helper methods
 * without requiring a real SMTP server.
 */
class MailerServiceTest extends TestCase
{
    public function testIsConfiguredReturnsFalseWithEmptyConfig(): void
    {
        $mailer = new MailerService([]);
        $this->assertFalse($mailer->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWithPartialConfig(): void
    {
        $mailer = new MailerService(['smtp' => ['host' => 'smtp.example.com']]);
        $this->assertFalse($mailer->isConfigured());

        $mailer2 = new MailerService(['smtp' => ['port' => 587]]);
        $this->assertFalse($mailer2->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWithValidConfig(): void
    {
        $mailer = new MailerService([
            'smtp' => ['host' => 'smtp.example.com', 'port' => 587],
        ]);
        $this->assertTrue($mailer->isConfigured());
    }

    public function testSendReturnsErrorWhenNotConfigured(): void
    {
        $mailer = new MailerService([]);
        $result = $mailer->send('user@example.com', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('smtp_not_configured', $result['error']);
    }

    public function testSendReturnsErrorWithInvalidRecipient(): void
    {
        $mailer = new MailerService([
            'smtp' => ['host' => 'smtp.example.com', 'port' => 587],
        ]);
        $result = $mailer->send('not-an-email', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_to_email', $result['error']);
    }

    public function testSendReturnsErrorWithEmptyRecipient(): void
    {
        $mailer = new MailerService([
            'smtp' => ['host' => 'smtp.example.com', 'port' => 587],
        ]);
        $result = $mailer->send('', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_to_email', $result['error']);
    }

    public function testSanitizeHeaderRemovesNewlines(): void
    {
        $ref = new \ReflectionMethod(MailerService::class, 'sanitizeHeader');
        $ref->setAccessible(true);

        $this->assertSame('Hello  World', $ref->invoke(null, "Hello\r\nWorld"));
        $this->assertSame('Hello World', $ref->invoke(null, "Hello\rWorld"));
        $this->assertSame('Hello World', $ref->invoke(null, "Hello\nWorld"));
        $this->assertSame('Clean text', $ref->invoke(null, '  Clean text  '));
    }

    public function testSanitizeEmailRejectsInvalid(): void
    {
        $ref = new \ReflectionMethod(MailerService::class, 'sanitizeEmail');
        $ref->setAccessible(true);

        $this->assertSame('', $ref->invoke(null, ''));
        $this->assertSame('', $ref->invoke(null, 'not-an-email'));
        $this->assertSame('', $ref->invoke(null, '@no-local'));
        $this->assertSame('user@example.com', $ref->invoke(null, ' user@example.com '));
    }

    public function testEncodeHeaderHandlesUnicode(): void
    {
        $ref = new \ReflectionMethod(MailerService::class, 'encodeHeader');
        $ref->setAccessible(true);

        // ASCII stays unchanged
        $this->assertSame('Hello World', $ref->invoke(null, 'Hello World'));

        // Unicode gets RFC2047 encoded
        $encoded = $ref->invoke(null, 'Séance AG');
        $this->assertStringStartsWith('=?UTF-8?B?', $encoded);
        $this->assertStringEndsWith('?=', $encoded);
    }

    public function testHtmlToTextStripsTagsPreservesNewlines(): void
    {
        $ref = new \ReflectionMethod(MailerService::class, 'htmlToText');
        $ref->setAccessible(true);

        $result = $ref->invoke(null, '<p>Hello</p><br>World<br/>End');
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<br', $result);
    }

    public function testSendFailsGracefullyOnConnectionError(): void
    {
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999, // non-existent port
                'tls' => 'none',
                'from_email' => 'test@example.com',
            ],
        ]);

        $result = $mailer->send('user@example.com', 'Test', '<p>Hello</p>');
        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }
}
