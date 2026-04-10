---
phase: 03
slug: trust-fixtures-deploy
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-10
---

# Phase 03 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Playwright (via Docker) + PHPUnit |
| **Config file** | `tests/e2e/playwright.config.ts` |
| **Quick run command** | `docker exec agvote-playwright npx playwright test trust --project=chromium` |
| **Full suite command** | `docker exec agvote-playwright npx playwright test --project=chromium` |
| **Estimated runtime** | ~45 seconds (trust specs), ~120 seconds (full suite) |

---

## Sampling Rate

- **After every task commit:** Run trust-related specs
- **After every plan wave:** Run full Playwright chromium suite
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 45 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 03-01-01 | 01 | 1 | TRUST-01 | integration | `grep 'loginAsAuditor' tests/e2e/helpers/auditor.js` | ❌ W0 | ⬜ pending |
| 03-01-02 | 01 | 1 | TRUST-01 | integration | `grep 'loginAsAssessor' tests/e2e/helpers/assessor.js` | ❌ W0 | ⬜ pending |
| 03-01-03 | 01 | 1 | TRUST-02 | unit | `php -l app/Controller/TestSeedController.php` | ❌ W0 | ⬜ pending |
| 03-02-01 | 02 | 2 | TRUST-03 | e2e | `docker exec agvote-playwright npx playwright test trust --project=chromium` | ✅ | ⬜ pending |
| 03-02-02 | 02 | 2 | TRUST-02 | e2e | `grep -c 'loginAsAdmin' tests/e2e/specs/trust*.spec.js` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/e2e/helpers/auditor.js` — login helper for auditor role
- [ ] `tests/e2e/helpers/assessor.js` — login helper for assessor role
- [ ] Seed endpoint controller — `app/Controller/TestSeedController.php`

*New files created during execution. Existing test infrastructure covers regression.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Production route gate | TRUST-02 | Requires APP_ENV=production to verify 404 | Set APP_ENV=production, curl POST /api/v1/test/seed-user, confirm 404 |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 45s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
