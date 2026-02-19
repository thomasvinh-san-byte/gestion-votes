-- Migration: Add 'paused' status to meeting lifecycle
-- Allows operators to temporarily pause a live session and resume it later.

-- 1. Add 'paused' value to meeting_status enum (between 'live' and 'closed')
DO $$
BEGIN
  ALTER TYPE meeting_status ADD VALUE IF NOT EXISTS 'paused' AFTER 'live';
EXCEPTION WHEN others THEN
  RAISE NOTICE 'meeting_status paused already exists, skipping';
END;
$$;

-- 2. Add tracking columns for pause state
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS paused_at timestamptz;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS paused_by uuid;

-- 3. Add state transitions for pause/resume
INSERT INTO meeting_state_transitions (from_status, to_status, required_role, description) VALUES
  ('live',   'paused', 'operator',  'Mettre la séance en pause'),
  ('paused', 'live',   'operator',  'Reprendre la séance'),
  ('paused', 'closed', 'president', 'Clôturer directement depuis la pause')
ON CONFLICT (from_status, to_status) DO NOTHING;

-- 4. Add partial index for paused meetings
CREATE INDEX IF NOT EXISTS idx_meetings_paused ON meetings(tenant_id) WHERE status = 'paused';
