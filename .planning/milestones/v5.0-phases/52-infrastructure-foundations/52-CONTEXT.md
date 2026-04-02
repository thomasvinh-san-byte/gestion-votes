# Phase 52: Infrastructure Foundations - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Fix Docker healthcheck PORT evaluation, entrypoint read-only FS handling, health endpoint JSON response, and audit all migration files for SQLite-isms. Deliverables: production-clean Docker stack + migration dry-run script.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion — pure infrastructure phase.

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- deploy/entrypoint.sh — current entrypoint with PORT handling (line 167-183)
- deploy/nginx.conf — nginx config that gets sed-patched for PORT
- public/api/v1/health.php — existing health endpoint
- database/migrations/ — 10+ migration files, one already fixed (20260322_tenant_settings.sql)

### Established Patterns
- Migrations use raw SQL with psql ON_ERROR_STOP=1
- entrypoint.sh tracks applied migrations via applied_migrations table
- Health endpoint does PDO connect with 5s timeout, returns JSON 200/503

### Integration Points
- Dockerfile HEALTHCHECK instruction references health endpoint
- docker-compose.yml sets read_only: true on app container
- GitHub Actions .github/workflows/docker-build.yml runs smoke tests

</code_context>

<specifics>
## Specific Ideas

No specific requirements — infrastructure phase.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>
