<?php
declare(strict_types=1);

namespace AgVote\Service;

/**
 * MailerService - SMTP socket (STARTTLS/SSL) without external dependencies.
 *
 * Support:
 * - tls: 'starttls' (587), 'ssl' (465), 'none'
 * - auth: LOGIN (user/pass) if user is defined
 */
final class MailerService
{
    private array $smtp;
    private string $appUrl;

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
        $tls  = (string)($this->smtp['tls'] ?? 'starttls'); // starttls|ssl|none
        $user = (string)($this->smtp['user'] ?? '');
        $pass = (string)($this->smtp['pass'] ?? '');
        $fromEmail = (string)($this->smtp['from_email'] ?? 'no-reply@example.test');
        $fromName  = (string)($this->smtp['from_name'] ?? 'Gestion Votes');
        $timeout   = (int)($this->smtp['timeout'] ?? 10);

        if ($host === '' || $port <= 0) {
            return ['ok' => false, 'error' => 'smtp_not_configured'];
        }

        $toEmail = $this->sanitizeEmail($toEmail);
        if ($toEmail === '') return ['ok' => false, 'error' => 'invalid_to_email'];

        $fromEmail = $this->sanitizeEmail($fromEmail);
        if ($fromEmail === '') return ['ok' => false, 'error' => 'invalid_from_email'];

        $socketHost = $host;
        if ($tls === 'ssl') {
            $socketHost = 'ssl://' . $host;
        }

        $fp = @stream_socket_client(
            $socketHost . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$fp) {
            return ['ok' => false, 'error' => 'smtp_connect_failed: ' . $errstr];
        }

        stream_set_timeout($fp, $timeout);

        $debug = [];

