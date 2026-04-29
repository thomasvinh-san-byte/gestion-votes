<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Core\Http\ClientIp;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ClientIp — proxy-aware client IP and HTTPS resolution.
 *
 * Tests the security boundary: forwarded headers must only be honored when
 * REMOTE_ADDR matches an entry in TRUSTED_PROXIES. Otherwise the headers
 * are caller-controlled and untrustworthy.
 */
final class ClientIpTest extends TestCase {
    private string $envBackup;
    /** @var array<string, mixed> */
    private array $serverBackup;

    protected function setUp(): void {
        parent::setUp();
        $this->envBackup = getenv('TRUSTED_PROXIES') !== false ? (string) getenv('TRUSTED_PROXIES') : '';
        $this->serverBackup = $_SERVER;
        ClientIp::reset();
    }

    protected function tearDown(): void {
        if ($this->envBackup === '') {
            putenv('TRUSTED_PROXIES');
        } else {
            putenv('TRUSTED_PROXIES=' . $this->envBackup);
        }
        $_SERVER = $this->serverBackup;
        ClientIp::reset();
        parent::tearDown();
    }

    public function testGetReturnsRemoteAddrWhenNoTrustedProxiesConfigured(): void {
        putenv('TRUSTED_PROXIES');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        ClientIp::reset();

        $this->assertSame('203.0.113.10', ClientIp::get());
    }

    public function testGetIgnoresXForwardedForFromUntrustedPeer(): void {
        putenv('TRUSTED_PROXIES=10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '203.0.113.99'; // not in trusted list
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        ClientIp::reset();

        // Spoofing attempt rejected — we use the real socket peer.
        $this->assertSame('203.0.113.99', ClientIp::get());
    }

    public function testGetUsesXForwardedForFromTrustedProxy(): void {
        putenv('TRUSTED_PROXIES=10.0.0.1,10.0.0.2');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1'; // trusted proxy
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
        ClientIp::reset();

        $this->assertSame('198.51.100.7', ClientIp::get());
    }

    public function testGetUsesFirstHopFromMultiHopXff(): void {
        putenv('TRUSTED_PROXIES=10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.5, 10.0.0.1';
        ClientIp::reset();

        $this->assertSame('198.51.100.7', ClientIp::get());
    }

    public function testGetFallsBackToRemoteWhenXffMalformed(): void {
        putenv('TRUSTED_PROXIES=10.0.0.1');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
        ClientIp::reset();

        $this->assertSame('10.0.0.1', ClientIp::get());
    }

    public function testGetReturnsUnknownWhenRemoteAddrAbsent(): void {
        putenv('TRUSTED_PROXIES');
        unset($_SERVER['REMOTE_ADDR']);
        ClientIp::reset();

        $this->assertSame('unknown', ClientIp::get());
    }

    public function testIsHttpsTrueWhenServerHttpsOn(): void {
        putenv('TRUSTED_PROXIES');
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        ClientIp::reset();

        $this->assertTrue(ClientIp::isHttps());
    }

    public function testIsHttpsFalseWhenServerHttpsOff(): void {
        putenv('TRUSTED_PROXIES');
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        ClientIp::reset();

        $this->assertFalse(ClientIp::isHttps());
    }

    public function testIsHttpsIgnoresForwardedProtoFromUntrustedPeer(): void {
        putenv('TRUSTED_PROXIES=10.0.0.1');
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.99'; // not trusted
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        ClientIp::reset();

        // Spoofing attempt rejected — we don't flip Secure for an untrusted caller.
        $this->assertFalse(ClientIp::isHttps());
    }

    public function testIsHttpsHonorsForwardedProtoFromTrustedProxy(): void {
        putenv('TRUSTED_PROXIES=10.0.0.1');
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        ClientIp::reset();

        $this->assertTrue(ClientIp::isHttps());
    }

    public function testTrustedProxiesIgnoresInvalidEntries(): void {
        putenv('TRUSTED_PROXIES=,10.0.0.1, not-an-ip ,10.0.0.2,');
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7';
        ClientIp::reset();

        // 10.0.0.2 is parsed correctly despite mangled CSV → XFF honored.
        $this->assertSame('198.51.100.7', ClientIp::get());
    }
}
