-- Migration: Add tenant_id to meeting_reports for defense-in-depth tenant isolation
-- Date: 2026-02-21
-- Ticket: P1-04

-- Step 1: Add nullable tenant_id column
ALTER TABLE meeting_reports
  ADD COLUMN IF NOT EXISTS tenant_id uuid REFERENCES tenants(id) ON DELETE CASCADE;

-- Step 2: Backfill tenant_id from the meetings table
UPDATE meeting_reports mr
   SET tenant_id = m.tenant_id
  FROM meetings m
 WHERE mr.meeting_id = m.id
   AND mr.tenant_id IS NULL;

-- Step 3: Make tenant_id NOT NULL after backfill
ALTER TABLE meeting_reports
  ALTER COLUMN tenant_id SET NOT NULL;

-- Step 4: Add index for tenant-scoped lookups
CREATE INDEX IF NOT EXISTS idx_meeting_reports_tenant
  ON meeting_reports(tenant_id);
