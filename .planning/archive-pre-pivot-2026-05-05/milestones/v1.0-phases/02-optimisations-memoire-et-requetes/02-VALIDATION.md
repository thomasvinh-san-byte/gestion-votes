---
phase: 2
slug: optimisations-memoire-et-requetes
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-07
---

# Phase 2 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit ^10.5 |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage` |
| **Full suite command** | `timeout 120 php vendor/bin/phpunit tests/ --no-coverage` |
| **Estimated runtime** | ~15 seconds |

---

## Sampling Rate

- **After every task commit:** Run `timeout 60 php vendor/bin/phpunit tests/Unit/ --no-coverage`
- **After every plan wave:** Run `timeout 120 php vendor/bin/phpunit tests/ --no-coverage`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 15 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| TBD | 02-01 | 1 | PERF-01 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/DatabaseProviderTest.php --no-coverage` | ❌ W0 | ⬜ pending |
| TBD | 02-01 | 1 | PERF-02 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/MeetingStatsRepositoryTest.php --no-coverage` | ❌ W0 | ⬜ pending |
| TBD | 02-02 | 1 | PERF-03 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/ExportServiceTest.php --no-coverage` | ✅ | ⬜ pending |
| TBD | 02-03 | 1 | PERF-04 | unit | `timeout 60 php vendor/bin/phpunit tests/Unit/EmailQueueServiceTest.php --no-coverage` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/Unit/DatabaseProviderTest.php` — stub for PERF-01 timeout verification
- [ ] `tests/Unit/MeetingStatsRepositoryTest.php` — stub for PERF-02 aggregation verification

*Existing ExportServiceTest.php and EmailQueueServiceTest.php need extension only.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| XLSX export of 5000 rows < 3MB memory | PERF-03 | Memory measurement requires runtime profiling | Run export with memory_get_peak_usage() wrapper |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 15s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
