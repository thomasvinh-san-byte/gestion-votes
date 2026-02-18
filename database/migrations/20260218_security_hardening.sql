-- =============================================================================
-- Migration: Security Hardening (2026-02-18)
-- =============================================================================
-- 1. Audit hash chain: scope per-meeting to reduce contention + FOR UPDATE
-- 2. Invitation tokens: migrate raw tokens to hash-only storage
-- 3. Missing CHECK constraints on motions, ballots, members
-- 4. Missing indexes for common query patterns
-- 5. NOT NULL on vote_tokens critical FKs
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
BEGIN;

UPDATE invitations
SET token_hash = encode(digest(token, 'sha256'), 'hex'),
    token = NULL
WHERE token IS NOT NULL
  AND token_hash IS NULL;

UPDATE invitations
SET token = NULL
WHERE token IS NOT NULL
  AND token_hash IS NOT NULL;

COMMIT;

-- ---------------------------------------------------------------------------
-- 3. Missing CHECK constraints
-- ---------------------------------------------------------------------------
DO $$ BEGIN
  ALTER TABLE motions ADD CONSTRAINT motions_status_check
    CHECK (status IS NULL OR status IN ('draft','open','closed'));
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE motions ADD CONSTRAINT motions_decision_check
    CHECK (decision IS NULL OR decision IN ('adopted','rejected'));
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE ballots ADD CONSTRAINT ballots_weight_positive
    CHECK (weight >= 0);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE ballots ADD CONSTRAINT ballots_source_check
    CHECK (source IS NULL OR source IN ('tablet','manual','electronic','paper'));
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE members ADD CONSTRAINT members_voting_power_positive
    CHECK (voting_power IS NULL OR voting_power >= 0);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ---------------------------------------------------------------------------
-- 4. Missing indexes for common query patterns
-- ---------------------------------------------------------------------------
CREATE INDEX IF NOT EXISTS idx_ballots_tenant_meeting_motion
  ON ballots(tenant_id, meeting_id, motion_id);

CREATE INDEX IF NOT EXISTS idx_motions_meeting_closed
  ON motions(meeting_id, closed_at) WHERE closed_at IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_members_user_id
  ON members(user_id) WHERE user_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_invitations_meeting_member
  ON invitations(meeting_id, member_id);

CREATE INDEX IF NOT EXISTS idx_vote_tokens_member_motion
  ON vote_tokens(meeting_id, motion_id, member_id) WHERE used_at IS NULL;

-- ---------------------------------------------------------------------------
-- 5. NOT NULL on vote_tokens critical FKs
-- ---------------------------------------------------------------------------
DO $$ BEGIN
  ALTER TABLE vote_tokens ALTER COLUMN tenant_id SET NOT NULL;
  ALTER TABLE vote_tokens ALTER COLUMN meeting_id SET NOT NULL;
  ALTER TABLE vote_tokens ALTER COLUMN member_id SET NOT NULL;
  ALTER TABLE vote_tokens ALTER COLUMN motion_id SET NOT NULL;
EXCEPTION WHEN others THEN
  RAISE NOTICE 'vote_tokens NOT NULL constraints: %', SQLERRM;
END $$;
