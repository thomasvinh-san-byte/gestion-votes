-- =============================================================================
-- Migration: Security Hardening (2026-02-18)
-- =============================================================================
-- 1. Audit hash chain: scope per-meeting to reduce contention + FOR UPDATE
-- 2. Invitation tokens: migrate raw tokens to hash-only storage
-- 3. Populate token_hash for existing invitations with raw tokens
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1. Fix audit hash chain race condition
--    Scope chain per (tenant_id, meeting_id) instead of per tenant_id only.
--    Use FOR UPDATE to serialize chain computation within same scope.
-- ---------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION audit_events_compute_hash() RETURNS trigger AS $$
DECLARE
  prev bytea;
BEGIN
  -- Scope chain per meeting when available, else per tenant
  IF NEW.meeting_id IS NOT NULL THEN
    SELECT this_hash INTO prev
    FROM audit_events
    WHERE tenant_id = NEW.tenant_id
      AND meeting_id = NEW.meeting_id
    ORDER BY created_at DESC, id DESC
    LIMIT 1
    FOR UPDATE;
  ELSE
    SELECT this_hash INTO prev
    FROM audit_events
    WHERE tenant_id = NEW.tenant_id
      AND meeting_id IS NULL
    ORDER BY created_at DESC, id DESC
    LIMIT 1
    FOR UPDATE;
  END IF;

  NEW.prev_hash := prev;
  NEW.this_hash := digest(
    coalesce(encode(NEW.prev_hash,'hex'),'') || '|' ||
    coalesce(NEW.tenant_id::text,'') || '|' ||
    coalesce(NEW.actor_user_id::text,'') || '|' ||
    coalesce(NEW.action,'') || '|' ||
    coalesce(NEW.resource_type,'') || '|' ||
    coalesce(NEW.resource_id::text,'') || '|' ||
    coalesce(NEW.payload::text,'') || '|' ||
    coalesce(NEW.created_at::text,''),
    'sha256'
  );
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

-- Index to speed up the per-meeting chain lookup
CREATE INDEX IF NOT EXISTS idx_audit_meeting_chain
  ON audit_events(tenant_id, meeting_id, created_at DESC, id DESC)
  WHERE meeting_id IS NOT NULL;

-- Index for tenant-level events (no meeting)
CREATE INDEX IF NOT EXISTS idx_audit_tenant_chain
  ON audit_events(tenant_id, created_at DESC, id DESC)
  WHERE meeting_id IS NULL;

-- ---------------------------------------------------------------------------
-- 2. Migrate invitation tokens: populate token_hash from raw tokens
-- ---------------------------------------------------------------------------
UPDATE invitations
SET token_hash = encode(digest(token, 'sha256'), 'hex'),
    token = NULL
WHERE token IS NOT NULL
  AND token_hash IS NULL;

-- ---------------------------------------------------------------------------
-- 3. Clear any remaining raw tokens where hash already exists
-- ---------------------------------------------------------------------------
UPDATE invitations
SET token = NULL
WHERE token IS NOT NULL
  AND token_hash IS NOT NULL;
