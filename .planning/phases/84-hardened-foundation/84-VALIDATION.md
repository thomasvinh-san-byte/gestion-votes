---
phase: 84
slug: hardened-foundation
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-03
---

# Phase 84 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | bash grep assertions |
| **Config file** | N/A |
| **Quick run command** | `grep -rn '#[0-9a-fA-F]\{3,6\}\|rgba(' public/assets/css/ --include='*.css' --exclude='design-system.css' \| wc -l` |
| **Full suite command** | `bash -c 'echo "hex/rgba in page CSS:"; grep -rn "#[0-9a-fA-F]\{3,6\}\|rgba(" public/assets/css/ --include="*.css" --exclude="design-system.css" \| wc -l; echo "stale hex in components:"; grep -r "1650E0\|22,80,224\|rgba(22" public/assets/js/components/ \| wc -l; echo "stale focus ring:"; grep -r "rgba(22,80,224,0.35)" public/assets/js/components/ \| wc -l'` |
| **Estimated runtime** | ~3 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick grep to verify hex/rgba count decreasing
- **After every plan wave:** Run full suite
- **Before `/gsd:verify-work`:** Full suite must show zero for all counts
- **Max feedback latency:** 3 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 84-01-01 | 01 | 1 | HARD-01 | grep | `grep -rn "#[0-9a-fA-F]\\{3,6\\}\|rgba(" public/assets/css/ --exclude=design-system.css \| wc -l` | ✅ | ⬜ pending |
| 84-02-01 | 02 | 1 | HARD-02 | grep | `grep -r "1650E0\|22,80,224\|rgba(22" public/assets/js/components/ \| wc -l` | ✅ | ⬜ pending |
| 84-02-02 | 02 | 1 | HARD-05 | grep | `grep -r "rgba(22,80,224,0.35)" public/assets/js/components/ \| wc -l` | ✅ | ⬜ pending |
| 84-03-01 | 03 | 2 | HARD-04 | grep | `grep -c "@property" public/assets/css/design-system.css` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Dark mode toggle no flash on Web Components | HARD-03 | Runtime | Toggle dark mode on dashboard, watch Web Components |
| Smooth animated color transition on buttons | HARD-04 | Runtime | Hover/focus buttons, verify smooth color change |
| Focus ring consistency across Web Components | HARD-05 | Visual | Tab through interactive elements, check 2px indigo outline |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity
- [ ] Wave 0 covers all MISSING references
- [ ] Feedback latency < 3s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
