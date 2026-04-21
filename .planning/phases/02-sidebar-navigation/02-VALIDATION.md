---
phase: 2
slug: sidebar-navigation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-21
---

# Phase 2 — Validation Strategy

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
| 2-01-01 | 01 | 1 | NAV-01 | — | N/A | grep+visual | `grep "sidebar-width" public/assets/css/design-system.css` | N/A | ⬜ pending |
| 2-01-02 | 01 | 1 | NAV-03 | — | N/A | grep | `grep "nav-item" public/assets/css/design-system.css` | N/A | ⬜ pending |
| 2-02-01 | 02 | 1 | NAV-02 | — | N/A | grep | `grep "data-requires-role" public/partials/sidebar.html` | N/A | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. CSS and JS changes verified via grep and visual inspection.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Sidebar always visible at 200px | NAV-01 | Visual CSS rendering | Open any page, verify sidebar is 200px with labels visible |
| Votant sees only Voter + Mon compte | NAV-02 | Requires role-specific login | Login as voter, verify only 2 nav items visible |
| Touch targets 44px minimum | NAV-03 | Visual measurement | Inspect nav-item in DevTools, verify height >= 44px |
| Sidebar works at 1366px | NAV-01 | Visual at breakpoint | Resize to 1366px, verify no content overlap |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 30s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
