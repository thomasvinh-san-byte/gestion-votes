-- Covering index for tenant-scoped audit queries filtered by action.
-- Replaces lookups that would otherwise scan idx_audit_events_action
-- without tenant isolation.
CREATE INDEX CONCURRENTLY IF NOT EXISTS idx_audit_tenant_action_time
  ON audit_events(tenant_id, action, created_at DESC);
