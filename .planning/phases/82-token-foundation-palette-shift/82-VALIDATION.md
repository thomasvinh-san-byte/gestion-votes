---
phase: 82
slug: token-foundation-palette-shift
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-04-03
---

# Phase 82 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (existing) + bash grep assertions |
| **Config file** | phpunit.xml |
| **Quick run command** | `grep -c "color-mix(in srgb" public/assets/css/design-system.css` |
| **Full suite command** | `bash -c 'echo "srgb count:"; grep -c "color-mix(in srgb" public/assets/css/design-system.css || echo 0; echo "oklch count:"; grep -c "oklch" public/assets/css/design-system.css; echo "hex in semantics:"; grep -n "^  --color-" public/assets/css/design-system.css \| grep "#[0-9a-fA-F]" \| wc -l'` |
| **Estimated runtime** | ~2 seconds |

---

## Sampling Rate

- **After every task commit:** Run `grep -c "color-mix(in srgb" public/assets/css/design-system.css` (must return 0 when complete)
- **After every plan wave:** Run full grep suite for srgb, hex, rgba remnants
- **Before `/gsd:verify-work`:** Full suite must show zero srgb, zero hex in semantic tokens
- **Max feedback latency:** 2 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 82-01-01 | 01 | 1 | COLOR-01 | grep | `grep "oklch" public/assets/css/design-system.css \| grep "^  --color-"` | ✅ | ⬜ pending |
| 82-01-02 | 01 | 1 | COLOR-02 | grep | `grep "stone-" public/assets/css/design-system.css \| grep "var(--stone"` | ✅ | ⬜ pending |
| 82-01-03 | 01 | 1 | COLOR-04 | grep | `grep "color-mix(in oklch" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 82-01-04 | 01 | 1 | COLOR-05 | grep | `grep -A5 'data-theme="dark"' public/assets/css/design-system.css \| grep "oklch"` | ✅ | ⬜ pending |
| 82-02-01 | 02 | 1 | COLOR-03 | grep | `grep "color-accent" public/assets/css/design-system.css` | ✅ | ⬜ pending |
| 82-02-02 | 02 | 1 | COLOR-05 | grep | `grep "critical-tokens" templates/*.htmx.html \| head -5` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements. No test framework changes needed — validation is grep-based for CSS token migration.

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Warm-neutral gray visible in dashboard | COLOR-02 | Visual appearance | Open dashboard in light mode, compare to pre-change screenshot |
| Dark mode surfaces warm-tinted | COLOR-05 | Visual appearance | Toggle dark mode, verify warm-dark surfaces |
| No flash-of-wrong-color on load | COLOR-05 | Runtime behavior | Hard refresh any page, watch for color flash |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 2s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
