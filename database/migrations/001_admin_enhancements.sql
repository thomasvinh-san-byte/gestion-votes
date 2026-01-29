-- ============================================================
-- Migration 001: Admin Application Enhancements
-- Date: 2026-01-29
-- Description: Fixes role constraints, adds missing audit columns,
--              and prepares schema for the admin application.
-- ============================================================

BEGIN;

-- 1. Fix users.role constraint to include all RBAC roles
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
ALTER TABLE users ADD CONSTRAINT users_role_check
  CHECK (role IN ('admin','operator','president','trust','viewer','readonly','voter'));

-- 2. Ensure audit_events has the columns used by bootstrap.php
ALTER TABLE audit_events ADD COLUMN IF NOT EXISTS actor_role text;
ALTER TABLE audit_events ADD COLUMN IF NOT EXISTS meeting_id uuid;
ALTER TABLE audit_events ADD COLUMN IF NOT EXISTS ip_address inet;
ALTER TABLE audit_events ADD COLUMN IF NOT EXISTS user_agent text;

-- Add FK for meeting_id if not present
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name = 'audit_events'
      AND constraint_type = 'FOREIGN KEY'
      AND constraint_name = 'audit_events_meeting_id_fkey'
  ) THEN
    ALTER TABLE audit_events
      ADD CONSTRAINT audit_events_meeting_id_fkey
      FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE SET NULL;
  END IF;
END $$;

-- 3. Index for admin dashboard queries
CREATE INDEX IF NOT EXISTS idx_audit_events_action ON audit_events(action, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_auth_failures_ip ON auth_failures(ip, created_at DESC);

COMMIT;
