# Phase 22: Audit - Context

**Gathered:** 2026-03-18
**Status:** Ready for planning

<domain>
## Phase Boundary

Intermediate audit — NOT the final phase of the project. Verify the codebase contains zero demo constants and every API call site has correct loading, error, and empty states. The session lifecycle can be run end-to-end without encountering any placeholder data. Two-plan structure: Plan 1 for DEMO_ eradication, Plan 2 for loading/error/empty audit and fixes.

Note: Additional phases will follow (retrait copropriété, PDFs résolutions, etc.). This audit closes out the v3.0 CLN requirements, not the project.

Requirements: CLN-01 (zero DEMO_ constants), CLN-02 (every API call has loading/error/empty states).

</domain>

<decisions>
## Implementation Decisions

### DEMO_ Sweep Scope
- Total eradication: runtime code, config files, shell scripts, documentation, AND planning files
- Delete .env.demo and setup_demo_az.sh entirely (.env.example already exists as reference)
- Rename all DEMO_ env vars in docker-compose.yml and deploy scripts to proper names (e.g., SEED_DATA, DEV_MODE)
- Clean DEMO_ references from docker service names, comments, and all documentation
- Planning files (.planning/) are also cleaned — no historical exception

### Loading/Error/Empty Coverage
- Audit every single fetch(), Utils.apiGet(), Utils.apiPost() call across all pages
- Unified loading pattern: ag-spinner component in content area, replacing content until loaded
- Error pattern: error message + retry button (follows established tryLoad pattern from audit.js/dashboard.js)
- Empty state pattern: Shared.emptyState() for all pages with no data
- Background calls (SSE heartbeat, device_heartbeat, SW cache): follow UX best practices — silent retry, surface a subtle "connection lost" banner only if degradation persists

### Gap Remediation Approach
- Two-pass: document all gaps first (checklist in PLAN.md), then fix in second pass
- Two separate plans: Plan 1 = DEMO_ eradication, Plan 2 = loading/error/empty audit + fixes
- Each page gets a checklist item with its specific gaps listed

### Verification Method
- DEMO_ verification: one-time grep script (not committed to repo) that confirms zero DEMO_ hits across entire repo
- API states verification: code review checklist table in SUMMARY.md — for each JS page file, list every fetch call and confirm loading + error + empty state exists

### Claude's Discretion
- Exact replacement names for DEMO_ env vars in docker/deploy configs
- Order of pages to audit for loading/error/empty states
- Whether background connection-lost banner uses ag-toast or a dedicated component
- Retry delay/count for the tryLoad pattern where not already established

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `ag-spinner` web component: existing loading indicator, use for all loading states
- `Shared.emptyState()`: established empty state renderer, already used in 18+ JS files
- `tryLoad(attempt)` pattern: retry logic established in audit.js and dashboard.js (ES5-compatible, promise-based)
- `Utils.apiGet()` / `Utils.apiPost()`: centralized API call wrappers in utils.js

### Established Patterns
- Phase 17 pattern: `tryLoad(attempt)` with setTimeout retry, error banner with retry button, `Shared.emptyState()` on empty response
- Phase 17 pattern: `hub-error dashboard-error` CSS class for error banners
- Phase 17 decision: reset KPI values to dash on error to avoid stale counts
- ES5-compatible style: promise-based `tryLoad(attempt)` instead of async/await

### Integration Points
- 9 page JS files with direct `fetch()` calls: public.js, landing.js, pv-print.js, docs-viewer.js, operator-tabs.js, vote.js, hub.js, audit.js, dashboard.js
- 18 JS files already reference emptyState — many pages partially covered
- 20 JS files reference loading/spinner — existing loading infrastructure
- DEMO_ references in: .env.demo, docker-compose.yml, docker-compose.prod.yml, deploy/entrypoint.sh, render.yaml, render-production.yaml, setup_demo_az.sh, bin/check-prod-readiness.sh, docs/*.md

</code_context>

<specifics>
## Specific Ideas

No specific requirements — follow established Phase 17 patterns (tryLoad, emptyState, error+retry) consistently across all pages.

</specifics>

<deferred>
## Deferred Ideas

- **Retrait copropriété** — Remove all copropriete/copropriete-related code and scope from the codebase. Separate cleanup phase.
- **PDFs résolutions** — Allow attaching PDF documents to resolutions in sessions, with consultation access for voters during voting. New feature phase.

</deferred>

---

*Phase: 22-final-audit*
*Context gathered: 2026-03-18*
