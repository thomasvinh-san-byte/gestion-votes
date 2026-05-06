# Phase 1: Audit et Classification - Context

**Gathered:** 2026-04-20
**Status:** Ready for planning

<domain>
## Phase Boundary

Inventory all mutating routes (POST/PATCH/DELETE), document their current protection level (IdempotencyGuard, DB UNIQUE constraint, CSRF only), and classify each by risk level based on business impact of a duplicate. Output is a reference document consumed by Phase 2.

</domain>

<decisions>
## Implementation Decisions

### Audit Output
- **D-01:** Produce a Markdown document (`01-IDEMPOTENCY-AUDIT.md`) in the phase directory with a table of all routes
- **D-02:** Table columns: Route, Method, Controller::method, Current Protection, Risk Level, Notes

### Risk Classification
- **D-03:** Critique = duplicate has direct business impact (double vote, double creation of a meeting/member, double email send)
- **D-04:** Moyen = duplicate creates noise but no data corruption (duplicate reminder, duplicate attachment upload)
- **D-05:** Bas = duplicate is harmless or already prevented by DB constraint (update operations, delete operations)

### Route Discovery
- **D-06:** Include ALL mutating endpoints — explicit POST/PATCH/DELETE routes AND mapAny routes that handle mutations
- **D-07:** Include auth routes (login), workflow transitions (start/close meeting), and import endpoints

### Claude's Discretion
- Exact ordering of routes in the table (by controller, by risk, or by URL path)
- Whether to include a summary section with counts per protection level
- How to handle routes that have partial protection (e.g., CSRF + DB constraint but no IdempotencyGuard)

</decisions>

<canonical_refs>
## Canonical References

### Requirements
- `.planning/REQUIREMENTS.md` — IDEM-01, IDEM-02

### Key Files
- `app/routes.php` — route definitions
- `app/Core/Security/IdempotencyGuard.php` — existing guard implementation
- `app/Core/Security/CsrfMiddleware.php` — CSRF protection
- `database/schema-master.sql` — DB UNIQUE constraints

</canonical_refs>

<code_context>
## Existing Code Insights

### Current IdempotencyGuard Coverage (3 controllers)
- `MeetingsController::createMeeting` — protected
- `AgendaController::create` — protected
- `MembersController::create` — protected

### DB UNIQUE Constraints (from schema-master.sql)
- `ballots(motion_id, member_id)` — one vote per member per motion
- `attendances(tenant_id, meeting_id, member_id)` — one attendance record
- `invitations(tenant_id, meeting_id, member_id)` — one invitation
- `proxies(tenant_id, meeting_id, giver_member_id)` — one proxy per giver

### Known Unprotected Routes (~25)
- All email_templates, export_templates, member_groups CRUD
- All attachment/document uploads
- Reminder upsert
- Import bulk
- Workflow transitions (start/close meeting)
- Auth login (rate limited but not idempotent)

</code_context>

<specifics>
## Specific Ideas

- The audit document will be the primary input for Phase 2 planning — it must be precise enough to generate a task list
- Routes already protected by both IdempotencyGuard AND DB UNIQUE are "fully covered" — no work needed in Phase 2

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 01-audit-et-classification*
*Context gathered: 2026-04-20*
