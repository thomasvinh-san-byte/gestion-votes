---
phase: 3
phase_name: Périmètre & SSRF
milestone: v2.1
verdict: implemented_and_tested
date: 2026-04-29
---

# Phase 3 SUMMARY — Périmètre & SSRF

| Req | Verdict | Approach | Tests |
|---|---|---|---|
| HARDEN-F11 | ✓ | New `UrlValidator` helper. Wired into `MonitoringService::sendWebhook` (refuses non-https, RFC1918, link-local, userinfo) and `EmailTrackingController::redirect`. CURLOPT_FOLLOWLOCATION=false + CURLOPT_PROTOCOLS=CURLPROTO_HTTPS. | 16/16 ✓ |
| HARDEN-F12 | ✓ | Tracking endpoints throttled at 200/60s per ClientIp. Password reset: per-email rate limit (3/600s) + constant-time response (250-330ms). | n/a (covered by RateLimiter @group redis) |
| HARDEN-F13 | ✓ | New `AccountLockout` helper. Progressive backoff 5 → 1min, 2^n minutes capped at 24h. Reset on successful login. Wired pre-DB read. | 5/5 ✓ (pure logic; Redis tests deferred) |

## Commits

- `feat(F11): UrlValidator gate for outbound webhook + email redirects`
- `feat(F12): rate limits on tracking + reset, constant-time response`
- `feat(F13): progressive per-account login lockout`
