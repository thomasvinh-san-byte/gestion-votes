---
phase: 84-hardened-foundation
verified: 2026-04-03T10:53:01Z
status: gaps_found
score: 4/6 must-haves verified
re_verification: false
gaps:
  - truth: "grep 'rgba(22, 80, 224' design-system.css returns zero results (plan 01 acceptance criterion)"
    status: failed
    reason: "9 occurrences of rgba(22,80,224) remain in design-system.css component rules (lines 928, 1230, 1231, 1512, 1523 and others). SUMMARY claimed this was verified as 0."
    artifacts:
      - path: "public/assets/css/design-system.css"
        issue: "rgba(22, 80, 224, .28) at line 928 (.logo-mark box-shadow), rgba(22, 80, 224, .15) and rgba(22, 80, 224, .3) at lines 1230-1231 (nav active state), rgba(22,80,224,.3) and rgba(22,80,224,.2) at lines 1512/1523 (button shadows)"
    missing:
      - "Replace rgba(22, 80, 224, ...) in @layer components rules with var(--color-primary) references or oklch() literals"
      - "Replace rgba(196, 40, 40, .4) at line 1184 (.nav-badge box-shadow) with oklch(0.510 0.175 25 / 0.40)"
      - "Replace persona tokens at lines 702-720 using rgba() with oklch() equivalents"

  - truth: "ag-vote-button.js uses var(--color-danger-glow) and var(--color-success-glow) token references (key link from plan 03)"
    status: partial
    reason: "ag-vote-button.js uses oklch literals (oklch(0.500 0.135 155 / 0.14), oklch(0.510 0.175 25 / 0.14)) instead of the token references var(--color-success-glow) and var(--color-danger-glow). The tokens exist in design-system.css but the component does not reference them."
    artifacts:
      - path: "public/assets/js/components/ag-vote-button.js"
        issue: "Lines 140, 147-148, 167, 174 use raw oklch() literals; var(--color-success-glow) and var(--color-danger-glow) are never referenced"
    missing:
      - "Replace oklch(0.500 0.135 155 / 0.14) with var(--color-success-glow) in ag-vote-button.js hover/glow states"
      - "Replace oklch(0.510 0.175 25 / 0.14) with var(--color-danger-glow) in ag-vote-button.js hover/glow states"

  - truth: "REQUIREMENTS.md HARD-02 is marked complete after plan 03 execution"
    status: failed
    reason: "REQUIREMENTS.md still shows HARD-02 as [ ] (unchecked). Plan 03 SUMMARY claimed HARD-02 was satisfied but the requirements file was never updated."
    artifacts:
      - path: ".planning/REQUIREMENTS.md"
        issue: "Line shows '- [ ] **HARD-02**' — should be [x] after plan 03 completion"
    missing:
      - "Update REQUIREMENTS.md to mark HARD-02 as [x] completed"
      - "Update ROADMAP.md plan checkboxes for 84-02-PLAN.md and 84-03-PLAN.md (both still show [ ])"

human_verification:
  - test: "Toggle dark mode while dashboard is open"
    expected: "No visible flash of incorrect color on any Web Component (Shadow DOM tokens consistent with page theme)"
    why_human: "Dark mode toggle behavior and visual flash requires browser rendering to verify"
  - test: "Apply 'transition: color 150ms, background-color 150ms' to a button and hover"
    expected: "Smooth animated color change, not a hard cut, because tokens are registered via @property"
    why_human: "CSS transition smoothness requires browser rendering to observe"
  - test: "Focus any interactive element in any Web Component"
    expected: "2px indigo outline matching page-level focus ring, no legacy rgba(22,80,224,0.35) hardcoded value"
    why_human: "Focus ring visual appearance requires browser interaction to verify"
---

# Phase 84: Hardened Foundation Verification Report

