-- =============================================================================
-- AG-VOTE Migration: Document column semantics
-- Date: 2026-03-10
-- Purpose: Add COMMENT on ambiguous columns for developer clarity
-- =============================================================================

-- attendances.effective_power:
-- When NULL, the member's default voting_power (from members table) is used.
-- When set, overrides the member's default voting_power for this specific meeting.
-- Typical use case: proxies that grant additional voting weight.
COMMENT ON COLUMN attendances.effective_power IS
    'Override for member voting_power in this meeting. NULL = use members.voting_power default. '
    'Set when proxies or special resolutions modify a member''s weight.';

-- motions state is derived from opened_at/closed_at, not a status column:
-- draft = opened_at IS NULL AND closed_at IS NULL
-- open  = opened_at IS NOT NULL AND closed_at IS NULL
-- closed = closed_at IS NOT NULL
COMMENT ON TABLE motions IS
    'Resolutions/motions for voting. State is derived from opened_at/closed_at timestamps: '
    'draft (both NULL), open (opened_at set, closed_at NULL), closed (closed_at set). '
    'No separate status column — single source of truth via timestamps.';

-- audit_log hash chain
COMMENT ON COLUMN audit_log.previous_hash IS
    'SHA-256 hash of the previous audit entry, forming an immutable chain. '
    'Protected by PostgreSQL trigger preventing UPDATE/DELETE.';
