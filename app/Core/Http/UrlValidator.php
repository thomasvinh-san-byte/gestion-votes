<?php

declare(strict_types=1);

namespace AgVote\Core\Http;

/**
 * UrlValidator — gate for outbound and redirect URLs.
 *
 * F11 hardening: when the application contacts an arbitrary URL it received
 * from configuration (`MONITOR_WEBHOOK_URL`) or from a query parameter
 * (`/api/v1/email_redirect.php?url=...`), it MUST refuse anything that could
 * be used to:
 *   - Reach internal infrastructure (RFC1918, link-local, loopback) → SSRF
 *   - Reach cloud metadata services (169.254.169.254 etc.) → IAM credential theft
 *   - Use a non-https scheme → leaks data over plaintext
 *   - Inject userinfo (`https://example.com@evil.com`) → phishing redirect
 *   - Hide an IDN/punycode lookalike of a trusted host
 *
 * Two entry points:
 *
 *   isSafeOutbound(string $url, list<string> $allowedHosts): bool
 *     For outbound HTTP calls (webhooks, fetches we initiate).
 *     Requires https and an EXACT match in $allowedHosts.
 *
 *   isSafeRedirect(string $url, list<string> $allowedHosts): bool
 *     For redirects we emit to a user (Location: ...). Same rules.
 *
 * The functions never throw — callers decide the response (refuse silently,
 * fall back to a safe default, or 4xx).
 */
final class UrlValidator {
    /** RFC1918 private + loopback + link-local + multicast + reserved IPv4 ranges. */
    private const PRIVATE_IPV4_CIDRS = [
        ['10.0.0.0',     8],   // RFC1918
        ['172.16.0.0',  12],   // RFC1918
        ['192.168.0.0', 16],   // RFC1918
        ['127.0.0.0',    8],   // loopback
        ['169.254.0.0', 16],   // link-local + cloud metadata
        ['100.64.0.0',  10],   // CGNAT
        ['224.0.0.0',    4],   // multicast
        ['0.0.0.0',      8],   // "this network"
    ];

    public static function isSafeOutbound(string $url, array $allowedHosts): bool {
        return self::evaluate($url, $allowedHosts, requireHttps: true);
    }

    /**
     * Same gate as outbound — redirects out of a trusted email link must
     * also stay on https + a whitelisted host. Userinfo is rejected so a
     * `https://app.com@evil.com/...` payload cannot impersonate the host.
     */
    public static function isSafeRedirect(string $url, array $allowedHosts): bool {
        return self::evaluate($url, $allowedHosts, requireHttps: true);
    }

    private static function evaluate(string $url, array $allowedHosts, bool $requireHttps): bool {
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $parsed = parse_url($url);
        if (!is_array($parsed) || !isset($parsed['scheme'], $parsed['host'])) {
            return false;
        }

        $scheme = strtolower((string) $parsed['scheme']);
        if ($requireHttps) {
            if ($scheme !== 'https') {
                return false;
            }
        } elseif (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        // Refuse userinfo (user:pass@host) — common phishing trick.
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }

        $host = strtolower((string) $parsed['host']);
        if ($host === '') {
            return false;
        }

        // Refuse IDN/punycode unless caller whitelisted the punycode form
        // explicitly. xn-- prefixed labels can be lookalikes.
        if (str_contains($host, 'xn--')) {
            return self::matchesAllowedHost($host, $allowedHosts);
        }

        // Hostname must be in the whitelist (case-insensitive exact match).
        if (!self::matchesAllowedHost($host, $allowedHosts)) {
            return false;
        }

        // If the host is already an IP literal, validate the IP directly.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isPublicIp($host);
        }

        // For DNS hostnames we don't resolve here — DNS rebinding is a
        // separate concern that requires connect-time pinning. The host
        // whitelist + scheme check is the practical gate at this layer.
        return true;
    }

    /**
     * @param list<string> $allowed
     */
    private static function matchesAllowedHost(string $host, array $allowed): bool {
        foreach ($allowed as $candidate) {
            if (strcasecmp($host, trim($candidate)) === 0) {
                return true;
            }
        }
        return false;
    }

    private static function isPublicIp(string $ip): bool {
        // IPv4: explicit CIDR check (filter_var FILTER_FLAG_NO_PRIV_RANGE
        // misses 169.254.x.x and a few others, so we do it ourselves).
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return false;
            }
            foreach (self::PRIVATE_IPV4_CIDRS as [$base, $bits]) {
                $baseLong = ip2long($base);
                if ($baseLong === false) {
                    continue;
                }
                $mask = $bits === 0 ? 0 : (0xFFFFFFFF << (32 - $bits)) & 0xFFFFFFFF;
                if (($long & $mask) === ($baseLong & $mask)) {
                    return false;
                }
            }
            return true;
        }

        // IPv6: refuse loopback, link-local, ULA, IPv4-mapped private.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return (bool) filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
        }

        return false;
    }
}
