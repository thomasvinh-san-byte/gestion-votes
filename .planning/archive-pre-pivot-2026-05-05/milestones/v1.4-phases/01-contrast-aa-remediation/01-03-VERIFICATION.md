# 01-03 Verification Log

## Contrast Audit Iterations

### Run 1 (baseline after Plans 01-01 + 01-02)

- **Command:** Docker playwright contrast-audit.spec.js with CONTRAST_AUDIT=1
- **Timestamp:** 2026-04-10T05:08:27Z
- **Result:** 8 violation groups across 8 pages (down from 316 baseline)
- **Residual pairs (13 unique):**
  - `#3661d7` on `#e8e5dc`: 7 (docs table links — primary on surface-alt)
  - `#9e988b` on `#f6f5f0`: 3 (wizard step text — muted with opacity 0.6)
  - `#00793f` on `#ccd3c3`: 2 (onboarding done steps — success on success-mix bg)
  - `#00793f` on `#dbe9de`: 2 (member avatars — success on success-mix bg)
  - `#ffffff` on `#6485e1`: 1 (filter-pill-count — white on lightened primary)
  - `#8f887a` on `#f6f5f0`: 1 (onboarding-optional — muted with opacity 0.7)
  - `#305cd7` on `#d2d1d0`: 1 (onboarding action link — primary on gray)
  - `#726858` on `#e8e5dc`: 1 (archives toolbar desc — muted on surface-alt)
  - `#9a9487` on `#f5f3ee`: 1 (empty-state-description — muted on card bg)
  - `#797060` on `#e8e5dc`: 1 (email-templates text-muted on surface-alt)
  - `#fbfbf9` on `#4e72d8`: 1 (btn-primary text — white on brand blue)
  - `#2d8e5d` on `#eaf5eb`: 1 (postsession alert — success on success-subtle)
  - `#007a41` on `#e8e5dc`: 1 (report pv-step-label done — success on surface-alt)

- **Micro-adjustments applied:**
  1. `--color-text-muted` light: oklch(0.470 -> 0.430)
  2. `--color-primary` light: var(--blue-600) -> oklch(0.505 0.195 265)
  3. Added `--color-success-text` darkened to oklch(0.400 0.135 155)
  4. Added `--color-success-on-subtle` companion token (both themes)
  5. wizard.css: removed opacity: 0.6 from .wiz-step-item
  6. members.css: removed opacity: 0.7 from .onboarding-optional
  7. members.css: .onboarding-step.done -> --color-success-on-subtle
  8. members.css: .onboarding-step.action -> --color-primary-on-subtle
  9. members.css: .member-card.is-active .member-avatar -> --color-success-on-subtle
  10. meetings.css: .filter-pill.active .filter-pill-count -> dark tint instead of white mix
  11. doc.css: .prose a + .doc-breadcrumb a -> --color-primary-on-subtle
  12. report.css: .pv-timeline-step.done .pv-step-label -> --color-success-on-subtle

### Run 2 (after first batch of fixes)

- **Timestamp:** 2026-04-10T05:17:51Z
- **Result:** 6 violation groups across 6 pages
- **Residual pairs:**
  - `#797263` on `#f4f2ed` (4.26): audit.htmx.html empty-state-description
  - `#7a7264` on `#f5f4ef` (4.31): archives.htmx.html empty-state-description
  - `#5778d4` on `#ebeff6` (3.61): help.htmx.html .tour-launch x10
  - `#6f6757` on `#e8e5dc` (4.44): email-templates #emptyState text-muted
  - `#c15555` on `#eeeadf` (3.71): hub dashboard-urgent__eyebrow (danger)
  - `#797162` on various warm bgs (4.01-4.27): hub session-card-meta, shortcut-card-sub, dashboard-aside__title; validate summary-label, form-label-required, form-helper

