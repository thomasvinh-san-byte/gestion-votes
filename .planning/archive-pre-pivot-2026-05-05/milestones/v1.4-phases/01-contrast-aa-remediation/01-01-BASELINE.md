# 01-01 BASELINE — Contrast-critical token audit

**Date:** 2026-04-10
**Phase:** 01-contrast-aa-remediation
**Plan:** 01 (token shift, atomic commit)
**Source audit:** `.planning/v1.3-A11Y-REPORT.md` §3 + `.planning/v1.3-CONTRAST-AUDIT.json`

## Important discovery — hex values are computed colors, not source literals

The 4 hex values quoted in the v1.3 a11y report (`#988d7a`, `#bdb7a9`, `#9d9381`,
`#4d72d8`) are the **rendered RGB** values that axe-core extracted from the live
DOM at audit time. They do **not** appear as literals in any source file:

```
$ grep -rni "988d7a\|bdb7a9\|9d9381\|4d72d8" public/
(no matches)
```

The plan's pitfall #2 mitigation ("propagate to 22 critical-tokens inline blocks")
assumed these hex values lived in inline `<style id="critical-tokens">` blocks.
After inspection, those blocks contain only `--color-bg`, `--color-surface`,
`--color-text` (verified across all 22 files). None of the contrast-critical
tokens are duplicated inline, so **same-commit propagation is a no-op for this
shift**. Pitfall #2 is structurally avoided here.

## Source token mapping

### 1. `#988d7a` (muted-foreground, 93+68+16 = 177 occurrences, 22/22 pages)

| Field | Value |
|---|---|
| Source token | `--color-text-muted` |
| `:root` definition (line 330) | `--color-text-muted: var(--stone-600);  /* oklch(0.648 0.030 82) */` |
| `[data-theme="dark"]` (line 628) | `--color-text-muted: oklch(0.450 0.015 265);  /* was #50596C */` |
| Primitive resolved | `--stone-600: oklch(0.648 0.030 82)` (line 143) — renders ≈ `#988d7a` |
| Worst ratio (light) | 2.36 on `#e0dbcf` (`.search-trigger`, dashboard) |
| Audit ref | A11Y-REPORT §3 row 1, 2, 4 |
| **Cible light** | `oklch(0.470 0.030 82)` — L* 0.47, same hue/chroma family |
| **Cible dark** | `oklch(0.780 0.020 82)` — L* 0.78 (light fg on dark bg, warm hue 78/82 per Phase 82-01 convention) |
| Justification | Current dark value (`oklch(0.450 0.015 265)`) is too DARK for dark mode (renders `#50596C`, fails contrast). Bumping to L* 0.78 with warm hue aligns with Phase 82 dark surface hue convention (78). |

### 2. `#bdb7a9` (wizard step inactive, ratio 1.83 — pire)

| Field | Value |
|---|---|
| Source token | **Same as #1**: `--color-text-muted` × `opacity: 0.6` |
| CSS site | `public/assets/css/wizard.css` line 90-104 — `.wiz-step-item { color: var(--color-text-muted); opacity: 0.6; }` |
| Why darker hex than #988d7a? | `opacity: 0.6` blends `#988d7a` (≈ stone-600) over `#f6f5f0` warm bg → effective `#bdb7a9` |
| Mitigation | Bumping `--color-text-muted` to L* 0.47 raises the underlying value enough that even with `opacity: 0.6` blending the wiz-step-item span will reach ≥ 4.5 (validated empirically in plan 01-03 axe re-run) |
| Audit ref | A11Y-REPORT §3 Top-5 worst ratios |

### 3. `#9d9381` (KPI labels / ag-tooltip, 27 occurrences, 4 pages)

| Field | Value |
|---|---|
| Source token | `--color-text-muted` (via `ag-tooltip` Shadow DOM fallback to `var(--color-text-muted)`) and `.kpi-label` cascading text-secondary in some cases |
| Resolution path | The variation between `#988d7a` and `#9d9381` is due to per-page background hue (warm vs cooler) — same source token, anti-aliased differently by axe |
| Selector example | `#usersCount` (admin), `.kpi-card .kpi-label ag-tooltip` (archives) |
| Mitigation | Subsumed by fix #1 (same source token) |
| Audit ref | A11Y-REPORT §3 row 3, 6 |

### 4. `#4d72d8` (chip actif settings, ratio 3.89, 10 occurrences)

