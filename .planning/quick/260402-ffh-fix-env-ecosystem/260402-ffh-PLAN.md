---
phase: 260402-ffh
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - .env.example
  - .env.production.example
  - app/Core/Providers/EnvProvider.php
  - app/Core/Application.php
  - bin/validate-env
autonomous: true
requirements: [ENV-01, ENV-02, ENV-03, ENV-04, ENV-05, ENV-06]

must_haves:
  truths:
    - ".env.example is the single source of truth — all app-read vars present, no phantom vars"
    - ".env.production.example uses DB_DSN (not DB_HOST/DB_DATABASE) and matches .env.example var-for-var"
    - "EnvProvider strips surrounding quotes from values (single and double)"
    - "Application::boot() warns at startup when APP_URL is empty or contains localhost in production"
    - "bin/validate-env prints a colored OK/MISSING/WARNING report for every documented var"
  artifacts:
    - path: ".env.example"
      provides: "Developer reference — all vars grouped with inline production hints"
    - path: ".env.production.example"
      provides: "Ops checklist — production values only, DB_DSN-based"
    - path: "app/Core/Providers/EnvProvider.php"
      provides: "Quote-stripping after trim()"
    - path: "app/Core/Application.php"
      provides: "APP_URL boot-time warning in production"
    - path: "bin/validate-env"
      provides: "CLI tool — checks all documented vars, colored output"
  key_links:
    - from: "EnvProvider::load()"
      to: "putenv()"
      via: "trim($val, '\"\\'')"
    - from: "Application::loadConfig()"
      to: "error_log()"
      via: "APP_URL localhost/empty warning"
---

<objective>
Fix the .env ecosystem definitively: rewrite both example files, strip quotes in EnvProvider,
add an APP_URL boot-time warning in Application, and ship bin/validate-env as a diagnostic tool.

Purpose: Eliminate six documented gaps — phantom vars in .env.example, DB_HOST/DB_DATABASE
mismatch in .env.production.example, quoted values passed raw to PDO, silent APP_URL
misconfiguration, and no CLI audit tool.

Output: Two rewritten example files, a two-line EnvProvider fix, one loadConfig() warning block,
and a new bin/validate-env PHP script.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/PROJECT.md
@.planning/STATE.md
@app/Core/Providers/EnvProvider.php
@app/Core/Application.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Rewrite .env.example and .env.production.example</name>
  <files>.env.example, .env.production.example</files>
  <action>
NOTE: .env is already excluded from git via .gitignore and is NOT tracked. No git rm needed.

--- Rewrite .env.example ---

Keep every var currently in the file with its grouping and [PRODUCTION] hints. Apply:

REMOVE entirely (no app reads, reserved in comments — drop the vars AND the NOTE comment about
them since it creates confusion):
  - CSRF_LIFETIME
  - RATE_LIMIT_REQUESTS
  - RATE_LIMIT_PERIOD

REMOVE entirely (hardcoded in Dockerfile):
  - STORAGE_PATH
  - DOMPDF_FONT_DIR
  - DOMPDF_CACHE_DIR

Trim the "Stockage & PDF" section to just AGVOTE_UPLOAD_DIR under a "Stockage" header. The
paragraph "Répertoire de travail : /tmp/ag-vote (créé par le Dockerfile)" can stay as a comment.

For every var that currently has NO [PRODUCTION] hint, add one. Affected vars:
  - MAIL_TLS: # [PRODUCTION] : MAIL_TLS=tls (port 465 TLS-first) ou starttls (port 587)
  - MAIL_TIMEOUT: # [PRODUCTION] : MAIL_TIMEOUT=30
  - APP_LOGIN_MAX_ATTEMPTS: # [PRODUCTION] : garder à 5 ou moins
  - APP_LOGIN_WINDOW: # [PRODUCTION] : 300 recommandé
  - EMAIL_TRACKING_ENABLED: # [PRODUCTION] : mettre à 0 si RGPD strict
  - PROXY_MAX_PER_RECEIVER: # [PRODUCTION] : ajuster selon les statuts de l'association
  - PUSH_ENABLED: # [PRODUCTION] : 1 (Redis requis)
  - API_KEY_OPERATOR / API_KEY_TRUST / API_KEY_ADMIN: # [PRODUCTION] : générer si accès API requis

Add this footer comment as the very last line:
  # Pour auditer votre configuration : php bin/validate-env

--- Rewrite .env.production.example ---

