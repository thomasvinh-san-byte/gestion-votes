-- Add meeting_type column to meetings table
-- Supports: ag_ordinaire, ag_extraordinaire, conseil, bureau, autre
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS meeting_type text NOT NULL DEFAULT 'ag_ordinaire';
