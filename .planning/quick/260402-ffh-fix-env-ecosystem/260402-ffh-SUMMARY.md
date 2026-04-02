---
phase: 260402-ffh
plan: 01
type: quick
subsystem: configuration
tags: [env, security, devops, tooling]
dependency_graph:
  requires: []
  provides: [validate-env CLI tool, quote-safe EnvProvider, APP_URL production guard]
  affects: [app/Core/Providers/EnvProvider.php, app/Core/Application.php, .env.example, .env.production.example, bin/validate-env]
tech_stack:
  added: []
  patterns: [boot-time warning via error_log(), CLI diagnostic script with ANSI TTY detection]
key_files:
  created:
    - bin/validate-env
  modified:
    - .env.example
    - .env.production.example
    - app/Core/Providers/EnvProvider.php
    - app/Core/Application.php
decisions:
  - validate-env uses global $IS_TTY (not closure use-capture) because named functions cannot use `use` keyword
  - APP_URL warning uses error_log() only — no throw — misconfiguration is recoverable
  - bin/validate-env reads .env for display only; does not call putenv() to avoid side effects
metrics:
  duration: ~15 minutes
  completed: 2026-04-01
  tasks_completed: 2
  tasks_total: 2
  files_modified: 5
---

# Quick Task 260402-ffh: Fix .env Ecosystem — Summary

**One-liner:** Rewrote both env example files (removed phantom vars, added DB_DSN, all production hints), added quote-stripping to EnvProvider, APP_URL boot warning to Application, and shipped `bin/validate-env` as a colored diagnostic CLI tool.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Rewrite .env.example and .env.production.example | 07877b99 | .env.example, .env.production.example |
| 2 | EnvProvider quote fix + Application APP_URL warning + bin/validate-env | 461b18d5 | app/Core/Providers/EnvProvider.php, app/Core/Application.php, bin/validate-env |

## What Was Done

### Task 1 — Example files rewrite

**.env.example changes:**
- Removed phantom vars: `CSRF_LIFETIME`, `RATE_LIMIT_REQUESTS`, `RATE_LIMIT_PERIOD` (not read by any app code)
- Removed Dockerfile-hardcoded vars: `STORAGE_PATH`, `DOMPDF_FONT_DIR`, `DOMPDF_CACHE_DIR`
- Collapsed "Stockage & PDF" to "Stockage" section with only `AGVOTE_UPLOAD_DIR`
- Added `[PRODUCTION]` hints to all vars that lacked them: `MAIL_TLS`, `MAIL_TIMEOUT`, `APP_LOGIN_MAX_ATTEMPTS`, `APP_LOGIN_WINDOW`, `EMAIL_TRACKING_ENABLED`, `PROXY_MAX_PER_RECEIVER`, `PUSH_ENABLED`, `API_KEY_*`
- Added footer: `# Pour auditer votre configuration : php bin/validate-env`

**.env.production.example changes:**
- Replaced `DB_HOST` / `DB_PORT` / `DB_DATABASE` with `DB_DSN=pgsql:host=<db-host>;port=5432;dbname=vote_app;sslmode=require`
- Added all missing vars: `APP_URL`, `APP_LOGIN_MAX_ATTEMPTS`, `APP_LOGIN_WINDOW`, `MAIL_TLS`, `MAIL_TIMEOUT`, `REDIS_DATABASE`, `REDIS_PREFIX`, `API_KEY_*`, `EMAIL_TRACKING_ENABLED`, `PROXY_MAX_PER_RECEIVER`, `AGVOTE_UPLOAD_DIR`
- Added monitoring block (commented), same footer

### Task 2 — Code fixes and new tool

**EnvProvider.php:**
- Added `$val = trim($val, '"\'');` immediately after `$val = trim($val);`
- Handles `DB_PASS="my password"` → `my password`, `APP_SECRET='secret'` → `secret`

**Application.php:**
- Added APP_URL boot-time warning in `loadConfig()` (inside `$isProduction` block, after auth checks, before DEFAULT_TENANT_ID)
- Fires `error_log('[CONFIG WARNING] APP_URL...')` when APP_URL is empty, contains localhost, or contains 127.0.0.1
- Does NOT throw — warning only

**bin/validate-env (new):**
- Shebang `#!/usr/bin/env php`, executable, exit 1 on missing required vars
- Loads .env for display only (no putenv side-effects)
- 11 sections covering all vars documented in .env.example
- Status levels: `[MISSING]` (red, required), `[WARN]` (yellow, security/cors/storage), `[PLACEHOLDER]` (yellow), `[OK]` (green), `[info]` (plain, optional vars)
- Redacts secrets (APP_SECRET, DB_PASS, REDIS_PASSWORD, MAIL_PASS, API_KEY_*, MONITOR_WEBHOOK_URL) to first 4 chars + `****`
- ANSI detection via `posix_isatty()` — strips colors when not a TTY
- Special production checks: `APP_DEBUG=1`, `LOAD_SEED_DATA=1`, `APP_URL` localhost

## Verification Results

| Check | Result |
|-------|--------|
| php -l EnvProvider.php | No syntax errors |
| php -l Application.php | No syntax errors |
| php -l bin/validate-env | No syntax errors |
| php bin/validate-env | Runs, prints report, exits 1 (APP_URL missing in dev .env — expected) |
| grep DB_DSN .env.production.example | 1 match |
| grep DB_HOST .env.production.example | 0 matches |
| grep CSRF_LIFETIME .env.example | 0 matches |
| PasswordResetServiceTest smoke test | 7 tests, 13 assertions — OK |

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Invalid `use` in named function**
- **Found during:** Task 2 — first syntax check of bin/validate-env
- **Issue:** PHP named functions cannot use `use ($var)` capture syntax — only closures can. Used `use ($isTty)` in `colour()`, `green()`, `yellow()`, `red()`.
- **Fix:** Converted `$isTty` to `$IS_TTY` global; added `global $IS_TTY;` inside `colour()`. Helper functions call `colour()` with no changes.
- **Files modified:** bin/validate-env
- **Commit:** 461b18d5 (included in task commit)

## Self-Check

Files exist:
- /home/user/gestion_votes_php/.env.example — FOUND
- /home/user/gestion_votes_php/.env.production.example — FOUND
- /home/user/gestion_votes_php/app/Core/Providers/EnvProvider.php — FOUND
- /home/user/gestion_votes_php/app/Core/Application.php — FOUND
- /home/user/gestion_votes_php/bin/validate-env — FOUND

Commits exist:
- 07877b99 — FOUND
- 461b18d5 — FOUND

## Self-Check: PASSED
