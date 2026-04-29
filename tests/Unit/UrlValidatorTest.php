<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Http\UrlValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for UrlValidator — F11 outbound URL gate.
 *
 * Asserts the security boundaries:
 *   - private/loopback/link-local IPs are refused (SSRF / cloud metadata)
 *   - non-https schemes are refused for outbound
 *   - userinfo-style URLs are refused (phishing redirect)
 *   - host whitelist is enforced exactly
 *   - punycode/IDN hosts only pass if explicitly whitelisted
 */
final class UrlValidatorTest extends TestCase {
    private const ALLOWED = ['app.example.com', 'webhook.example.com'];

    public function testAcceptsHttpsUrlWhenHostInWhitelist(): void {
        $this->assertTrue(UrlValidator::isSafeOutbound(
            'https://app.example.com/notify',
            self::ALLOWED,
        ));
    }

    public function testRefusesHttpEvenIfHostInWhitelist(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'http://app.example.com/notify',
            self::ALLOWED,
        ));
    }

    public function testRefusesUrlWithUserinfo(): void {
        // Classic phishing pattern: app.example.com is the "user", real host is evil.com
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://app.example.com@evil.com/phish',
            self::ALLOWED,
        ));
    }

    public function testRefusesHostNotInWhitelist(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://attacker.example.com/notify',
            self::ALLOWED,
        ));
    }

    public function testRefusesEmptyAllowList(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound('https://app.example.com/', []));
    }

    public function testRefusesAwsMetadataIp(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://169.254.169.254/latest/meta-data/iam/',
            ['169.254.169.254'], // even if pin-listed, the IP is link-local → refuse
        ));
    }

    public function testRefusesRfc1918Ip(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://10.0.0.1/internal',
            ['10.0.0.1'],
        ));
    }

    public function testRefusesLoopbackIp(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://127.0.0.1/loopback',
            ['127.0.0.1'],
        ));
    }

    public function testRefusesIpv6Loopback(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://[::1]/loopback',
            ['::1'],
        ));
    }

    public function testRefusesUrlWithoutHost(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound('https:///path', self::ALLOWED));
    }

    public function testRefusesGarbageInput(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound('not a url', self::ALLOWED));
        $this->assertFalse(UrlValidator::isSafeOutbound('', self::ALLOWED));
    }

    public function testHostMatchIsCaseInsensitive(): void {
        $this->assertTrue(UrlValidator::isSafeOutbound(
            'https://APP.example.com/notify',
            self::ALLOWED,
        ));
    }

    public function testIdnHostRefusedUnlessExplicitlyWhitelisted(): void {
        $idnHost = 'xn--ce-yia.example'; // some punycode host
        // Not whitelisted → refused
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://' . $idnHost . '/foo',
            self::ALLOWED,
        ));
        // Explicitly whitelisted → accepted
        $this->assertTrue(UrlValidator::isSafeOutbound(
            'https://' . $idnHost . '/foo',
            [$idnHost],
        ));
    }

    public function testRefusesNonHttpScheme(): void {
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'ftp://app.example.com/file',
            self::ALLOWED,
        ));
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'file:///etc/passwd',
            self::ALLOWED,
        ));
    }

    public function testIsSafeRedirectMirrorsOutboundRules(): void {
        $this->assertTrue(UrlValidator::isSafeRedirect(
            'https://app.example.com/inbox',
            self::ALLOWED,
        ));
        $this->assertFalse(UrlValidator::isSafeRedirect(
            'https://10.0.0.1/internal',
            ['10.0.0.1'],
        ));
        $this->assertFalse(UrlValidator::isSafeRedirect(
            'https://app.example.com@evil.com/phish',
            self::ALLOWED,
        ));
    }

    public function testRefusesSneaky172Range(): void {
        // 172.16.0.0/12 is private — common confusion since 172.x is mostly public.
        $this->assertFalse(UrlValidator::isSafeOutbound(
            'https://172.16.5.10/internal',
            ['172.16.5.10'],
        ));
        // Outside the private range should pass IP-wise (still needs whitelist)
        $this->assertTrue(UrlValidator::isSafeOutbound(
            'https://172.32.5.10/svc',
            ['172.32.5.10'],
        ));
    }
}
