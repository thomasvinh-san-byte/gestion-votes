---
phase: 16-accessibility-deep-audit
plan: 04
subsystem: accessibility/tests
tags: [a11y, contrast, axe-core, playwright, wcag-aa]
requires: [16-01]
provides:
  - "Manual one-shot contrast audit runner"
  - ".planning/v1.3-CONTRAST-AUDIT.json raw data for 16-05 report"
affects: [tests/e2e/specs]
tech_stack:
  added: []
  patterns:
    - "Env-gated spec (process.env.CONTRAST_AUDIT) — file lives in specs/ but default run skips"
    - "AxeBuilder withRules(['color-contrast']) whitelist to isolate contrast rule"
key_files:
  created:
    - tests/e2e/specs/contrast-audit.spec.js
    - .planning/v1.3-CONTRAST-AUDIT.json
  modified: []
decisions:
  - "PAGES array duplicated from accessibility.spec.js (per plan) — keep in sync manually"
  - "fg/bg/ratio/fontSize/fontWeight extracted from node.any[0].data (axe 4.x stable shape)"
  - "Spec ran in Docker container (mcr.microsoft.com/playwright:v1.59.1-jammy) — host chromium missing system libs (libatk-1.0.so.0)"
metrics:
  tasks: 1
  duration: "~10m (including docker image pull)"
  completed: 2026-04-09
requirements_satisfied: [A11Y-03]
---

# Phase 16 Plan 04: Contrast Audit Runner Summary

One-shot, env-gated Playwright spec that runs axe's `color-contrast` rule across all 22 HTMX pages and dumps the raw violation data to `.planning/v1.3-CONTRAST-AUDIT.json` for consumption by the report generator (plan 16-05).

## What was built

**`tests/e2e/specs/contrast-audit.spec.js`**
- Gated by `test.skip(!process.env.CONTRAST_AUDIT, '...')` — default CI (`bin/test-e2e.sh`) does not execute it
- Serial single-test loop over 22 pages (duplicated PAGES array from `accessibility.spec.js` with a sync-reminder comment)
- Per page: login (if required) → goto → wait for requiredLocator → `AxeBuilder.withTags(['wcag2aa']).withRules(['color-contrast']).analyze()`
- Aggregates into a single JSON object with per-page `violationCount` and up to 20 node details per violation
- Writes `.planning/v1.3-CONTRAST-AUDIT.json` via `fs.writeFileSync` at end of the test
- 5-minute test timeout (`test.setTimeout(300_000)`)

**`.planning/v1.3-CONTRAST-AUDIT.json`** — initial snapshot, 22 page entries, no errors.

## How to run

```bash
# One-shot manual run (inside Docker test runner — host chromium has missing libs):
PROJECT_NAME=$(basename $(pwd) | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9_-]/_/g')
docker run --rm \
  --network "${PROJECT_NAME}_backend" \
  --volume "$(pwd):/work" \
  --volume "${PROJECT_NAME}_tests-node-modules:/work/tests/e2e/node_modules" \
  --workdir /work/tests/e2e \
  --user "$(id -u):$(id -g)" \
  -e IN_DOCKER=true -e BASE_URL=http://agvote:8080 \
  -e REDIS_HOST=redis -e REDIS_PORT=6379 -e REDIS_PASSWORD="agvote-redis-dev" \
  -e CONTRAST_AUDIT=1 \
  mcr.microsoft.com/playwright:v1.59.1-jammy \
  bash -lc "npx playwright test --project=chromium specs/contrast-audit.spec.js --reporter=line"
```

Default run (skip verification):
```bash
cd tests/e2e && timeout 30 npx playwright test specs/contrast-audit.spec.js --project=chromium --reporter=line
# → "1 skipped"
```

## Findings — initial snapshot (2026-04-09)

All 22 pages reported **exactly one `color-contrast` violation** (axe rule id, impact `serious`). The node counts vary from 2 (public projection) to 20 (dashboard, meetings, wizard, etc.) — many hit axe's internal node cap for a single violation.

