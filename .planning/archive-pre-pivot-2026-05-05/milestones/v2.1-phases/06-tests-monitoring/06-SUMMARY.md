---
phase: 6
phase_name: Tests & monitoring gate
milestone: v2.1
verdict: implemented_and_tested
date: 2026-04-29
---

# Phase 6 SUMMARY — Tests & monitoring gate

| Req | Verdict | Approach | Tests |
|---|---|---|---|
| HARDEN-F20 | ✓ | New `tests/Security/` testsuite + `HardeningRegressionTest` (11 tripwires across F02-F18) | 11/11 ✓ |
| HARDEN-F21 | ✓ | `app/Core/Security/SecuritySignal.php` — escalation logger with 60 s rolling counter + 5 min cooldown. Wired into AuthController on invalid_credentials. | n/a (Redis-backed, integration suite) |
| HARDEN-F22 | ✓ | `SECURITY.md` (responsible disclosure + scope + operational signals) + `SECURITY_AUDIT.md` updated with F01-F22 status table | n/a (doc) |

## Single commit (Phase 6 closure)

`feat(F20+F21+F22): Security testsuite, signal escalation, SECURITY.md`

## Milestone closure

Run from CI: `vendor/bin/phpunit --testsuite Security --no-coverage` → must remain 11/11 green forever.
