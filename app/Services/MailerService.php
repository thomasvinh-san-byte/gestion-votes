<?php
declare(strict_types=1);

namespace AgVote\Service;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;

/**
 * MailerService - Email sending via symfony/mailer.
 *
 * Replaces the hand-rolled SMTP socket implementation with a battle-tested
 * library that handles STARTTLS, AUTH, MIME, connection pooling, etc.
 *
 * The public API (constructor signature + send() return format) is preserved
 * for backward compatibility with EmailQueueService and controllers.
 */
final class MailerService
{
    private array $smtp;
    private string $appUrl;
    private ?Mailer $mailer = null;

    public function __construct(array $config)
    {
        $this->smtp = $config['smtp'] ?? [];
        $this->appUrl = (string)(($config['app']['url'] ?? '') ?: 'http://localhost:8080');
    }

    public function isConfigured(): bool
    {
        return !empty($this->smtp['host']) && !empty($this->smtp['port']);
    }

    /**
     * @return array{ok:bool,error:?string,debug?:array<string,mixed>}
     */
    public function send(string $toEmail, string $subject, string $html, ?string $text = null): array
    {
        $host = (string)($this->smtp['host'] ?? '');
        $port = (int)($this->smtp['port'] ?? 0);

        if ($host === '' || $port <= 0) {
            return ['ok' => false, 'error' => 'smtp_not_configured'];
        }

        $toEmail = self::sanitizeEmail($toEmail);
        if ($toEmail === '') {
            return ['ok' => false, 'error' => 'invalid_to_email'];
        }

        $fromEmail = self::sanitizeEmail((string)($this->smtp['from_email'] ?? 'no-reply@example.test'));
        if ($fromEmail === '') {
            return ['ok' => false, 'error' => 'invalid_from_email'];
        }

        $fromName = self::sanitizeHeader((string)($this->smtp['from_name'] ?? 'Gestion Votes'));

        try {
            $mailer = $this->getMailer();

            $email = (new Email())
                ->from(new Address($fromEmail, $fromName))
                ->to($toEmail)
                ->subject(self::sanitizeHeader($subject))
                ->html($html);

            if ($text !== null) {
                $email->text($text);
            } else {
                $email->text(self::htmlToText($html));
            }

            $mailer->send($email);

            return ['ok' => true, 'error' => null];
        } catch (TransportExceptionInterface $e) {
            return ['ok' => false, 'error' => 'smtp_send_failed: ' . $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'mail_error: ' . $e->getMessage()];
        }
    }

    /**
     * Builds the Symfony Mailer transport DSN from SMTP config and returns a Mailer instance.
     * Lazy-initialized and cached for the lifetime of this service instance.
     */
    private function getMailer(): Mailer
    {
        if ($this->mailer !== null) {
            return $this->mailer;
        }

        $host = (string)($this->smtp['host'] ?? '');
        $port = (int)($this->smtp['port'] ?? 587);
        $tls  = (string)($this->smtp['tls'] ?? 'starttls');
        $user = (string)($this->smtp['user'] ?? '');
        $pass = (string)($this->smtp['pass'] ?? '');

        // Build DSN: smtp://user:pass@host:port
        $scheme = match ($tls) {
            'ssl'      => 'smtps',   // implicit TLS (port 465)
            'starttls' => 'smtp',    // STARTTLS (port 587) — Symfony auto-negotiates STARTTLS
            default    => 'smtp',
        };

        $dsn = $scheme . '://';

        if ($user !== '') {
            $dsn .= rawurlencode($user);
            if ($pass !== '') {
                $dsn .= ':' . rawurlencode($pass);
            }
            $dsn .= '@';
        }

        $dsn .= $host . ':' . $port;

        // Disable TLS verification only for 'none' mode (dev/testing)
        if ($tls === 'none') {
            $dsn .= '?verify_peer=0';
        }

        $transport = Transport::fromDsn($dsn);
        $this->mailer = new Mailer($transport);

        return $this->mailer;
    }

    // ── Helpers preserved for backward compatibility & tests ──────────────

    private static function sanitizeEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        return $email;
    }

    private static function sanitizeHeader(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s);
        return trim($s);
    }

    private static function encodeHeader(string $s): string
    {
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }

    private static function htmlToText(string $html): string
    {
        $t = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/\n{3,}/", "\n\n", $t);
        return trim($t);
    }
}
