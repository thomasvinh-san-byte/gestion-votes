---
phase: 05
slug: csp-nonce-enforcement
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-10
---

# Phase 05 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (via Docker) + PHPUnit |
| **Config file** | `tests/e2e/playwright.config.ts` |
| **Quick run command** | `docker exec agvote-playwright npx playwright test csp --project=chromium` |
| **Full suite command** | `docker exec agvote-playwright npx playwright test --project=chromium` |
| **Estimated runtime** | ~45 seconds (CSP spec), ~120 seconds (full suite) |

---

## Sampling Rate

- **After every task commit:** Run CSP-related specs + php -l on modified PHP
- **After every plan wave:** Run full Playwright chromium suite
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 05-01-01 | 01 | 1 | CSP-01 | unit | `php -l app/Core/Security/SecurityProvider.php && grep 'nonce' app/Core/Security/SecurityProvider.php` | ✅ | ⬜ pending |
| 05-01-02 | 01 | 1 | CSP-02 | grep | `grep -rE '<script[^>]*nonce' public/*.htmx.html \| wc -l` | ✅ | ⬜ pending |
| 05-02-01 | 02 | 2 | CSP-03 | e2e | `docker exec agvote-playwright npx playwright test csp --project=chromium` | ❌ W0 | ⬜ pending |
| 05-02-02 | 02 | 2 | CSP-04 | e2e | `docker exec agvote-playwright npx playwright test csp --project=chromium` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/e2e/specs/csp-enforcement.spec.js` — CSP header validation and violation check

*New spec needed. Existing test infrastructure covers regression.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| None | — | — | All verifiable via automated commands |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
