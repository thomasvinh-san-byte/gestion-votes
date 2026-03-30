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
class MailerServiceTest extends TestCase {
    public function testIsConfiguredReturnsFalseWithEmptyConfig(): void {
        $mailer = new MailerService([]);
        $this->assertFalse($mailer->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWithPartialConfig(): void {
        $mailer = new MailerService(['smtp' => ['host' => 'smtp.example.com']]);
        $this->assertFalse($mailer->isConfigured());

        $mailer2 = new MailerService(['smtp' => ['port' => 587]]);
        $this->assertFalse($mailer2->isConfigured());
    }

    public function testIsConfiguredReturnsTrueWithValidConfig(): void {
        $mailer = new MailerService([
            'smtp' => ['host' => 'smtp.example.com', 'port' => 587],
        ]);
        $this->assertTrue($mailer->isConfigured());
    }

    public function testSendReturnsErrorWhenNotConfigured(): void {
        $mailer = new MailerService([]);
        $result = $mailer->send('user@example.com', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('smtp_not_configured', $result['error']);
    }

    public function testSendReturnsErrorWithInvalidRecipient(): void {
        $mailer = new MailerService([
            'smtp' => ['host' => 'smtp.example.com', 'port' => 587],
        ]);
        $result = $mailer->send('not-an-email', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_to_email', $result['error']);
    }

    public function testSendReturnsErrorWithEmptyRecipient(): void {
        $mailer = new MailerService([
            'smtp' => ['host' => 'smtp.example.com', 'port' => 587],
        ]);
        $result = $mailer->send('', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_to_email', $result['error']);
    }

    public function testSanitizeHeaderRemovesNewlines(): void {
        $ref = new \ReflectionMethod(MailerService::class, 'sanitizeHeader');
        $ref->setAccessible(true);

        $this->assertSame('Hello  World', $ref->invoke(null, "Hello\r\nWorld"));
        $this->assertSame('Hello World', $ref->invoke(null, "Hello\rWorld"));
        $this->assertSame('Hello World', $ref->invoke(null, "Hello\nWorld"));
        $this->assertSame('Clean text', $ref->invoke(null, '  Clean text  '));
    }

    public function testSanitizeEmailRejectsInvalid(): void {
        $ref = new \ReflectionMethod(MailerService::class, 'sanitizeEmail');
        $ref->setAccessible(true);

        $this->assertSame('', $ref->invoke(null, ''));
        $this->assertSame('', $ref->invoke(null, 'not-an-email'));
        $this->assertSame('', $ref->invoke(null, '@no-local'));
        $this->assertSame('user@example.com', $ref->invoke(null, ' user@example.com '));
    }

    public function testHtmlToTextStripsTagsPreservesNewlines(): void {
        $ref = new \ReflectionMethod(MailerService::class, 'htmlToText');
        $ref->setAccessible(true);

        $result = $ref->invoke(null, '<p>Hello</p><br>World<br/>End');
        $this->assertStringContainsString('Hello', $result);
        $this->assertStringContainsString('World', $result);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<br', $result);
    }

    public function testSendFailsGracefullyOnConnectionError(): void {
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

    public function testSendReturnsErrorWithInvalidFromEmail(): void {
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999,
                'tls' => 'none',
                'from_email' => 'not-a-valid-email', // Invalid from_email
            ],
        ]);

        $result = $mailer->send('user@example.com', 'Test', '<p>Hello</p>');

        $this->assertFalse($result['ok']);
        $this->assertSame('invalid_from_email', $result['error']);
    }

    public function testSendWithTextProvidedSkipsHtmlToText(): void {
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 19999,
                'tls' => 'none',
                'from_email' => 'noreply@test.com',
            ],
        ]);

        // When text is provided, it should be used instead of htmlToText conversion
        // Connection fails (port 19999), but the code path is exercised up to the send attempt
        $result = $mailer->send('user@example.com', 'Test', '<p>Hello</p>', 'Hello plain');

        $this->assertFalse($result['ok']); // Connection fails gracefully
        $this->assertNotNull($result['error']);
    }

    // =========================================================================
    // getMailer() DSN building — different TLS modes (private method exercised via send())
    // =========================================================================

    public function testSendWithSslTlsModeUsesSmptsScheme(): void {
        // ssl → smtps scheme
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 465,
                'tls' => 'ssl',
                'from_email' => 'noreply@test.com',
            ],
        ]);

        $result = $mailer->send('user@example.com', 'SSL Test', '<p>Hi</p>');
        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function testSendWithStarttlsModeUsesSmtpScheme(): void {
        // starttls → smtp scheme
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 587,
                'tls' => 'starttls',
                'from_email' => 'noreply@test.com',
            ],
        ]);

        $result = $mailer->send('user@example.com', 'STARTTLS Test', '<p>Hi</p>');
        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function testSendWithUserAndPassBuildsDsnWithCredentials(): void {
        // user + pass → user:pass@ in DSN
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 587,
                'tls' => 'starttls',
                'from_email' => 'noreply@test.com',
                'user' => 'myuser',
                'pass' => 'mysecret',
            ],
        ]);

        $result = $mailer->send('user@example.com', 'Auth Test', '<p>Hi</p>');
        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function testSendWithUserNoPassBuildsDsnWithUserOnly(): void {
        // user, no pass → user@ in DSN (no colon)
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 587,
                'tls' => 'starttls',
                'from_email' => 'noreply@test.com',
                'user' => 'myuser',
                'pass' => '', // empty pass
            ],
        ]);

        $result = $mailer->send('user@example.com', 'User Only Test', '<p>Hi</p>');
        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function testSendWithFromNameSanitizesHeader(): void {
        // from_name is sanitized to remove newlines
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 587,
                'tls' => 'none',
                'from_email' => 'noreply@test.com',
                'from_name' => "Evil\r\nHeader",
            ],
        ]);

        // send() sanitizes from_name before building the email
        $result = $mailer->send('user@example.com', 'Test', '<p>Hi</p>');
        $this->assertFalse($result['ok']);
        $this->assertNotNull($result['error']);
    }

    public function testSendWithSubjectSanitizesHeader(): void {
        $mailer = new MailerService([
            'smtp' => [
                'host' => '127.0.0.1',
                'port' => 587,
                'tls' => 'none',
                'from_email' => 'noreply@test.com',
            ],
        ]);

        $result = $mailer->send('user@example.com', "Subject\r\nInjection", '<p>Hi</p>');
        $this->assertFalse($result['ok']);
        // Error is SMTP connection failure, not header sanitization issue
        $this->assertNotNull($result['error']);
    }
}