This file must be a 100% ops-ready checklist with production values only (no dev defaults).
Every var in .env.example must appear here. Organise with the same section headers.

Critical fix — replace the old DB_HOST + DB_PORT + DB_DATABASE block with:
  DB_DSN=pgsql:host=<db-host>;port=5432;dbname=vote_app;sslmode=require
  DB_USER=vote_app
  DB_PASS=<mot-de-passe-fort-64-caracteres>

Vars currently MISSING from .env.production.example that must be added:
  APP_URL=https://<votre-domaine.fr>
  APP_LOGIN_MAX_ATTEMPTS=5
  APP_LOGIN_WINDOW=300
  MAIL_TLS=starttls
  MAIL_TIMEOUT=30
  REDIS_DATABASE=0
  REDIS_PREFIX=agvote:
  API_KEY_OPERATOR=
  API_KEY_TRUST=
  API_KEY_ADMIN=
  EMAIL_TRACKING_ENABLED=0
  PROXY_MAX_PER_RECEIVER=3
  AGVOTE_UPLOAD_DIR=/var/agvote/uploads

Add the MONITOR_* block (commented out, same as .env.example).

Add same footer:
  # Pour auditer votre configuration : php bin/validate-env
  </action>
  <verify>
    Run: php bin/validate-env (after Task 2 creates it)
    Quick pre-check: grep for DB_HOST in .env.production.example must return nothing;
    grep for DB_DSN must return a match.
    grep for CSRF_LIFETIME in .env.example must return nothing.
  </verify>
  <done>
    .env.example contains no phantom vars (CSRF_LIFETIME, RATE_LIMIT_REQUESTS, RATE_LIMIT_PERIOD,
    STORAGE_PATH, DOMPDF_FONT_DIR, DOMPDF_CACHE_DIR). .env.production.example contains DB_DSN
    and no DB_HOST or DB_DATABASE. Both files end with the validate-env footer comment.
  </done>
</task>

<task type="auto">
  <name>Task 2: EnvProvider quote fix + Application APP_URL warning + bin/validate-env</name>
  <files>app/Core/Providers/EnvProvider.php, app/Core/Application.php, bin/validate-env</files>
  <action>
--- EnvProvider.php ---

Current code after explode:
    $key = trim($key);
    $val = trim($val);

Add a single line immediately after `$val = trim($val);`:
    $val = trim($val, '"\'');

This handles values like:
    DB_PASS="my password"   → my password
    APP_SECRET='secret'     → secret
    DB_DSN=pgsql:host=db    → pgsql:host=db  (no change — no surrounding quotes)

Verify syntax: php -l app/Core/Providers/EnvProvider.php

--- Application.php ---

Inside loadConfig(), after the existing $isProduction block that validates APP_SECRET and
APP_AUTH_ENABLED (lines ~150-181), add a new warning block:

    // APP_URL warning in production
    if ($isProduction) {
        $appUrl = (string) (getenv('APP_URL') ?: (self::$config['app_url'] ?? ''));
        if ($appUrl === '' || str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1')) {
            error_log('[CONFIG WARNING] APP_URL is not set or points to localhost in production. '
                . 'Set APP_URL to your public URL (e.g. https://vote.mondomaine.fr). '
                . 'Email links and CORS will be incorrect until this is fixed.');
        }
    }

Place this block AFTER the $authEnabled check and BEFORE the DEFAULT_TENANT_ID block.
Do NOT throw — log only (warning, not fatal).

Verify syntax: php -l app/Core/Application.php

--- bin/validate-env ---

Create /home/user/gestion_votes_php/bin/validate-env as an executable PHP CLI script.

The script must:
1. Load .env from dirname(__DIR__).'/.env' if it exists (same logic as EnvProvider but
   for display only — do not mutate putenv).