        $greet = $this->readResponse($fp);
        $debug['greet'] = $greet;
        if (!$this->isOk($greet)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'smtp_bad_greet', 'debug' => $debug];
        }

        $ehloHost = $this->clientName();
        $ehlo = $this->cmd($fp, "EHLO {$ehloHost}");
        $debug['ehlo1'] = $ehlo;

        if ($tls === 'starttls') {
            $start = $this->cmd($fp, "STARTTLS");
            $debug['starttls'] = $start;
            if (!$this->isOk($start)) {
                fclose($fp);
                return ['ok' => false, 'error' => 'smtp_starttls_failed', 'debug' => $debug];
            }

            $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $debug['crypto'] = $cryptoOk ? 'ok' : 'fail';
            if (!$cryptoOk) {
                fclose($fp);
                return ['ok' => false, 'error' => 'smtp_tls_crypto_failed', 'debug' => $debug];
            }

            $ehlo2 = $this->cmd($fp, "EHLO {$ehloHost}");
            $debug['ehlo2'] = $ehlo2;
            if (!$this->isOk($ehlo2)) {
                fclose($fp);
                return ['ok' => false, 'error' => 'smtp_ehlo_after_tls_failed', 'debug' => $debug];
            }
        }

        // AUTH (if user is defined)
        if ($user !== '') {
            $auth = $this->cmd($fp, "AUTH LOGIN");
            $debug['auth_login'] = $auth;
            if (!$this->isAuthContinue($auth)) {
                fclose($fp);
                return ['ok' => false, 'error' => 'smtp_auth_not_accepted', 'debug' => $debug];
            }

            $u = $this->cmd($fp, base64_encode($user));
            $debug['auth_user'] = $u;
            if (!$this->isAuthContinue($u)) {
                fclose($fp);
                return ['ok' => false, 'error' => 'smtp_auth_user_rejected', 'debug' => $debug];
            }

            $p = $this->cmd($fp, base64_encode($pass));
            $debug['auth_pass'] = $p;
            if (!$this->isOk($p)) {
                fclose($fp);
                return ['ok' => false, 'error' => 'smtp_auth_failed', 'debug' => $debug];
            }
        }

        $mailFrom = $this->cmd($fp, "MAIL FROM:<{$fromEmail}>");
        $debug['mail_from'] = $mailFrom;
        if (!$this->isOk($mailFrom)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'smtp_mail_from_failed', 'debug' => $debug];
        }

        $rcpt = $this->cmd($fp, "RCPT TO:<{$toEmail}>");
        $debug['rcpt_to'] = $rcpt;
        if (!$this->isOk($rcpt)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'smtp_rcpt_to_failed', 'debug' => $debug];
        }

        $data = $this->cmd($fp, "DATA");
        $debug['data'] = $data;
        if (!$this->isDataContinue($data)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'smtp_data_not_accepted', 'debug' => $debug];
        }

        $raw = $this->buildMessage($fromName, $fromEmail, $toEmail, $subject, $html, $text);
        $this->writeData($fp, $raw);

        $end = $this->readResponse($fp);
        $debug['data_end'] = $end;
        if (!$this->isOk($end)) {
            fclose($fp);
            return ['ok' => false, 'error' => 'smtp_data_send_failed', 'debug' => $debug];
        }

        $quit = $this->cmd($fp, "QUIT");
        $debug['quit'] = $quit;
        fclose($fp);

        return ['ok' => true, 'error' => null];
    }

    private function buildMessage(string $fromName, string $fromEmail, string $toEmail, string $subject, string $html, ?string $text): string
    {
        $subject = $this->sanitizeHeader($subject);

        $date = date('r');
        $msgId = sprintf('<%s.%s@%s>', time(), bin2hex(random_bytes(8)), $this->clientName());

        $from = $this->encodeAddress($fromName, $fromEmail);
        $to   = $this->encodeAddress('', $toEmail);

        $headers = [];
        $headers[] = "Date: {$date}";
        $headers[] = "Message-ID: {$msgId}";
        $headers[] = "From: {$from}";
        $headers[] = "To: {$to}";
        $headers[] = "Subject: {$this->encodeHeader($subject)}";
        $headers[] = "MIME-Version: 1.0";

        $body = "";

        if ($text === null) {
            $text = $this->htmlToText($html);
        }

        $boundary = "b1_" . bin2hex(random_bytes(12));
        $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= $this->qp($text) . "\r\n";

        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= $this->qp($html) . "\r\n";
        $body .= "--{$boundary}--\r\n";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function writeData($fp, string $message): void
    {
        // Dot-stuffing: any line starting with "." must be prefixed
        $message = preg_replace('/\r\n\./', "\r\n..", $message);
        fwrite($fp, $message);
        fwrite($fp, "\r\n.\r\n");
    }

    private function cmd($fp, string $line): string
    {
        fwrite($fp, $line . "\r\n");
        return $this->readResponse($fp);
    }

    private function readResponse($fp): string
    {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) break;
            $data .= $line;
            // multi-line response ends when 4th char is space
            if (preg_match('/^\d{3} /', $line)) break;
        }
        return trim($data);
    }

    private function isOk(string $resp): bool
    {
        return preg_match('/^(2\d\d)\b/', $resp) === 1;
    }

    private function isAuthContinue(string $resp): bool
    {
        return preg_match('/^(3\d\d)\b/', $resp) === 1;
    }

    private function isDataContinue(string $resp): bool
    {
        return preg_match('/^354\b/', $resp) === 1;
    }

    private function clientName(): string
    {
        $h = gethostname();
        if (!$h) $h = 'localhost';
        $h = preg_replace('/[^A-Za-z0-9\.-]/', '', $h);
        return $h ?: 'localhost';
    }

    private function sanitizeEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') return '';
        // simple filter; strict enough for SMTP RCPT
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
        return $email;
    }

    private function sanitizeHeader(string $s): string
    {
        $s = str_replace(["\r", "\n"], ' ', $s);
        return trim($s);
    }

    private function encodeHeader(string $s): string
    {
        // encode as RFC2047 if non-ascii
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    }

    private function encodeAddress(string $name, string $email): string
    {
        $email = $this->sanitizeEmail($email);
        if ($name === '' || $email === '') return $email;
        $name = $this->sanitizeHeader($name);
        return $this->encodeHeader($name) . " <{$email}>";
    }

    private function htmlToText(string $html): string
    {
        $t = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/\n{3,}/", "\n\n", $t);
        return trim($t);
    }

    private function qp(string $s): string
    {
        // quoted-printable wrapper
        return quoted_printable_encode($s);
    }
}
