# Phase 77: RGPD Compliance - Context

**Gathered:** 2026-04-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Three RGPD features: (1) member self-service data export from Mon Compte page, (2) admin-configurable data retention policy with automatic purge, (3) admin right-to-erasure with cascade deletion across all tables.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation choices are at Claude's discretion. Key considerations:
- Data export: use /account page (Phase 71) as entry point, JSON or CSV format
- Retention: store policy in tenant_settings, use a console command for purging (same pattern as email queue worker)
- Erasure: admin action in /admin users page, cascade across members, ballots, attendances, proxies, audit_events
- All tables have ON DELETE CASCADE on user_id/member_id foreign keys — verify before implementing

References:
- AccountController for /account page integration
- AdminController for admin user management
- tenant_settings pattern for retention config
- bin/console for CLI commands (email queue worker pattern)

</decisions>

<code_context>
## Existing Code Insights

Codebase context will be gathered during planning.

</code_context>

<specifics>
## Specific Ideas

No specific requirements beyond ROADMAP success criteria.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
