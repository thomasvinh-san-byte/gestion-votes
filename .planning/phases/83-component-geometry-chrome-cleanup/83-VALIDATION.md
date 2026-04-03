---
phase: 83
slug: component-geometry-chrome-cleanup
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-03
---

# Phase 83 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | bash grep assertions + browser visual |
| **Config file** | N/A |
| **Quick run command** | `grep -c "border-radius:" public/assets/css/design-system.css` |
| **Full suite command** | `bash -c 'echo "radius aliases:"; grep -c "radius-btn\|radius-card\|radius-panel\|radius-modal\|radius-toast\|radius-tooltip\|radius-tag\|radius-input" public/assets/css/design-system.css; echo "shadow levels:"; grep "^  --shadow-" public/assets/css/design-system.css | wc -l; echo "srgb in shadows:"; grep -c "color-mix(in srgb" public/assets/css/design-system.css || echo 0'` |
| **Estimated runtime** | ~2 seconds |

---

## Sampling Rate

- **After every task commit:** Run quick grep to verify radius/shadow changes
- **After every plan wave:** Run full grep suite
- **Before `/gsd:verify-work`:** Full suite + visual spot-check
- **Max feedback latency:** 2 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 83-01-01 | 01 | 1 | COMP-01 | grep | `grep "radius-base" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 83-01-02 | 01 | 1 | COMP-02 | grep | `grep "^  --shadow-sm\|^  --shadow-md\|^  --shadow-lg" public/assets/css/design-system.css \| wc -l` | ✅ | ⬜ pending |
| 83-01-03 | 01 | 1 | COMP-03 | grep | `grep "border-alpha\|oklch.*0\\.08" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 83-02-01 | 02 | 2 | COMP-04 | grep | `grep "skeleton-shimmer\|skeleton-kpi" public/assets/css/design-system.css` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test framework changes needed.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Single --radius-base changes all corners simultaneously | COMP-01 | Visual/runtime | Change --radius-base to 4px, verify all corners update |
| Shadow vocabulary visible in UI (sm/md/lg only) | COMP-02 | Visual | Inspect cards, dropdowns, modals for correct shadow levels |
| Alpha borders float on any background | COMP-03 | Visual | Place card on white vs gray surface, check border visibility |
| Shimmer animation on dashboard load | COMP-04 | Runtime | Throttle network, verify shimmer appears |
| prefers-reduced-motion stops shimmer | COMP-04 | Runtime | Enable reduced motion in OS, verify static placeholder |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 2s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
