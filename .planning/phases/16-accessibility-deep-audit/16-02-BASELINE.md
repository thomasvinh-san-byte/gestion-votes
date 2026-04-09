# Phase 16 — Axe Baseline (before fixes)

**Run date:** 2026-04-09
**Runner:** `cd tests/e2e && npx playwright test specs/accessibility.spec.js --project=chromium --reporter=line`
**Pages covered:** 22 (21 HTMX + login) — configured
**Pages actually scanned:** 0 (environment blocker, see below)
**Disabled rules:** color-contrast (D-04, structural runner only)

## Summary

- Tests passing: 0/26
- Tests failing: 26/26 (22 axe matrix + 4 legacy unit tests)
- Unique rule-ids with critical/serious impact: **UNKNOWN — axe never executed**
- Total offending nodes: **UNKNOWN — axe never executed**

## Environment blocker — browser fails to launch

**Every test failed at the Playwright browser-launch stage with identical error:**

```
browserType.launch: Target page, context or browser has been closed
Browser logs:
[pid=XXXXXX][err] /home/user/.cache/ms-playwright/chromium_headless_shell-1217/chrome-headless-shell-linux64/chrome-headless-shell:
  error while loading shared libraries: libatk-1.0.so.0: cannot open shared object file: No such file or directory
```

**Diagnostic confirmed:**

- Both chromium binaries (`chromium-1217/chrome-linux64/chrome` and `chromium_headless_shell-1217/chrome-headless-shell-linux64/chrome-headless-shell`) exit 127 with the same `libatk-1.0.so.0` missing-library error.
- `find / -name "libatk-1.0*"` returns zero hits — the shared library is entirely absent from the host filesystem.
- `dpkg -l libatk1.0-0` → package not installed.
- `sudo -n apt-get install …` → `a password is required` (no passwordless sudo).
- `npx playwright install-deps chromium` → `sudo: a terminal is required` — also requires elevation.

**The required system packages that must be installed as root** (from `npx playwright install-deps chromium`):

```
libatk1.0-0 libatk-bridge2.0-0 libcups2 libxkbcommon0 libxcomposite1
libxdamage1 libxfixes3 libxrandr2 libgbm1 libasound2t64 libnss3
```

On Debian/Ubuntu:

```bash
sudo apt-get update
sudo apt-get install -y libatk1.0-0 libatk-bridge2.0-0 libcups2 \
    libxkbcommon0 libxcomposite1 libxdamage1 libxfixes3 libxrandr2 \
    libgbm1 libasound2t64 libnss3
# or, preferred, as the user who ran `npx playwright install`:
npx playwright install-deps chromium
```

**Prior context:** Phase 15 (cross-browser matrix, committed 3 days ago as `c255c846`) ran Playwright successfully against chromium — so the environment has regressed since then. Likely cause: a host-level package cleanup or a fresh container/VM without the dependency layer installed.

## Violations grouped by rule-id (fix order = descending node count)

**N/A — no axe assertions reached. Cannot enumerate until the browser launches.**

This section will be populated after the environment is fixed and the runner is re-executed. The plan was written assuming a working browser stack.

## Per-page failure summary

| Page                        | Passing | Failing rules                                  |
| --------------------------- | ------- | ---------------------------------------------- |
| /login.html                 | ❌      | (browser launch failure — libatk-1.0.so.0)     |
| /dashboard.htmx.html        | ❌      | (browser launch failure)                       |
| /meetings.htmx.html         | ❌      | (browser launch failure)                       |
| /members.htmx.html          | ❌      | (browser launch failure)                       |
| /operator.htmx.html         | ❌      | (browser launch failure)                       |
| /settings.htmx.html         | ❌      | (browser launch failure)                       |
| /audit.htmx.html            | ❌      | (browser launch failure)                       |
| /admin.htmx.html            | ❌      | (browser launch failure)                       |
| /analytics.htmx.html        | ❌      | (browser launch failure)                       |
| /archives.htmx.html         | ❌      | (browser launch failure)                       |
| /docs.htmx.html             | ❌      | (browser launch failure)                       |
| /email-templates.htmx.html  | ❌      | (browser launch failure)                       |
| /help.htmx.html             | ❌      | (browser launch failure)                       |
| /hub.htmx.html              | ❌      | (browser launch failure)                       |
| /postsession.htmx.html      | ❌      | (browser launch failure)                       |
| /public.htmx.html           | ❌      | (browser launch failure)                       |
| /report.htmx.html           | ❌      | (browser launch failure)                       |
| /trust.htmx.html            | ❌      | (browser launch failure)                       |
| /users.htmx.html            | ❌      | (browser launch failure)                       |
| /validate.htmx.html         | ❌      | (browser launch failure)                       |
| /vote.htmx.html             | ❌      | (browser launch failure)                       |
| /wizard.htmx.html           | ❌      | (browser launch failure)                       |

## Open questions raised by baseline

- **Environment provenance**: What changed between phase 15 (2026-04-06, passing cross-browser matrix) and now? Was a new dev container spun up? Was a cleanup removing GTK/ATK runtime libraries performed?
- **Unblock path**: Requires operator with root/sudo to run `npx playwright install-deps chromium` or the equivalent `apt-get install` command listed above. No other blocker exists — the Playwright binary itself, test runner, auth fixtures and PAGES array are all intact from 16-01.
- **Once unblocked**: Re-run the same command (`cd tests/e2e && timeout 300 npx playwright test specs/accessibility.spec.js --project=chromium --reporter=line 2>&1 | tee /tmp/16-02-axe-baseline.txt`). Because 16-01 already applied 6 WIP seed fixes (operator live regions, settings aria-labels, SettingsController unwrap, strict-mode `.first()` fix) the first real run should produce a meaningful violation inventory that plan 16-02's Task 2 can batch-fix.

## Proposed batch-fix order

**Blocked** — cannot be derived without baseline data. Per plan rules ("Baseline run confirms actual set" — interfaces block of 16-02-PLAN) we must not pre-invent the list. When the environment is repaired and the run produces real violations, this section will be rewritten with the empirical rule-id frequency list.

## Raw output

Full baseline log (26 failures, all identical cause) preserved at `/tmp/16-02-axe-baseline.txt` for the session. It is 52 occurrences of the `libatk-1.0.so.0` error across the 26 test invocations (one per test case × 2 retry attempts on some).
