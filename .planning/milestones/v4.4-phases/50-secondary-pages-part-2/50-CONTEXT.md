# Phase 50: Secondary Pages Part 2 - Context

**Gathered:** 2026-03-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Ground-up rebuild of 4 pages: audit, members, users, vote/ballot. Same v4.3 approach.

</domain>

<decisions>
## Implementation Decisions

### Approach (carried from v4.3)
- Read existing JS before touching HTML
- Rewrite HTML+CSS, update JS selectors if needed
- Verify backend, browser test before done

### Page-Specific Notes
- **Audit**: Timeline + table views, CSV export. Reference: archives table pattern.
- **Members**: Card/table toggle, CSV import, role management. Reference: users table pattern.
- **Users**: Table with CRUD, role assignment, pagination. Reference: v4.3 settings admin pattern.
- **Vote/Ballot**: Full-screen mobile ballot, optimistic feedback, PDF consultation. Reference: v4.3 voter patterns from Phase 29.

### Claude's Discretion
- All implementation choices — pure rebuild phase

</decisions>

<code_context>
## Existing Code Insights

### Pages to Rebuild
- `public/audit.htmx.html` + `audit.js` + `audit.css`
- `public/members.htmx.html` + `members.js` + `members.css`
- `public/users.htmx.html` + `users.js` + `users.css`
- `public/vote.htmx.html` + `vote.js` + `vote.css`

</code_context>

<specifics>
## Specific Ideas

Same v4.3 approach. WIRE-01/WIRE-02 verified within each page.

</specifics>

<deferred>
## Deferred Ideas

None.

</deferred>