- **Additional micro-adjustments applied:**
  13. `--color-text-muted` light: oklch(0.430 -> 0.400)
  14. `--color-danger-text` darkened to oklch(0.430 0.175 25)
  15. pages.css: .dashboard-urgent__eyebrow -> --color-danger-text
  16. design-system.css: .form-label-required::after -> --color-danger-text
  17. help.css: .tour-launch + .tour-icon -> --color-primary-on-subtle
  18. design-system.css: .session-card-meta stripped hex fallback (#95a3a4)

### Run 3 (after second batch of fixes)

- **Timestamp:** 2026-04-10T05:21:25Z
- **Result:** 5 violation groups across 5 pages
- **Residual pairs:**
  - `#797263` on `#f4f2ed` (4.26): audit empty-state — muted still barely under
  - `#7a7264` on `#f5f4ef` (4.31): archives empty-state — muted still barely under
  - `#5778d4` on `#ebeff6` (3.61): help tour-launch — was still using --color-primary (not yet deployed)
  - `#558d67` on `#eaf2e8` (3.40): postsession alert — success-text still too light
  - `#825cb9` on `#f3eff9` (4.43): users purple badge — no companion token yet
  - `#0f8a58` on `#ebf6ec` (3.94): users success status badge

- **Post-run-3 fixes (applied, need verification in run 4):**
  19. `--color-text-muted` light: oklch(0.400 -> 0.380)
  20. `--color-success-text`: oklch(0.400 -> 0.350)
  21. `--color-success-on-subtle`: oklch(0.400 -> 0.350)
  22. Added `--color-accent-text` darkened to oklch(0.400 0.170 298) + companion tokens
  23. Added `--color-accent-on-subtle`, `--color-purple-on-subtle` (both themes)
  24. users.css: all role-badge + avatar + status-badge variants -> on-subtle tokens
  25. design-system.css: tag-accent/success/purple -> on-subtle tokens, stripped tag-purple hex fallbacks
  26. contrast-audit.spec.js: added totalViolations + uniquePairs aggregation to output

### Run 4 (cache-disabled, post all fixes)

- **Timestamp:** 2026-04-10T05:31:00Z
- **Result:** 1 violation (1 unique pair) — archives empty-state-description mid-fadeIn animation
- **Fix applied:** Bumped --color-text-muted L0.360 -> L0.340, added 500ms animation settle wait in spec

### Run 5 (definitive)

- **Timestamp:** 2026-04-10T05:33:00Z
- **Result:** **0 violations, 0 unique pairs across 22 pages**
- **Commits:** 04a90287, 7fe099b1, b2a4e109

### Token Summary (final values)

| Token | Light mode | Dark mode |
|-------|-----------|-----------|
| --color-text-muted | oklch(0.340 0.030 82) | oklch(0.780 0.020 82) |
| --color-primary | oklch(0.480 0.195 265) | var(--blue-400) unchanged |
| --color-success-text | oklch(0.350 0.135 155) | oklch(0.650 0.155 155) |
| --color-success-on-subtle | oklch(0.350 0.135 155) | oklch(0.650 0.155 155) |
| --color-danger-text | oklch(0.430 0.175 25) | var(--red-500) unchanged |
| --color-accent-text | oklch(0.400 0.170 298) | oklch(0.700 0.175 298) |
| --color-accent-on-subtle | oklch(0.400 0.170 298) | oklch(0.700 0.175 298) |
| --color-purple-on-subtle | oklch(0.400 0.170 298) | oklch(0.700 0.175 298) |
| --color-primary-on-subtle | oklch(0.440 0.190 265) | oklch(0.820 0.100 265) |

## Regression Tests

### accessibility.spec.js

- **Command:** `bin/test-e2e.sh specs/accessibility.spec.js`
- **Timestamp:** 2026-04-10T05:25:00Z
- **Result:** 26 passed (52.3s)
- **Baseline match:** 26/26 (v1.3 Phase 16-02 baseline)
- **Conclusion:** No structural regression

### keyboard-nav.spec.js

- **Command:** `bin/test-e2e.sh specs/keyboard-nav.spec.js`
- **Timestamp:** 2026-04-10T05:26:00Z
- **Result:** 6 passed (12.7s)
- **Baseline match:** 6/6 (v1.3 Phase 16-04 baseline)
- **Conclusion:** No keyboard navigation regression
