-- Migration: Add tenant_id to paper_ballots for defense-in-depth tenant isolation
-- Date: 2026-02-21
-- Ticket: audit-A2

-- Step 1: Add nullable tenant_id column
ALTER TABLE paper_ballots
  ADD COLUMN IF NOT EXISTS tenant_id uuid REFERENCES tenants(id) ON DELETE CASCADE;

-- Step 2: Backfill tenant_id from the meetings table
UPDATE paper_ballots pb
   SET tenant_id = m.tenant_id
  FROM meetings m
 WHERE pb.meeting_id = m.id
   AND pb.tenant_id IS NULL;

-- Step 3: Make tenant_id NOT NULL after backfill
ALTER TABLE paper_ballots
  ALTER COLUMN tenant_id SET NOT NULL;

-- Step 4: Add index for tenant-scoped lookups
CREATE INDEX IF NOT EXISTS idx_paper_ballots_tenant
  ON paper_ballots(tenant_id);
