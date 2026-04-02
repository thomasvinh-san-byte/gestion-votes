---
phase: 48
slug: settings-admin-rebuild
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-03-22
---

# Phase 48 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | None — manual browser testing |
| **Config file** | n/a |
| **Quick run command** | Open browser at `/settings.htmx.html` and `/admin.htmx.html` |
| **Full suite command** | Manual: load both pages, save settings, CRUD users, verify KPIs |
| **Estimated runtime** | ~120 seconds |

---

## Sampling Rate

- **After every task commit:** Open both pages, verify no JS errors
- **After every plan wave:** Full flow: save settings + reload, create/edit/deactivate user
- **Before `/gsd:verify-work`:** All 4 success criteria verified
- **Max feedback latency:** 120 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 48-01-01 | 01 | 1 | REB-06 | manual-visual | Browser → settings tabs, admin KPIs, user table | n/a | ⬜ pending |
| 48-02-01 | 02 | 2 | WIRE-01 | manual-functional | Save settings → reload → values persist | n/a | ⬜ pending |
| 48-02-02 | 02 | 2 | REB-06 | manual-functional | Admin KPIs show real counts | n/a | ⬜ pending |
| 48-02-03 | 02 | 2 | REB-06 | manual-functional | Create/edit/deactivate user → changes reflect | n/a | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test files to create.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Settings persist after reload | REB-06 | Requires save + reload cycle | Change a setting, save, reload, verify value |
| Admin KPIs show real counts | REB-06 | Requires live data | Load admin, verify counts match DB |
| User CRUD works | REB-06 | End-to-end functional | Create user, edit, deactivate |
| No JS console errors | REB-06 | Runtime check | Open console, load both pages |
| No dead endpoints | WIRE-01 | Network check | Check Network tab during all operations |

---

## Validation Sign-Off

- [x] All tasks have manual verification procedures
- [x] Sampling continuity: every commit gets browser smoke test
- [x] Wave 0 covers all MISSING references (none needed)
- [x] No watch-mode flags
- [x] Feedback latency < 120s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