| Field | Value |
|---|---|
| Source token | `--color-primary` (resolves to `--blue-600 = oklch(0.520 0.195 265)`) |
| `:root` definition (line 342) | `--color-primary: var(--blue-600);  /* oklch(0.520 0.195 265) */` |
| `[data-theme="dark"]` (line 640) | `--color-primary: var(--blue-400);  /* oklch(0.680 0.130 265) — was #3D7EF8 */` |
| Selector | `button[data-stab="regles"]` on `--color-primary-subtle` background (`#ebf0f9`) |
| **Brand-critical** | `--color-primary` IS the brand blue. Bumping it system-wide would shift identity. |
| **Strategy** | Add a NEW companion token `--color-primary-on-subtle: oklch(0.440 0.190 265)` (darker variant L*=0.44). Wiring this token into `button[data-stab="regles"]` is OUT OF SCOPE for plan 01-01 (would require touching `settings.css`, not in `files_modified`). It is therefore deferred to plan 01-02 (wave 2). The token alias is added now for atomicity. |
| Pitfall #3 compliance | Adds new token alongside; never renames or deletes `--color-primary`. |
| Audit ref | A11Y-REPORT §3 row 5 |

## Inventaire critical-tokens inline blocks

Files containing `<style id="critical-tokens">`:

```
public/admin.htmx.html             public/operator.htmx.html
public/analytics.htmx.html         public/postsession.htmx.html
public/archives.htmx.html          public/public.htmx.html
public/audit.htmx.html             public/report.htmx.html
public/dashboard.htmx.html         public/settings.htmx.html
public/docs.htmx.html              public/trust.htmx.html
public/email-templates.htmx.html   public/users.htmx.html
public/help.htmx.html              public/validate.htmx.html
public/hub.htmx.html               public/vote.htmx.html
public/meetings.htmx.html          public/wizard.htmx.html
public/members.htmx.html
```

Note: `public/login.html` does NOT contain a `<style id="critical-tokens">` block
(it uses its own dedicated CSS). It is still listed in plan `files_modified` for
defensive sweep but contains no contrast-critical token literals to update.

**Block content (uniform across all 21 files):**

```css
:root {
  --color-bg: oklch(0.922 0.013 95);
  --color-surface: oklch(0.969 0.006 95);
  --color-text: oklch(0.180 0.012 75);
}
[data-theme="dark"] {
  --color-bg: oklch(0.090 0.008 78);
  --color-surface: oklch(0.115 0.009 78);
  --color-text: oklch(0.908 0.015 265);
}
```

**Conclusion:** Inline blocks declare 3 tokens (`--color-bg`, `--color-surface`,
`--color-text`). NONE of the 4 contrast-critical tokens (`--color-text-muted`,
`--color-primary`, etc.) appear inline. Task 3 of plan 01-01 is therefore a
verification no-op: nothing to propagate.

## Targets summary

| Token | Light current | Light target | Dark current | Dark target |
|---|---|---|---|---|
| `--color-text-muted` | `var(--stone-600)` (`oklch(0.648 0.030 82)`) | `oklch(0.470 0.030 82)` | `oklch(0.450 0.015 265)` | `oklch(0.780 0.020 82)` |
| `--color-primary-on-subtle` (NEW) | (not defined) | `oklch(0.440 0.190 265)` | (not defined) | `oklch(0.760 0.130 265)` |

`--color-primary` itself is left untouched (brand identity preservation).
`--color-text-secondary` is already `--stone-900` (oklch 0.180) and passes contrast
on all warm surfaces — no change needed.

## Pitfalls neutralized

- **#2** (critical-tokens inline drift) — verified inline blocks contain none of
  the target tokens; no propagation needed; same-commit rule trivially satisfied.
- **#3** (token renames break Shadow DOM) — `--color-text-muted` is value-shifted,
  not renamed. `--color-primary-on-subtle` is added alongside (not replacing).
- **#15** (JS re-introducing contrast at runtime) — no JS touched.

## Verification commands

```bash
# After Task 2:
grep -n "color-text-muted" public/assets/css/design-system.css
grep -n "color-primary-on-subtle" public/assets/css/design-system.css

# After Task 3 (sanity):
grep -l "#988d7a\|#bdb7a9\|#9d9381\|#4d72d8" public/*.htmx.html public/login.html public/assets/css/design-system.css
# expected: empty (already empty at baseline; remains empty after the shift)
```
