-- ─────────────────────────────────────────────────────────────────────────
-- Migration: error_events table for server-side error capture
-- ─────────────────────────────────────────────────────────────────────────
-- LOG-V25-02 (Phase 6 v2.5):
--
-- Captures every api_fail() return server-side so /admin/error-stats can
-- show the real signal (which codes are emitted, by whom, on which routes).
-- Replaces the limited audit_events filtering shipped in v2.4 P2.3 which
-- only saw 6 audit-flavored actions.
--
-- Capture path: app/api.php api_fail() — try/catch isolated so a failure
-- to insert never breaks the API response. Reads written by
-- ErrorEventsRepository (top codes, timeline, drill-down by tenant).
--
-- Indexes are sized for the dashboard query patterns:
--  - timeline / top codes over a 7d window → idx_occurred_at
--  - per-tenant drill-down → idx_tenant_code_occurred
--  - global per-code lookup → idx_code_occurred
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS error_events (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    occurred_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    request_id   TEXT,
    tenant_id    UUID,
    user_id      UUID,
    error_code   TEXT         NOT NULL,
    http_status  INTEGER      NOT NULL,
    route        TEXT,
    method       TEXT,
    payload      JSONB        NOT NULL DEFAULT '{}'::jsonb
);

CREATE INDEX IF NOT EXISTS idx_error_events_occurred_at
    ON error_events (occurred_at DESC);

CREATE INDEX IF NOT EXISTS idx_error_events_tenant_code_occurred
    ON error_events (tenant_id, error_code, occurred_at DESC);

CREATE INDEX IF NOT EXISTS idx_error_events_code_occurred
    ON error_events (error_code, occurred_at DESC);

COMMENT ON TABLE error_events IS
    'Server-side capture of every api_fail() response. Source for /admin/error-stats. LOG-V25-02 v2.5.';
