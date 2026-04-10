---
phase: 06
slug: controller-refactoring
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-10
---

# Phase 06 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit ^10.5 + Playwright (via Docker) |
| **Config file** | `phpunit.xml` / `tests/e2e/playwright.config.ts` |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/{Controller}Test.php --no-coverage` |
| **Full suite command** | `timeout 120 php vendor/bin/phpunit --no-coverage` |
| **Estimated runtime** | ~15 seconds (targeted), ~60 seconds (full PHPUnit), ~120 seconds (Playwright) |

---

## Sampling Rate

- **After every task commit:** Run targeted controller/service test
- **After every plan wave:** Run full PHPUnit suite
- **Before `/gsd:verify-work`:** Full PHPUnit + Playwright critical-path
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 06-01-01 | 01 | 1 | CTRL-05 | audit | `grep -c 'ReflectionClass' tests/Unit/MeetingsControllerTest.php` | ✅ | ⬜ pending |
| 06-01-02 | 01 | 1 | CTRL-05 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingsControllerTest.php --no-coverage` | ✅ | ⬜ pending |
| 06-02-01 | 02 | 2 | CTRL-01 | unit | `wc -l app/Controller/MeetingsController.php` | ✅ | ⬜ pending |
| 06-02-02 | 02 | 2 | CTRL-02 | unit | `wc -l app/Services/MeetingLifecycleService.php` | ❌ W0 | ⬜ pending |
| 06-03-01 | 03 | 3 | CTRL-03 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingLifecycleServiceTest.php --no-coverage` | ❌ W0 | ⬜ pending |
| 06-04-01 | 04 | 4 | CTRL-04 | e2e | `docker exec agvote-playwright npx playwright test critical-path --project=chromium` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] New service files created during execution (Wave 2)
- [ ] New service test files created during execution (Wave 3)

*Existing PHPUnit and Playwright infrastructure covers regression.*

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
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
