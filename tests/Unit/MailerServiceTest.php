<?php

declare(strict_types=1);

use AgVote\Service\MailerService;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Services/MailerService.php';

/**
 * Unit tests for MailerService.
 *
 * Tests the configuration, header sanitization, and message building
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
        // Use reflection to test private method
        $mailer = new MailerService([]);
        $ref = new \ReflectionMethod($mailer, 'sanitizeHeader');
        $ref->setAccessible(true);

        $this->assertSame('Hello World', $ref->invoke($mailer, "Hello\r\nWorld"));
        $this->assertSame('Hello World', $ref->invoke($mailer, "Hello\rWorld"));
        $this->assertSame('Hello World', $ref->invoke($mailer, "Hello\nWorld"));
        $this->assertSame('Clean text', $ref->invoke($mailer, '  Clean text  '));
    }

    public function testSanitizeEmailRejectsInvalid(): void
    {
        $mailer = new MailerService([]);
        $ref = new \ReflectionMethod($mailer, 'sanitizeEmail');
        $ref->setAccessible(true);

        $this->assertSame('', $ref->invoke($mailer, ''));
        $this->assertSame('', $ref->invoke($mailer, 'not-an-email'));
        $this->assertSame('', $ref->invoke($mailer, '@no-local'));
        $this->assertSame('user@example.com', $ref->invoke($mailer, ' user@example.com '));
    }

    public function testBuildMessageContainsRequiredHeaders(): void
    {
        $mailer = new MailerService([]);
        $ref = new \ReflectionMethod($mailer, 'buildMessage');
        $ref->setAccessible(true);

        $msg = $ref->invoke(
            $mailer,
            'Test Sender',
            'sender@example.com',
            'recipient@example.com',
            'Test Subject',
            '<p>Hello</p>',
            null
        );

        // Check required SMTP headers
        $this->assertStringContainsString('From:', $msg);
        $this->assertStringContainsString('To:', $msg);
        $this->assertStringContainsString('Subject:', $msg);
        $this->assertStringContainsString('Date:', $msg);
        $this->assertStringContainsString('Message-ID:', $msg);
        $this->assertStringContainsString('MIME-Version: 1.0', $msg);
        $this->assertStringContainsString('multipart/alternative', $msg);
        $this->assertStringContainsString('text/plain', $msg);
        $this->assertStringContainsString('text/html', $msg);
    }

    public function testBuildMessageContainsHtmlAndTextParts(): void
    {
        $mailer = new MailerService([]);
        $ref = new \ReflectionMethod($mailer, 'buildMessage');
        $ref->setAccessible(true);

        $msg = $ref->invoke(
            $mailer,
            'Sender',
            'sender@example.com',
            'to@example.com',
            'Subject',
            '<p>Hello <b>world</b></p>',
            'Hello world'
        );

        // Both parts present
        $this->assertStringContainsString('Hello world', $msg);
        $this->assertStringContainsString('Hello <b>world</b>', $msg);
    }

    public function testEncodeHeaderHandlesUnicode(): void
    {
        $mailer = new MailerService([]);
        $ref = new \ReflectionMethod($mailer, 'encodeHeader');
        $ref->setAccessible(true);

        // ASCII stays unchanged
        $this->assertSame('Hello World', $ref->invoke($mailer, 'Hello World'));

        // Unicode gets RFC2047 encoded
        $encoded = $ref->invoke($mailer, 'Séance AG');
        $this->assertStringStartsWith('=?UTF-8?B?', $encoded);
        $this->assertStringEndsWith('?=', $encoded);
    }

    public function testHtmlToTextStripsTagsPreservesNewlines(): void
    {
        $mailer = new MailerService([]);
        $ref = new \ReflectionMethod($mailer, 'htmlToText');
        $ref->setAccessible(true);

        $result = $ref->invoke($mailer, '<p>Hello</p><br>World<br/>End');
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<br', $result);
    }
}
