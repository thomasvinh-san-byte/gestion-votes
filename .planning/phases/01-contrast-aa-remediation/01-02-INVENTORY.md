# Plan 01-02 — Inventory: Shadow DOM hex fallbacks

**Date:** 2026-04-10
**Scope:** `public/assets/js/components/*.js`
**Purpose:** Exhaustive list of `var(--color-*, #hex)` occurrences to strip (Pitfall #1 mitigation).

## Audit commands

```bash
# 1. Total count of hex fallbacks
grep -rnE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/ | wc -l
# -> 110

# 2. Full file:line listing
grep -rnE 'var\(--color-[^,)]*,\s*#[0-9a-fA-F]{3,8}' public/assets/js/components/

# 3. Files concerned (dedup)
grep -rlE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/ | sort -u

# 4. oklch fallbacks to PRESERVE (pattern authorized by Pitfall #1)
grep -rnE 'var\(--color-[^,)]*,\s*oklch' public/assets/js/components/
# -> 0 (none exist — nothing to preserve)
```

## Summary

- **Total occurrences:** 110
- **Files affected:** 16 (out of 23 Web Components under `public/assets/js/components/`)
- **Unaffected components (7):** `ag-badge.js`, `ag-confirm.js`, `ag-empty-state.js`, `ag-kpi.js`, `ag-modal.js`, `ag-quorum-bar.js`, `index.js`
- **oklch fallbacks to preserve:** 0

## Per-file counts

| # | File | Occurrences |
|---|------|-------------|
| 1 | `ag-breadcrumb.js` | 4 |
| 2 | `ag-donut.js` | 1 |
| 3 | `ag-mini-bar.js` | 1 |
| 4 | `ag-page-header.js` | 2 |
| 5 | `ag-pagination.js` | 5 |
| 6 | `ag-pdf-viewer.js` | 9 |
| 7 | `ag-popover.js` | 12 |
| 8 | `ag-scroll-top.js` | 1 |
| 9 | `ag-searchable-select.js` | 23 |
| 10 | `ag-spinner.js` | 2 |
| 11 | `ag-stepper.js` | 11 |
| 12 | `ag-time-input.js` | 5 |
| 13 | `ag-toast.js` | 13 |
| 14 | `ag-tooltip.js` | 3 |
| 15 | `ag-tz-picker.js` | 3 |
| 16 | `ag-vote-button.js` | 15 |
| | **Total** | **110** |

## Full listing (file:line — token — old fallback — action)

### ag-time-input.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 121 | `--color-surface` | `#fff` | remove |
| 122 | `--color-border` | `#d5dbd2` | remove |
| 134 | `--color-text-dark` | `#1a1a1a` | remove |
| 137 | `--color-text-light` | `#b5b5b0` | remove |
| 138 | `--color-text-muted` | `#95a3a4` | remove |

### ag-page-header.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 29 | `--color-text-dark` | `#1a1a1a` | remove |
| 44 | `--color-text-muted` | `#95a3a4` | remove |

### ag-scroll-top.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 59 | `--color-primary-hover` | `#1241b8` | remove |

### ag-mini-bar.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 39 | `--color-bg-subtle` | `#e8e7e2` | remove |

### ag-breadcrumb.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 27 | `--color-text-muted` | `#95a3a4` | remove |
| 30 | `--color-text-muted` | `#95a3a4` | remove |
| 37 | `--color-text-dark` | `#1a1a1a` | remove |
| 41 | `--color-text-light` | `#b5b5b0` | remove |

### ag-stepper.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 39 | `--color-border` | `#d5dbd2` | remove |
| 43 | `--color-success` | `#16a34a` | remove |
| 49 | `--color-border` | `#d5dbd2` | remove |
| 50 | `--color-surface` | `#fff` | remove |
| 54 | `--color-text-muted` | `#95a3a4` | remove |
| 59 | `--color-success` | `#16a34a` | remove |
| 60 | `--color-success` | `#16a34a` | remove |
| 61 | `--color-text-inverse` | `#fff` | remove |
| 66 | `--color-primary-text` | `#fff` | remove |
| 71 | `--color-text-muted` | `#95a3a4` | remove |
| 74 | `--color-success` | `#16a34a` | remove |

### ag-searchable-select.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 260 | `--color-surface` | `#ffffff` | remove |
| 261 | `--color-border` | `#d5dbd2` | remove |
| 266 | `--color-text` | `#4e5340` | remove |
| 270 | `--color-border-hover` | `#a0a897` | remove |
| 275 | `--color-surface` | `#ffffff` | remove |
| 291 | `--color-text-muted` | `#7a8275` | remove |
| 314 | `--color-surface` | `#ffffff` | remove |
| 330 | `--color-border` | `#d5dbd2` | remove |
| 333 | `--color-surface` | `#ffffff` | remove |
| 340 | `--color-border` | `#d5dbd2` | remove |
| 343 | `--color-bg-subtle` | `#f5f7f4` | remove |
| 350 | `--color-surface` | `#ffffff` | remove |
| 354 | `--color-text-muted` | `#7a8275` | remove |
| 364 | `--color-text-muted` | `#7a8275` | remove |
| 384 | `--color-bg-subtle` | `#f5f7f4` | remove |
| 387 | `--color-primary-subtle` | `#e8f0e8` | remove |
| 396 | `--color-text` | `#4e5340` | remove |
| 401 | `--color-text-muted` | `#7a8275` | remove |
| 410 | `--color-text-muted` | `#7a8275` | remove |
| 411 | `--color-bg-subtle` | `#f5f7f4` | remove |
| 419 | `--color-text-muted` | `#7a8275` | remove |
| 434 | `--color-text-muted` | `#7a8275` | remove |
| 449 | `--color-warning-subtle` | `#fff3cd` | remove |

### ag-tz-picker.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 65 | `--color-border` | `#d5dbd2` | remove |
| 67 | `--color-surface` | `#fff` | remove |
| 70 | `--color-text-dark` | `#1a1a1a` | remove |

### ag-pdf-viewer.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 126 | `--color-border` | `#e5e7eb` | remove |
| 143 | `--color-surface` | `#fafaf7` | remove |
| 167 | `--color-surface` | `#fafaf7` | remove |
| 201 | `--color-border` | `#e5e7eb` | remove |
| 203 | `--color-surface` | `#fafaf7` | remove |
| 213 | `--color-text-dark` | `#1a1a1a` | remove |
| 237 | `--color-text-muted` | `#6b7280` | remove |
| 246 | `--color-bg-subtle` | `#e8e7e2` | remove |
| 247 | `--color-text-dark` | `#1a1a1a` | remove |

### ag-vote-button.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 88 | `--color-border` | `#d5dbd2` | remove |
| 94 | `--color-surface` | `#ffffff` | remove |
| 97 | `--color-text-dark` | `#1a1a1a` | remove |
| 138 | `--color-success-subtle` | `#e4ede4` | remove |
| 145 | `--color-danger-subtle` | `#f2e4e4` | remove |
| 151 | `--color-border-dash` | `#c4c3bc` | remove |
| 152 | `--color-bg-subtle` | `#e8e7e2` | remove |
| 154 | `--color-text-muted` | `#95a3a4` | remove |
| 157 | `--color-border` | `#d5dbd2` | remove |
| 158 | `--color-bg-subtle` | `#e8e7e2` | remove |
| 160 | `--color-text-muted` | `#95a3a4` | remove |
| 178 | `--color-text-muted` | `#95a3a4` | remove |
| 179 | `--color-text-muted` | `#95a3a4` | remove |
| 184 | `--color-text-muted` | `#95a3a4` | remove |
| 185 | `--color-text-muted` | `#95a3a4` | remove |

### ag-toast.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 93 | `--color-surface-raised` | `#ffffff` | remove |
| 94 | `--color-border` | `#d5dbd2` | remove |
| 121 | `--color-text` | `#1a1a1a` | remove |
| 134 | `--color-text-muted` | `#95a3a4` | remove |
| 140 | `--color-bg-subtle` | `#e8e7e2` | remove |
| 141 | `--color-text-dark` | `#1a1a1a` | remove |
| 157 | `--color-success` | `#0b7a40` | remove |
| 159 | `--color-success-subtle` / `--color-success` | `#e4ede4` / `#0b7a40` | remove (2 on line) |
| 161 | `--color-danger` | `#c42828` | remove |
| 163 | `--color-danger-subtle` / `--color-danger` | `#f2e4e4` / `#c42828` | remove (2 on line) |
| 165 | `--color-warning` | `#b8860b` | remove |
| 167 | `--color-warning-subtle` / `--color-warning` | `#f5eddf` / `#b8860b` | remove (2 on line) |

Note: Lines 157, 161, 165 also contain `var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1))` — `--shadow-*` is OUT OF SCOPE and must be preserved untouched. Only the nested `var(--color-*, #hex)` within those lines gets stripped.
Line 171 uses `var(--color-info)` (already without fallback) and `var(--color-info-subtle, #EBF0FF)` — only the latter gets stripped.

### ag-spinner.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 58 | `--color-border` | `#d5dbd2` | remove |
| 74 | `--color-primary-subtle` | `#e8edfa` | remove |

### ag-tooltip.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 32 | `--color-text-dark` | `#1a1a1a` | remove |
| 33 | `--color-surface-raised` | `#fff` | remove |
| 53 | `--color-text-dark` x2 (template literal ternary) | `#1a1a1a` | remove |

### ag-pagination.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 68 | `--color-border-subtle` | `#e8e7e2` | remove |
| 74 | `--color-border` | `#d5dbd2` | remove |
| 75 | `--color-surface` | `#fff` | remove |
| 76 | `--color-text-muted` | `#95a3a4` | remove |
| 83 | `--color-primary-subtle` | `#e8edfa` | remove |

### ag-popover.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 221 | `--color-surface-raised` | `#fff` | remove |
| 222 | `--color-border` | `#d5dbd2` | remove |
| 227 | `--color-text` | `#1a1a1a` | remove |
| 297 | `--color-surface-raised` | `#fff` | remove |
| 298 | `--color-border` | `#d5dbd2` | remove |
| 338 | `--color-text` | `#1a1a1a` | remove |
| 342 | `--color-text-muted` | `#95a3a4` | remove |
| 358 | `--color-text-muted` | `#95a3a4` | remove |
| 369 | `--color-border` | `#d5dbd2` | remove |
| 370 | `--color-bg-subtle` | `#e8e7e2` | remove |
| 371 | `--color-text-muted` | `#95a3a4` | remove |
| 380 | `--color-primary-subtle` | `#e8edfa` | remove |

### ag-donut.js

| Line | Token | Old fallback | Action |
|------|-------|--------------|--------|
| 64 | `--color-text-dark` | `#1a1a1a` | remove |

## Out-of-scope patterns (must NOT be touched)

- `var(--shadow-lg, 0 20px 25px -5px rgba(0,0,0,0.1))` — `--shadow-*` prefix, not `--color-*`
- `var(--toast-accent-width, 3px)` — `--toast-*` prefix
- `var(--size-*, ...)`, `var(--radius-*, ...)` — geometry, not color
- Any `var(--color-*, oklch(...))` — authorized by Pitfall #1 (count: 0 in current codebase)

## Gate after edits

```bash
grep -rnE 'var\(--color-[^,)]*,\s*#' public/assets/js/components/
```

MUST return 0 results.
