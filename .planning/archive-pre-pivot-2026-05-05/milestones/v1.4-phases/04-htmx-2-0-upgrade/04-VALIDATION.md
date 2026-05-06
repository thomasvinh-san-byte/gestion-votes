---
phase: 04
slug: htmx-2-0-upgrade
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-10
---

# Phase 04 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (via Docker) + PHPUnit |
| **Config file** | `tests/e2e/playwright.config.ts` |
| **Quick run command** | `docker exec agvote-playwright npx playwright test --project=chromium` |
| **Full suite command** | `docker exec agvote-playwright npx playwright test` |
| **Estimated runtime** | ~120 seconds (chromium), ~300 seconds (full cross-browser) |

---

## Sampling Rate

- **After every task commit:** Run chromium suite
- **After every plan wave:** Run full cross-browser suite
- **Before `/gsd:verify-work`:** Full cross-browser suite must be green
- **Max feedback latency:** 120 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 04-01-01 | 01 | 1 | HTMX-01 | integration | `node -e "require('fs').readFileSync('public/assets/vendor/htmx.min.js','utf8').includes('2.0.6')"` | ✅ | ⬜ pending |
| 04-01-02 | 01 | 1 | HTMX-04 | grep | `grep -c 'htmx-ext' public/shell.htmx.html` | ✅ | ⬜ pending |
| 04-02-01 | 02 | 2 | HTMX-03 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` | ✅ | ⬜ pending |
| 04-02-02 | 02 | 2 | HTMX-02 | grep | `grep -rE 'hx-on="[^:]' public/*.html public/*.htmx.html` | ✅ | ⬜ pending |
| 04-03-01 | 03 | 3 | HTMX-05 | e2e | `docker exec agvote-playwright npx playwright test` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No new test files needed.

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
- [ ] Feedback latency < 120s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
