-- ─────────────────────────────────────────────────────────────────────────
-- Migration: next_step_clicks — UX metric for ErrorDictionary suggestions
-- ─────────────────────────────────────────────────────────────────────────
-- LOG-V25-04 (Phase 6 v2.5):
--
-- Captures clicks on the "next-step" actionable suggestion shown beneath
-- error messages (introduced v2.3 P4.4 ErrorDictionary enrichment).
--
-- Read alongside error_events.count(error_code) to compute the click-through
-- rate per code. A high CTR means the suggestion is useful; a low CTR
-- means the user ignores it (rephrase or remove).
--
-- Cardinality: 1 row per click. Expected volume is ≤ error_events volume.
-- ─────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS next_step_clicks (
    id           UUID         PRIMARY KEY DEFAULT gen_random_uuid(),
    occurred_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
    request_id   TEXT,
    tenant_id    UUID,
    user_id      UUID,
    error_code   TEXT         NOT NULL,
    suggestion   TEXT,
    route        TEXT
);

CREATE INDEX IF NOT EXISTS idx_next_step_clicks_code_occurred
    ON next_step_clicks (error_code, occurred_at DESC);

CREATE INDEX IF NOT EXISTS idx_next_step_clicks_tenant_occurred
    ON next_step_clicks (tenant_id, occurred_at DESC);

COMMENT ON TABLE next_step_clicks IS
    'UX metric — clicks on ErrorDictionary next-step suggestions. LOG-V25-04 v2.5.';