| Page                         | Violations | Nodes | Worst ratio |
| ---------------------------- | ---------- | ----- | ----------- |
| /login.html                  | 1          | 4     | 2.99        |
| /dashboard.htmx.html         | 1          | 20    | 2.36        |
| /meetings.htmx.html          | 1          | 20    | 2.36        |
| /members.htmx.html           | 1          | 20    | 2.06        |
| /operator.htmx.html          | 1          | 20    | 2.36        |
| /settings.htmx.html          | 1          | 7     | 2.36        |
| /audit.htmx.html             | 1          | 17    | 2.02        |
| /admin.htmx.html             | 1          | 12    | 2.36        |
| /analytics.htmx.html         | 1          | 20    | 2.36        |
| /archives.htmx.html          | 1          | 13    | 2.09        |
| /docs.htmx.html              | 1          | 17    | 2.27        |
| /email-templates.htmx.html   | 1          | 7     | 2.36        |
| /help.htmx.html              | 1          | 20    | 2.36        |
| /hub.htmx.html               | 1          | 19    | 2.36        |
| /postsession.htmx.html       | 1          | 16    | 2.36        |
| /public.htmx.html            | 1          | 2     | 2.36        |
| /report.htmx.html            | 1          | 13    | 2.02        |
| /trust.htmx.html             | 1          | 9     | 2.27        |
| /users.htmx.html             | 1          | 18    | 2.21        |
| /validate.htmx.html          | 1          | 17    | 2.36        |
| /vote.htmx.html              | 1          | 5     | 2.36        |
| /wizard.htmx.html            | 1          | 20    | 1.83        |

**Total:** 22 violations, 0 page errors.

**Recurring offender (sample from dashboard):**
- target `.search-trigger` — fg `#988d7a` on bg `#e0dbcf` — ratio **2.36** (needs ≥ 4.5 for WCAG AA)
- Low-contrast muted-foreground tokens on warm surfaces appear across all layout shells. The root cause is almost certainly a small set of design tokens (`--color-text-muted`, muted button foregrounds) shared across the entire app.

**Worst ratio observed:** 1.83 on `/wizard.htmx.html` (below even WCAG AA large-text 3:1 threshold).

## Notes for plan 16-05 (report generator)

- The JSON is already de-duplicable: since most pages share the same offending tokens, the report should group by `(fgColor, bgColor, target pattern)` rather than listing 200+ nodes verbatim.
- Consider a "root cause" section that maps low-ratio color pairs back to the design-token layer (Phase 14 / Phase 84 tokens) so fixes land in one place.
- No pages errored during the run — report can state 100% coverage.

## Deviations from Plan

**[Rule 3 - Blocking] Host chromium missing system libraries**
- **Found during:** Task 1, first CONTRAST_AUDIT=1 run attempt
- **Issue:** `chrome-headless-shell` on host fails with `libatk-1.0.so.0: cannot open shared object file` — also affects full chromium binary (`libatk`, `libatk-bridge`, `libcups`, `libasound`, `libcairo`, `libpango`, `libXdamage`, `libatspi` all missing). Default-skip run passed only because no browser launched.
- **Fix:** Ran the spec inside the project's standard Playwright Docker image (`mcr.microsoft.com/playwright:v1.59.1-jammy`) via the same pattern as `bin/test-e2e.sh`, with `-e CONTRAST_AUDIT=1` added. Docker image has all required libs. Documented the exact command in "How to run" above.
- **Scope:** Did not modify `bin/test-e2e.sh` to forward arbitrary env vars — that's out of scope. The plan document can link to the command above. Logging as deferred improvement.

**Deferred improvement (not fixed, logged here):**
- `bin/test-e2e.sh` could grow an `-e EXTRA_ENV=VAR=val` pass-through or a dedicated `CONTRAST_AUDIT` forward so manual runs don't need the raw docker incantation. Out of scope for 16-04.

## Self-Check: PASSED

- `tests/e2e/specs/contrast-audit.spec.js` — FOUND
- `.planning/v1.3-CONTRAST-AUDIT.json` — FOUND, 22 pages, valid JSON
- grep `CONTRAST_AUDIT` in spec — FOUND
- grep `withRules(['color-contrast'])` in spec — FOUND
- grep `writeFileSync` in spec — FOUND
- grep `v1.3-CONTRAST-AUDIT.json` in spec — FOUND
- Commit `1fefde78` — FOUND (feat(16-04): add one-shot contrast audit spec + initial v1.3 contrast snapshot)
- Default run (no env) reports `1 skipped` — VERIFIED
- CONTRAST_AUDIT=1 run reports `1 passed (17.3s)` and wrote JSON — VERIFIED
