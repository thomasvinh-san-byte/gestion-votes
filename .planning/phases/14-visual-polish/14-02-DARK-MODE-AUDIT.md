# Phase 14-02 Dark Mode Parity Audit

**Date:** 2026-04-07
**Requirement:** POLISH-02

## Scope Clarification

Contrary to 14-UI-SPEC.md § POLISH-02 "Known debt" (which listed 6 per-page CSS files),
grep verification on 2026-04-07 shows that **all 25 per-page CSS files already contain zero
hex/rgba/hsl literals**. Phase 12 closed this debt.

Verification command and result:

```
for f in public/assets/css/*.css; do
  c=$(grep -cE '#[0-9a-fA-F]{3,6}|rgba?\(|hsla?\(' "$f");
  if [ "$c" != "0" ] && [ "$(basename $f)" != "design-system.css" ]; then
    echo "$f: $c";
  fi;
done
# (empty — all per-page CSS is token-clean; design-system.css legitimately
# contains OKLCH definitions and therefore scores non-zero but is excluded)
```

The real remaining debt lives in Web Component Shadow DOM fallback hex literals.

## Per-file audit (Web Components)

| File | Baseline | After Task 1 | Status | Notes |
|---|---|---|---|---|
| ag-badge.js | 23 | 0 | FIXED | All `var(--token, #hex)` fallbacks removed; all variant color rules cleaned |
| ag-confirm.js | 16 | 0 | FIXED | `rgba(0,0,0,.45)` backdrop → `var(--color-backdrop)`; all variant and button color fallbacks removed |
| ag-kpi.js | 14 | 1* | FIXED | All color literals removed; remaining grep hit is `&#039;` in `escapeHtml()` — HTML entity, not a color |
| ag-modal.js | 8 | 0 | FIXED | `rgba(0,0,0,0.5)` backdrop → `var(--color-backdrop)`; all surface/border/text fallbacks removed |
| ag-toast.js | 16 | 16 | PRE-EXISTING | Not touched this plan; toast has its own token-driven box-shadow path; carry-forward |
| ag-breadcrumb.js | 4 | 4 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-donut.js | 1 | 1 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-mini-bar.js | 1 | 1 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-page-header.js | 2 | 2 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-pagination.js | 6 | 6 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-pdf-viewer.js | 12 | 12 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-popover.js | 13 | 13 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-quorum-bar.js | 1 | 1 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-scroll-top.js | 2 | 2 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-searchable-select.js | 25 | 25 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-spinner.js | 4 | 4 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-stepper.js | 11 | 11 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-time-input.js | 5 | 5 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-tooltip.js | 4 | 4 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-tz-picker.js | 3 | 3 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-vote-button.js | 23 | 23 | CARRY-FORWARD | Not fixed this plan — candidate for Phase 17 LOOSE-03 |
| ag-empty-state.js | 0 | 0 | CLEAN | Already token-only |

Grep command to reproduce baseline column:

```
for f in public/assets/js/components/ag-*.js; do
  printf "%-35s %s\n" "$(basename $f)" \
    "$(grep -cE '#[0-9a-fA-F]{3,6}|rgba?\(|hsla?\(' "$f")"
done
```

## Documented exceptions

### ag-kpi.js — 1 grep hit (`&#039;` in `escapeHtml`)

- **File:** `public/assets/js/components/ag-kpi.js`, line 132
- **Content:** `.replace(/'/g, '&#039;');`
- **Reason:** The grep pattern `#[0-9a-fA-F]{3,6}` matches `#039` inside the HTML entity
  string `&#039;`. This is a string replace function escaping single quotes to HTML entities.
  It is not a color literal and has no visual rendering impact.
- **Decision:** Exception granted — no fix required.

## Critical-tokens inline style blocks

`grep -l "critical-tokens" public/*.htmx.html` reports 21 files (audited on 2026-04-07).
These blocks are guaranteed-in-sync with design-system.css as of Phase 84 HARD-03
(per STATE.md decision: "all 21 htmx.html critical-tokens blocks updated from hex to oklch").
No action needed in Phase 14.

## Dark mode verification method

1. Open any of the 4 fixed components in isolation on `/meetings.htmx.html` (renders
   ag-kpi, ag-badge, ag-modal on opening a meeting card action).
2. Toggle theme via `#btnToggleTheme` in the sidebar footer.
3. Confirm: text legible (contrast ≥ 4.5:1 by eye), borders visible, surfaces change hue,
   no ghost elements with hardcoded light-mode colors.

Token coverage after Task 1:

- **Surfaces:** `--color-surface`, `--color-surface-raised` (dark overrides defined in design-system.css)
- **Backdrop:** `--color-backdrop` (0.50 in light, 0.70 in dark — richer scrim)
- **Text:** `--color-text-dark`, `--color-text-muted`, `--color-text-inverse`
- **Borders:** `--color-border`, `--color-border-subtle`
- **Hover states:** `--color-bg-subtle`
- **Semantic colors:** `--color-{danger|warning|success|info|primary}{|-subtle}` — all dark-overridden

## Carry-forward decisions

The 9 non-fixed components (plus ag-toast) are carry-forward to Phase 17 LOOSE-03 if
dark-mode issues surface during multi-browser testing (Phase 15) or a11y audit (Phase 16).
Total carry-forward literal count: 133 across 18 files (excluding ag-empty-state which is
already clean).
