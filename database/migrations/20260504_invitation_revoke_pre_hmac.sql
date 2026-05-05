-- ─────────────────────────────────────────────────────────────────────────
-- Migration: revoke pre-HMAC invitations (SEC-V2-03 v2.6)
-- ─────────────────────────────────────────────────────────────────────────
-- The invitation token hashing changed from plain SHA-256 to HMAC-SHA256
-- keyed with APP_SECRET. Existing token_hash rows in the database were
-- computed with the legacy algorithm and will no longer validate against
-- the new HMAC lookup.
--
-- This migration revokes (`revoked_at = NOW()`) all pending/sent invitations
-- whose hashes predate the algorithm change. Operators must re-issue
-- invitations for affected meetings.
--
-- Idempotent: rows already revoked are filtered out by the WHERE clause.
-- Safe to re-run (no-op after first execution).
--
-- Audit trail: each affected invitation gets a row in audit_events recording
-- the revoke event under the action `invitation.bulk_revoke_sec_v2_03`.
-- ─────────────────────────────────────────────────────────────────────────

WITH revoked AS (
    UPDATE invitations
    SET revoked_at = NOW(),
        updated_at = NOW()
    WHERE revoked_at IS NULL
      AND status IN ('pending', 'sent')
      AND token_hash IS NOT NULL
    RETURNING id, tenant_id, meeting_id, member_id
)
INSERT INTO audit_events (tenant_id, meeting_id, actor_user_id, actor_role, action, resource_type, resource_id, payload, created_at)
SELECT
    revoked.tenant_id,
    revoked.meeting_id,
    NULL,
    'system',
    'invitation.bulk_revoke_sec_v2_03',
    'invitation',
    revoked.id,
    jsonb_build_object(
        'reason', 'pre_hmac_token_hash',
        'sec_advisory', 'SEC-V2-03',
        'remediation', 're-issue invitation'
    ),
    NOW()
FROM revoked;
