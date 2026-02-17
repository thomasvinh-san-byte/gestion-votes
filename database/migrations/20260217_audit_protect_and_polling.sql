-- Migration: Protect audit_events from deletion
-- Date: 2026-02-17
--
-- Adds a trigger that prevents DELETE on audit_events table.
-- This ensures the integrity of the audit trail.

CREATE OR REPLACE FUNCTION prevent_audit_delete()
RETURNS TRIGGER AS $$
BEGIN
  RAISE EXCEPTION 'Suppression interdite sur audit_events : la piste d''audit est immuable';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_no_delete ON audit_events;

CREATE TRIGGER trg_audit_no_delete
  BEFORE DELETE ON audit_events
  FOR EACH ROW
  EXECUTE FUNCTION prevent_audit_delete();
