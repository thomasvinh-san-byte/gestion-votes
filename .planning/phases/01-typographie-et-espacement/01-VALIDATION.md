---
phase: 1
slug: typographie-et-espacement
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 1 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 10.5 + Playwright E2E |
| **Config file** | `phpunit.xml` / `tests/e2e/playwright.config.ts` |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Full suite command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Estimated runtime** | ~30 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php -l` on modified PHP files (syntax check)
- **After every plan wave:** Run full unit test suite
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 30 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Threat Ref | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|------------|-----------------|-----------|-------------------|-------------|--------|
| 1-01-01 | 01 | 1 | TYPO-01 | — | N/A | visual | Manual inspection of rendered pages | N/A | ⬜ pending |
| 1-01-02 | 01 | 1 | TYPO-02 | — | N/A | visual | Manual inspection of form labels | N/A | ⬜ pending |
| 1-01-03 | 01 | 1 | TYPO-03 | — | N/A | visual | Manual inspection of header height | N/A | ⬜ pending |
| 1-01-04 | 01 | 1 | TYPO-04 | — | N/A | visual | Manual inspection of form spacing | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. This is a CSS-only phase — validation is primarily visual inspection and E2E regression tests.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Base font renders at 16px | TYPO-01 | Visual CSS rendering | Inspect body text in browser DevTools, verify computed font-size is 16px |
| Labels display in normal case | TYPO-02 | Visual CSS rendering | Check form labels on any page — should be sentence case, not UPPERCASE |
| Header height is 64px | TYPO-03 | Visual CSS rendering | Inspect .app-header in DevTools, verify computed height is 64px |
| Form spacing is 20-24px | TYPO-04 | Visual CSS rendering | Inspect gap between form fields, verify 20px or 24px spacing |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