2. Define the canonical var list grouped by section. Use this exact list (every var
   documented in .env.example):

   REQUIRED (missing = error, red):
     APP_ENV, APP_SECRET, APP_URL,
     DB_DSN, DB_USER, DB_PASS,
     DEFAULT_TENANT_ID

   SECURITY (missing = warning, yellow):
     APP_AUTH_ENABLED, CSRF_ENABLED, RATE_LIMIT_ENABLED,
     LOAD_SEED_DATA, APP_LOGIN_MAX_ATTEMPTS, APP_LOGIN_WINDOW

   EMAIL (missing = info, not an error):
     MAIL_HOST, MAIL_PORT, MAIL_USER, MAIL_PASS,
     MAIL_FROM, MAIL_FROM_NAME, MAIL_TLS, MAIL_TIMEOUT

   REDIS (missing = info):
     REDIS_HOST, REDIS_PORT, REDIS_PASSWORD, REDIS_DATABASE, REDIS_PREFIX

   FEATURES (missing = info):
     PUSH_ENABLED, EMAIL_TRACKING_ENABLED, PROXY_MAX_PER_RECEIVER

   API_KEYS (missing = info):
     API_KEY_OPERATOR, API_KEY_TRUST, API_KEY_ADMIN

   CORS (missing = warning):
     CORS_ALLOWED_ORIGINS

   STORAGE (missing = warning):
     AGVOTE_UPLOAD_DIR

   MONITORING (missing = info):
     MONITOR_ALERT_EMAILS, MONITOR_WEBHOOK_URL,
     MONITOR_AUTH_FAILURES_THRESHOLD, MONITOR_DB_LATENCY_MS,
     MONITOR_DISK_FREE_PCT, MONITOR_EMAIL_BACKLOG

3. For each var, determine status:
   - MISSING (not set or empty string) → red [MISSING] for REQUIRED, yellow [WARN] for SECURITY/CORS/STORAGE
   - SET but looks like placeholder (contains '<' or ends with '>') → yellow [PLACEHOLDER]
   - SET with non-empty value → green [OK], but redact secrets:
     For vars: APP_SECRET, DB_PASS, REDIS_PASSWORD, MAIL_PASS, API_KEY_*, MONITOR_WEBHOOK_URL
     show only first 4 chars + "****" instead of full value

4. Print with ANSI colors (detect TTY: if not a TTY, strip colors):
   Use these ANSI codes: green=\033[32m, yellow=\033[33m, red=\033[31m, reset=\033[0m

5. Print a summary line at the end:
   "X vars OK, Y warnings, Z missing"
   Exit code 0 if no REQUIRED vars missing, exit code 1 otherwise.

6. Special warnings:
   - If APP_ENV=production AND APP_DEBUG=1 → print red "[SECURITY] APP_DEBUG=1 in production!"
   - If APP_ENV=production AND LOAD_SEED_DATA=1 → print red "[SECURITY] LOAD_SEED_DATA=1 in production!"
   - If APP_ENV=production AND APP_URL contains localhost → print yellow "[WARN] APP_URL points to localhost"

Make the file executable: chmod +x bin/validate-env

Shebang line: #!/usr/bin/env php

Verify syntax: php -l bin/validate-env
Run: php bin/validate-env (should print report without fatal errors)
  </action>
  <verify>
    php -l app/Core/Providers/EnvProvider.php
    php -l app/Core/Application.php
    php -l bin/validate-env
    php bin/validate-env
    php -r "require 'app/Core/Providers/EnvProvider.php'; putenv('_TEST_QUOTE='); \AgVote\Core\Providers\EnvProvider::load('/dev/null');"
  </verify>
  <done>
    EnvProvider.php has trim($val, '"\'') after trim($val). Application.php logs APP_URL warning
    when production and localhost. bin/validate-env runs without fatal errors, prints colored
    report with OK/MISSING/WARNING per var, exits 1 when required vars are absent.
  </done>
</task>

</tasks>

<verification>
1. php -l app/Core/Providers/EnvProvider.php — no syntax errors
2. php -l app/Core/Application.php — no syntax errors
3. php -l bin/validate-env — no syntax errors
4. php bin/validate-env — prints report (exit code may be 1 in dev; that is expected)
5. grep -c "DB_DSN" .env.production.example — returns 1
6. grep -c "DB_HOST" .env.production.example — returns 0
7. grep -c "CSRF_LIFETIME" .env.example — returns 0
8. timeout 60 php vendor/bin/phpunit tests/Unit/PasswordResetServiceTest.php --no-coverage
   (smoke test that Application bootstrap is not broken)
</verification>

<success_criteria>
- .env.example: 0 phantom vars, all production hints present, validate-env footer added
- .env.production.example: DB_DSN used, all vars from .env.example present, same footer
- EnvProvider: single line trim($val, '"\'') strips both quote styles
- Application: error_log() warning fires for localhost APP_URL in production (no throw)
- bin/validate-env: executable, prints colored per-var report, exit 1 on missing required vars
- All PHP files pass php -l syntax check
</success_criteria>

<output>
After completion, create .planning/quick/260402-ffh-fix-env-ecosystem/260402-ffh-SUMMARY.md
following the standard summary template.
</output>