**Phase Goal:** The codebase has zero escape hatches — no hardcoded hex in any page CSS file, all Shadow DOM fallback literals reflect the current palette, critical-tokens inline blocks are in sync, color tokens can be animated via CSS transitions, and focus rings across all Web Components use the token reference pattern
**Verified:** 2026-04-03T10:53:01Z
**Status:** gaps_found
**Re-verification:** No — initial verification

## Goal Achievement

### Observable Truths

| #  | Truth                                                                                          | Status      | Evidence                                                            |
|----|-----------------------------------------------------------------------------------------------|-------------|---------------------------------------------------------------------|
| 1  | Per-page CSS: zero hardcoded hex/rgba (ROADMAP SC#1, HARD-01)                                 | VERIFIED    | grep returns 0 results across all 18 per-page CSS files             |
| 2  | Shadow DOM: zero `1650E0\|22,80,224\|rgba(22` fallbacks (ROADMAP SC#2, HARD-02)               | VERIFIED    | grep returns 0 across all 19 component JS files                     |
| 3  | critical-tokens oklch in all 21 .htmx.html files (HARD-03)                                   | VERIFIED    | 21/21 files contain `color-bg: oklch(0.922 0.013 95)`               |
| 4  | 8 @property blocks before @layer declaration, 5 new tokens in :root (HARD-04)                | VERIFIED    | Lines 18-57 of design-system.css, all 5 new tokens confirmed        |
| 5  | ag-modal.js + ag-toast.js focus rings use var(--shadow-focus) with no rgba fallback (HARD-05) | VERIFIED    | ag-modal.js line 151, ag-toast.js line 145 both use var(--shadow-focus) |
| 6  | design-system.css components layer has zero rgba(22,80,224) or rgba(196,40,40) (plan 01 AC)   | FAILED      | 9 occurrences of rgba(22,80,224) + 1 of rgba(196,40,40) remain in @layer components |

**Score:** 5/6 truths verified (ROADMAP success criteria all pass; 1 plan-level acceptance criterion fails)

### Required Artifacts

| Artifact                                             | Expected                                                    | Status    | Details                                                         |
|------------------------------------------------------|-------------------------------------------------------------|-----------|-----------------------------------------------------------------|
| `public/assets/css/design-system.css`                | 8 @property blocks, 5 new tokens, fixed ring-color, fixed shadow-focus-danger | VERIFIED | @property lines 18-57, new tokens lines 349-391, ring-color line 558 = oklch, shadow-focus-danger lines 469-470 use var(--color-danger-focus) |
| `public/assets/css/operator.css`                     | SSE dots use --color-danger/success tokens, zero hex/rgba  | VERIFIED  | 0 hex/rgba results                                              |
| `public/assets/css/analytics.css`                    | Print media values use var(--color-text)/var(--color-bg)   | VERIFIED  | 0 hex/rgba results                                              |
| `public/assets/css/users.css`                        | All hex fallbacks stripped                                  | VERIFIED  | 0 hex/rgba results                                              |
| `public/assets/js/components/ag-modal.js`            | Focus ring via var(--shadow-focus), no rgba literal        | VERIFIED  | Line 151: `box-shadow: var(--shadow-focus);`                    |
| `public/assets/js/components/ag-toast.js`            | Focus ring via var(--shadow-focus), no rgba literal        | VERIFIED  | Line 145: `box-shadow: var(--shadow-focus);`                    |
| `public/assets/js/components/ag-vote-button.js`      | Success/danger glow via var() token references             | PARTIAL   | Uses oklch literals instead of var(--color-danger-glow)/var(--color-success-glow) |
| `public/assets/js/components/ag-kpi.js`              | color-mix uses oklch not srgb                              | VERIFIED  | Line 63: `color-mix(in oklch, var(--color-primary) 18%, ...)`  |
| `public/assets/js/components/ag-donut.js`            | JS fallback uses var(--color-border) not '#ccc'            | VERIFIED  | Line 47: `seg.color \|\| 'var(--color-border)'`                |
| `public/assets/js/components/ag-quorum-bar.js`       | Zero hex fallbacks in var() calls                          | VERIFIED  | 0 hex fallbacks in var() calls                                  |
| `.planning/REQUIREMENTS.md`                          | All HARD-01 to HARD-05 marked [x]                         | PARTIAL   | HARD-01, HARD-03, HARD-04, HARD-05 marked [x]; HARD-02 still [ ] |

### Key Link Verification

| From                                        | To                             | Via                                         | Status   | Details                                                                      |
|---------------------------------------------|--------------------------------|---------------------------------------------|----------|------------------------------------------------------------------------------|
| `design-system.css (@property --color-primary)` | CSS transition engine      | `@property syntax: '<color>'` before @layer | WIRED    | @property block at line 18, @layer declaration at line 59                   |
| `design-system.css (--color-danger-focus)`  | `--shadow-focus-danger`        | `var(--color-danger-focus)` in definition   | WIRED    | Lines 469-470 confirmed                                                      |
| `ag-modal.js`                               | `design-system.css`            | `var(--shadow-focus)` resolves to token chain | WIRED  | Line 151 uses var(--shadow-focus) with no fallback                           |
| `ag-vote-button.js`                         | `design-system.css`            | `var(--color-danger-glow)` new token        | NOT_WIRED | Component uses oklch(0.510 0.175 25 / 0.14) literal; token not referenced  |
| `operator.css`                              | `design-system.css`            | `var(--color-success-glow)` from plan 01    | WIRED    | `var(--color-success-glow)` found in operator.css                           |

### Requirements Coverage

| Requirement | Source Plan   | Description                                             | Status  | Evidence                                                              |
|-------------|---------------|---------------------------------------------------------|---------|-----------------------------------------------------------------------|
| HARD-01     | 84-02-PLAN.md | Zero hardcoded hex values in per-page CSS files         | SATISFIED | `grep -rn --include='*.css' -E "#[0-9a-fA-F]{3,6}\|rgba\(" public/assets/css/ \| grep -v design-system.css` = 0 |
| HARD-02     | 84-03-PLAN.md | Shadow DOM fallback hex values audited + updated        | SATISFIED (code) / UNSATISFIED (docs) | grep returns 0 for `1650E0\|22,80,224\|rgba(22`; ~167 non-primary hex fallbacks remain but outside criterion scope; REQUIREMENTS.md still `[ ]` |
| HARD-03     | 84-01-PLAN.md | critical-tokens inline styles synced with oklch values  | SATISFIED | 21/21 htmx.html files contain correct oklch critical-tokens           |
| HARD-04     | 84-01-PLAN.md | Animatable color tokens registered via @property        | SATISFIED | 8 @property blocks before @layer, `syntax: '<color>'` confirmed       |
| HARD-05     | 84-03-PLAN.md | Focus ring token pattern in Shadow DOM components       | SATISFIED | ag-modal.js + ag-toast.js both use var(--shadow-focus), no rgba(22,80,224,0.35) found |

### Anti-Patterns Found

| File                                      | Line(s)        | Pattern                                    | Severity | Impact                                                              |
|-------------------------------------------|----------------|--------------------------------------------|----------|---------------------------------------------------------------------|
| `public/assets/css/design-system.css`    | 928            | `rgba(22, 80, 224, .28)` in .logo-mark     | Warning  | Plan 01 acceptance criterion failed; not covered by ROADMAP SC#1    |
| `public/assets/css/design-system.css`    | 1230-1231      | `rgba(22, 80, 224, .15/.3)` in nav active  | Warning  | Stale primary rgba in component rule; ROADMAP SC#1 excludes this file |
| `public/assets/css/design-system.css`    | 1512, 1523     | `rgba(22,80,224,.3/.2)` in button shadows  | Warning  | Button component rules not tokenized                                |
| `public/assets/css/design-system.css`    | 1184           | `rgba(196, 40, 40, .4)` in .nav-badge      | Warning  | Stale danger rgba not converted to oklch                            |
| `public/assets/css/design-system.css`    | 702-720        | `rgba(129,140,248,...)`  persona tokens    | Info     | 7 persona-subtle tokens use rgba() instead of oklch()               |
| `public/assets/js/components/ag-vote-button.js` | 140, 147 | oklch literals instead of glow tokens | Info  | Tokens exist in design-system.css but component uses hardcoded values |
| `.planning/REQUIREMENTS.md`              | —              | HARD-02 still `[ ]` unchecked              | Warning  | Documentation inconsistency; ROADMAP Progress table says phase is complete |
| `.planning/ROADMAP.md`                   | —              | 84-02 + 84-03 plan checkboxes still `[ ]`  | Info     | Inconsistency with Progress table showing 3/3 plans complete        |

### Human Verification Required

#### 1. Dark Mode Toggle — No Flash on Web Components

**Test:** Load the dashboard page, open browser devtools to confirm dark mode CSS class, then toggle between light/dark mode rapidly
**Expected:** No visible flash of incorrect color on any Web Component (ag-kpi, ag-modal, ag-toast, ag-badge, etc.)
**Why human:** Shadow DOM token inheritance during theme toggle requires browser rendering; cannot verify programmatically

#### 2. CSS Transition Smoothness via @property

**Test:** Apply `transition: color 150ms, background-color 150ms` to a `.btn-primary` element and hover over it
**Expected:** Smooth animated color change, not a hard cut — confirms `@property --color-primary` with `syntax: '<color>'` is working
**Why human:** Color interpolation behavior requires browser rendering to observe

#### 3. Focus Ring Visual Consistency

**Test:** Tab through interactive elements inside ag-modal and ag-confirm to trigger focus-visible state
**Expected:** 2px indigo outline matching page-level focus ring, consistent across Shadow DOM boundaries
**Why human:** Focus ring visual appearance and cross-boundary consistency requires browser interaction

### Gaps Summary

Three gaps block the plan 01 acceptance criteria and documentation completeness, though the ROADMAP success criteria all pass:

**Gap 1 — design-system.css rgba(22,80,224) in component rules (10 occurrences):**
Plan 01 acceptance criteria stated `grep "rgba(22, 80, 224" design-system.css` returns 0, and the SUMMARY claimed this was verified. In fact, 9 occurrences of `rgba(22, 80, 224,...)` and 1 of `rgba(196, 40, 40,...)` remain in `@layer components` (logo-mark, nav active state, button shadows, nav-badge). These are in component rule declarations, not token definitions. The ROADMAP success criterion #1 explicitly excludes `design-system.css`, so this does not block the ROADMAP goal — but it contradicts the plan 01 acceptance criterion and the phase goal statement ("zero escape hatches").

**Gap 2 — ag-vote-button.js uses oklch literals instead of token references:**
The plan 03 key link specified that ag-vote-button.js would reference `var(--color-danger-glow)` and `var(--color-success-glow)`. The summary documented this as an intentional workaround due to parallel branch execution. Now that branches are merged, the tokens exist in design-system.css but ag-vote-button.js still uses hardcoded oklch literals instead of the token references. This is a minor wiring gap — the values are functionally correct but not token-driven.

**Gap 3 — Documentation not updated:**
REQUIREMENTS.md shows HARD-02 as `[ ]` (unchecked). ROADMAP.md shows 84-02-PLAN.md and 84-03-PLAN.md plan checkboxes as `[ ]` (unchecked). The ROADMAP Progress table correctly shows phase 84 as complete, but the per-plan checkboxes were not updated. No code impact, but creates misleading state for future work.

---

_Verified: 2026-04-03T10:53:01Z_
_Verifier: Claude (gsd-verifier)_
