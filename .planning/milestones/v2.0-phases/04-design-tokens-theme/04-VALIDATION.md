---
phase: 4
slug: design-tokens-theme
status: draft
nyquist_compliant: true
wave_0_complete: false
created: 2026-03-12
---

# Phase 4 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Bash diff / grep validation (CSS token comparison) |
| **Config file** | none — scripts created in Wave 0 |
| **Quick run command** | `bash .planning/phases/04-design-tokens-theme/validate-tokens.sh` |
| **Full suite command** | `bash .planning/phases/04-design-tokens-theme/validate-tokens.sh --full` |
| **Estimated runtime** | ~2 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick validation (token count + key values)
- **After every plan wave:** Run full suite (all token comparisons)
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** 2 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 04-01-01 | 01 | 1 | DS-01 | diff | `grep --color-bg design-system.css` | ❌ W0 | ⬜ pending |
| 04-01-02 | 01 | 1 | DS-02 | diff | `grep shadow design-system.css` | ❌ W0 | ⬜ pending |
| 04-01-03 | 01 | 1 | DS-03 | diff | `grep data-theme design-system.css` | ❌ W0 | ⬜ pending |
| 04-01-04 | 01 | 1 | DS-04 | diff | `grep font-family design-system.css` | ❌ W0 | ⬜ pending |
| 04-01-05 | 01 | 1 | DS-05 | diff | `grep sidebar design-system.css` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `validate-tokens.sh` — script that extracts token values from design-system.css and compares against wireframe reference values
- [ ] Reference token values extracted from wireframe HTML

*Existing infrastructure covers CSS loading — validation is about value correctness.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Surface elevation visual hierarchy | DS-01 | Visual distinction requires human eye | Toggle theme, check bg < surface < surface-raised < glass |
| Dark/light theme toggle smooth | DS-03 | Visual artifacts need human check | Toggle `data-theme` attribute, verify no flash/missing colors |
| Font rendering quality | DS-04 | Font rendering is browser-dependent | Check Bricolage Grotesque body, Fraunces headings, JetBrains Mono code |

---

## Validation Sign-Off

- [x] All tasks have automated verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 2s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
