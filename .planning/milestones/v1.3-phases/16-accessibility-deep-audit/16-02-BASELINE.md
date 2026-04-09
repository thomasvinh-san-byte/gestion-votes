# Phase 16 — Axe Baseline (before fixes)

**Run date:** 2026-04-09
**Runner:** `bin/test-e2e.sh specs/accessibility.spec.js --reporter=line` (Docker, chromium)
**Pages covered:** 22 (21 HTMX + login)
**Disabled rules:** color-contrast (D-04, structural runner only)

## Summary

- Tests passing: **18/22** axe matrix (4 unit-style legacy tests pass; counted separately)
- Tests failing: **4/22** axe matrix
- Unique rule-ids with critical/serious impact: **5**
- Total offending nodes: **47**

## Environment note — stale image discovery

The baseline run executed against the running `agvote-app` container, which is built from a baked image (no bind mount on `/var/www/public`). The image hash predates the 16-01 commit (`357d84c0 fix(16-01): seed a11y WIP …`) that added `role="status"`/`role="progressbar"` to the operator live regions.

As a consequence, the baseline reflects partly-stale source for `operator.htmx.html` and `public.htmx.html`. Because `docker compose up --build app` failed (apk virtual deps unreachable in this sandbox), this plan mounts the host `public/` directory read-only into the container via `docker-compose.override.yml` so the next run picks up the up-to-date HTML and the 16-02 batch fixes. The override is removed after Task 2 completes.

## Violations grouped by rule-id (fix order = descending node count)

### 1. button-name — critical — 40 nodes on /admin.htmx.html (1 page)
Buttons must have discernible text.
- /admin.htmx.html: 40 nodes
  - `.btn-edit-user`, `.btn-password-user`, `.btn-toggle-user`, `.btn-delete-user` rows in user list — icon-only buttons rendered by `users-admin.js` without `aria-label`.
  - Pattern: 4 action buttons × 10 user rows = 40 nodes. Single fix in the JS row template.

### 2. aria-prohibited-attr — serious — 4 nodes on 3 pages
Elements must only use permitted ARIA attributes (`aria-label` not allowed on roleless elements / generic `<span>`/`<div>`).
- /operator.htmx.html: 2 nodes — STALE-IMAGE FALSE POSITIVE (16-01 already added `role="status"` and `role="progressbar"`; bind mount unblocks this)
  - `span.op-live-dot[aria-label="Session en cours"]`
  - `#opResolutionProgress`
- /public.htmx.html: 1 node — `#resolutionTracker` `<div hidden aria-label="Progression des résolutions">` needs `role="status"`
- /vote.htmx.html: 1 node — `#voteProgressDots` `<div hidden aria-label="Progression des résolutions">` needs `role="status"`

### 3. aria-input-field-name — serious — 2 nodes on /vote.htmx.html (1 page)
ARIA input fields must have an accessible name.
- /vote.htmx.html: 2 nodes
  - `#meetingSelect .select-trigger[role="combobox"]`
  - `#memberSelect .select-trigger[role="combobox"]`
  - Combobox triggers need `aria-label` or `aria-labelledby`.

### 4. select-name — critical — 1 node on /admin.htmx.html (1 page)
Select element must have an accessible name.
- /admin.htmx.html: 1 node
  - `<select id="newRole" class="form-input">` — needs `<label for="newRole">` or `aria-label`.

### 5. scrollable-region-focusable — serious — 1 node on /vote.htmx.html (1 page)
Scrollable region must have keyboard access.
- /vote.htmx.html: 1 node
  - `<main class="vote-main" id="main-content" role="main">` — empty `<main>` populated by JS later; either ensure it is not scrollable until populated, or add `tabindex="0"`.

## Per-page failure summary

| Page                         | Passing | Failing rules                                                   |
| ---------------------------- | ------- | --------------------------------------------------------------- |
| /login.html                  | OK      | —                                                               |
| /dashboard.htmx.html         | OK      | —                                                               |
| /meetings.htmx.html          | OK      | —                                                               |
| /members.htmx.html           | OK      | —                                                               |
| /operator.htmx.html          | FAIL    | aria-prohibited-attr (stale image — already fixed in HTML)      |
| /settings.htmx.html          | OK      | —                                                               |
| /audit.htmx.html             | OK      | —                                                               |
| /admin.htmx.html             | FAIL    | button-name (40), select-name (1)                               |
| /analytics.htmx.html         | OK      | —                                                               |
| /archives.htmx.html          | OK      | —                                                               |
| /docs.htmx.html              | OK      | —                                                               |
| /email-templates.htmx.html   | OK      | —                                                               |
| /help.htmx.html              | OK      | —                                                               |
| /hub.htmx.html               | OK      | —                                                               |
| /postsession.htmx.html       | OK      | —                                                               |
| /public.htmx.html            | FAIL    | aria-prohibited-attr (`#resolutionTracker`)                     |
| /report.htmx.html            | OK      | —                                                               |
| /trust.htmx.html             | OK      | —                                                               |
| /users.htmx.html             | OK      | —                                                               |
| /validate.htmx.html          | OK      | —                                                               |
| /vote.htmx.html              | FAIL    | aria-prohibited-attr, aria-input-field-name, scrollable-region  |
| /wizard.htmx.html            | OK      | —                                                               |

## Proposed batch-fix order

1. **button-name (40 nodes)** — fix `users-admin.js` row template: add French `aria-label` to edit/password/toggle/delete buttons.
2. **aria-prohibited-attr (4 nodes)** — add `role="status"` to `#resolutionTracker` (public.htmx.html) and `#voteProgressDots` (vote.htmx.html). Operator nodes already fixed; bind mount in place so next run will pick them up.
3. **aria-input-field-name (2 nodes)** — add `aria-label` (French) to `#meetingSelect` and `#memberSelect` `.select-trigger[role="combobox"]` in vote.htmx.html.
4. **select-name (1 node)** — add `aria-label` to `#newRole` `<select>` in admin.htmx.html (or wrap in `<label>`).
5. **scrollable-region-focusable (1 node)** — add `tabindex="0"` to `<main id="main-content">` in vote.htmx.html (or remove the scrollable property until JS populates it).

## Raw output

Full baseline log preserved at `/tmp/16-02-axe-baseline.txt` for the session.
