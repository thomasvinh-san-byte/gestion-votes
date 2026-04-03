---
phase: quick
plan: 260403-gtt
type: execute
wave: 1
depends_on: []
files_modified:
  - public/assets/css/vote.css
  - public/assets/css/members.css
  - public/assets/css/operator.css
  - public/assets/css/pages.css
  - public/assets/css/public.css
  - public/assets/css/meetings.css
  - public/assets/css/landing.css
  - public/assets/css/login.css
  - public/assets/css/postsession.css
  - public/assets/css/archives.css
  - public/assets/css/design-system.css
autonomous: true
requirements: []
must_haves:
  truths:
    - "Zero occurrences of color-mix(in srgb) remain in any per-page CSS file"
    - "Zero rgba() persona tokens remain in design-system.css dark mode block"
    - "All color-mix calls use oklch color space consistently across the codebase"
  artifacts:
    - path: "public/assets/css/vote.css"
      provides: "43 color-mix calls upgraded to oklch"
      contains: "color-mix(in oklch"
    - path: "public/assets/css/design-system.css"
      provides: "Persona subtle tokens using color-mix(in oklch)"
      contains: "color-mix(in oklch"
  key_links: []
---

<objective>
Upgrade all 76 color-mix(in srgb) calls in per-page CSS files to color-mix(in oklch) for perceptually uniform color mixing. Also replace 7 rgba() persona-subtle tokens in design-system.css dark mode block with color-mix(in oklch) expressions using existing persona base color variables.

Purpose: Completes oklch migration — Phase 82 established oklch in design-system.css tokens, Phase 84 stripped hardcoded hex/rgba from page CSS, but per-page color-mix calls still use srgb interpolation.
Output: All CSS files using oklch color space uniformly.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@public/assets/css/design-system.css
</context>

<tasks>

<task type="auto">
  <name>Task 1: Replace color-mix(in srgb) with color-mix(in oklch) in all 10 per-page CSS files</name>
  <files>public/assets/css/vote.css, public/assets/css/members.css, public/assets/css/operator.css, public/assets/css/pages.css, public/assets/css/public.css, public/assets/css/meetings.css, public/assets/css/landing.css, public/assets/css/login.css, public/assets/css/postsession.css, public/assets/css/archives.css</files>
  <action>
In each of the 10 per-page CSS files, find-and-replace the string `color-mix(in srgb,` with `color-mix(in oklch,`. This is a literal string substitution — the rest of the color-mix expression (percentage, color arguments) stays identical.

Expected counts per file:
- vote.css: 43
- members.css: 12
- operator.css: 6
- pages.css: 4
- public.css: 4
- meetings.css: 2
- landing.css: 2
- login.css: 1
- postsession.css: 1
- archives.css: 1

Use sed for efficiency:
```bash
sed -i 's/color-mix(in srgb,/color-mix(in oklch,/g' public/assets/css/{vote,members,operator,pages,public,meetings,landing,login,postsession,archives}.css
```

Verify total replacements equal 76.
  </action>
  <verify>
    <automated>cd /home/user/gestion_votes_php && count=$(grep -rc "color-mix(in srgb" public/assets/css/*.css 2>/dev/null | awk -F: '{s+=$2}END{print s}') && echo "Remaining srgb: $count" && test "$count" -eq 0 && echo "PASS" || echo "FAIL"</automated>
  </verify>
  <done>Zero occurrences of "color-mix(in srgb" in any CSS file under public/assets/css/</done>
</task>

<task type="auto">
  <name>Task 2: Replace rgba() persona-subtle tokens with color-mix(in oklch) in design-system.css dark mode</name>
  <files>public/assets/css/design-system.css</files>
  <action>
In design-system.css, within the dark mode block ([data-theme="dark"]), replace the 7 rgba()-based persona-subtle tokens (lines ~702-720) with color-mix expressions referencing their corresponding persona base variable.

Replace:
```css
--persona-preparateur-subtle: rgba(129, 140, 248, 0.15);
--persona-president-subtle: rgba(251, 191, 36, 0.15);
--persona-operateur-subtle: rgba(34, 211, 238, 0.15);
--persona-votant-subtle: rgba(74, 222, 128, 0.15);
--persona-postsession-subtle: rgba(167, 139, 250, 0.15);
--persona-auditeur-subtle: rgba(248, 113, 113, 0.15);
--persona-admin-subtle: rgba(148, 163, 184, 0.15);
```

With:
```css
--persona-preparateur-subtle: color-mix(in oklch, var(--persona-preparateur) 15%, transparent);
--persona-president-subtle: color-mix(in oklch, var(--persona-president) 15%, transparent);
--persona-operateur-subtle: color-mix(in oklch, var(--persona-operateur) 15%, transparent);
--persona-votant-subtle: color-mix(in oklch, var(--persona-votant) 15%, transparent);
--persona-postsession-subtle: color-mix(in oklch, var(--persona-postsession) 15%, transparent);
--persona-auditeur-subtle: color-mix(in oklch, var(--persona-auditeur) 15%, transparent);
--persona-admin-subtle: color-mix(in oklch, var(--persona-admin) 15%, transparent);
```

This makes persona-subtle tokens derive from their persona base color dynamically, matching the 15% opacity pattern of the original rgba() values while staying in oklch color space.
  </action>
  <verify>
    <automated>cd /home/user/gestion_votes_php && count=$(grep -c "rgba(" public/assets/css/design-system.css) && echo "Remaining rgba: $count" && test "$count" -eq 0 && echo "PASS" || echo "FAIL: $count rgba() calls remain"</automated>
  </verify>
  <done>Zero rgba() calls in design-system.css. All 7 persona-subtle dark mode tokens use color-mix(in oklch, var(--persona-X) 15%, transparent)</done>
</task>

</tasks>

<verification>
```bash
# No srgb color-mix anywhere in CSS
grep -rc "color-mix(in srgb" public/assets/css/*.css | grep -v ":0$" && echo "FAIL: srgb remains" || echo "PASS: no srgb"

# No rgba in design-system.css
grep -c "rgba(" public/assets/css/design-system.css && echo "FAIL: rgba remains" || echo "PASS: no rgba"

# oklch color-mix count should be >= 76 (page files) + 7 (persona) = 83+
total=$(grep -rc "color-mix(in oklch" public/assets/css/*.css | awk -F: '{s+=$2}END{print s}')
echo "Total oklch color-mix calls: $total"
```
</verification>

<success_criteria>
- Zero "color-mix(in srgb" occurrences across all CSS files
- Zero "rgba(" occurrences in design-system.css
- All color mixing uses oklch color space consistently
</success_criteria>

<output>
After completion, create `.planning/quick/260403-gtt-upgrade-color-mix-srgb-to-oklch-in-page-/260403-gtt-SUMMARY.md`
</output>
