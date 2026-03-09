-- =============================================================================
-- Migration: Add missing FK constraints + cleanup (2026-03-10)
-- =============================================================================
-- 1. FK constraints on speech_requests, meeting_notifications, manual_actions
-- 2. FK constraint on meetings.paused_by
-- 3. Drop obsolete UNIQUE constraint on invitations.token
-- 4. Trigger updated_at on meeting_roles
-- =============================================================================

-- ---------------------------------------------------------------------------
-- 1. FK constraints missing from migration 20260220
-- ---------------------------------------------------------------------------

-- speech_requests
DO $$ BEGIN
  ALTER TABLE speech_requests
    ADD CONSTRAINT fk_speech_requests_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE speech_requests
    ADD CONSTRAINT fk_speech_requests_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE speech_requests
    ADD CONSTRAINT fk_speech_requests_member FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- meeting_notifications
DO $$ BEGIN
  ALTER TABLE meeting_notifications
    ADD CONSTRAINT fk_meeting_notifications_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE meeting_notifications
    ADD CONSTRAINT fk_meeting_notifications_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- meeting_validation_state
DO $$ BEGIN
  ALTER TABLE meeting_validation_state
    ADD CONSTRAINT fk_meeting_validation_state_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE meeting_validation_state
    ADD CONSTRAINT fk_meeting_validation_state_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- manual_actions
DO $$ BEGIN
  ALTER TABLE manual_actions
    ADD CONSTRAINT fk_manual_actions_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE manual_actions
    ADD CONSTRAINT fk_manual_actions_meeting FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

DO $$ BEGIN
  ALTER TABLE manual_actions
    ADD CONSTRAINT fk_manual_actions_motion FOREIGN KEY (motion_id) REFERENCES motions(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ---------------------------------------------------------------------------
-- 2. FK constraint on meetings.paused_by
-- ---------------------------------------------------------------------------
DO $$ BEGIN
  ALTER TABLE meetings
    ADD CONSTRAINT fk_meetings_paused_by FOREIGN KEY (paused_by) REFERENCES users(id) ON DELETE SET NULL;
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ---------------------------------------------------------------------------
-- 3. Drop obsolete UNIQUE constraint on invitations.token
--    Token column is now always NULL (migrated to token_hash).
-- ---------------------------------------------------------------------------
DO $$ BEGIN
  ALTER TABLE invitations DROP CONSTRAINT IF EXISTS invitations_token_key;
EXCEPTION WHEN undefined_object THEN NULL;
END $$;

-- ---------------------------------------------------------------------------
-- 4. Trigger updated_at on meeting_roles
-- ---------------------------------------------------------------------------
DO $$ BEGIN
  ALTER TABLE meeting_roles ADD COLUMN IF NOT EXISTS updated_at timestamptz NOT NULL DEFAULT now();
EXCEPTION WHEN duplicate_column THEN NULL;
END $$;

CREATE OR REPLACE FUNCTION trg_meeting_roles_updated_at() RETURNS trigger AS $$
BEGIN
  NEW.updated_at := now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_meeting_roles_updated_at ON meeting_roles;
CREATE TRIGGER trg_meeting_roles_updated_at
  BEFORE UPDATE ON meeting_roles
  FOR EACH ROW EXECUTE FUNCTION trg_meeting_roles_updated_at();
