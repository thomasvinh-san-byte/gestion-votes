---
phase: 5
phase_name: Headers, cookies & defense-in-depth
milestone: v2.1
verdict: implemented_and_tested
date: 2026-04-29
---

# Phase 5 SUMMARY — Headers, cookies & defense-in-depth

| Req | Verdict | Approach | Tests |
|---|---|---|---|
| HARDEN-F17 | ✓ | `CSP_STRICT_MODE=1` env var promotes nonce-based CSP from Report-Only to enforcing. Default behavior unchanged for backward compat. | n/a (env-controlled) |
| HARDEN-F18 | ✓ | SameSite default Strict (was Lax). Override via `SESSION_COOKIE_SAMESITE`. Invalid values fall back to Strict. | 6/6 ✓ |
| HARDEN-F19 | ✓ | `bootstrap.php` refuses APP_ENV=production + APP_DEBUG=1 with SECURITY-prefixed log. APP_SECRET >=32 chars already enforced in AuthMiddleware. | n/a (boot-time) |

## Commits
- `feat(F17+F18+F19): CSP strict mode opt-in, SameSite=Strict default, prod-debug refused`
