-- Migration: notification_reads table
-- Created: 2026-03-31
-- Purpose: Track which notifications each user has read

CREATE TABLE IF NOT EXISTS notification_reads (
    id SERIAL PRIMARY KEY,
    user_id TEXT NOT NULL,
    tenant_id TEXT NOT NULL,
    event_id TEXT NOT NULL,
    read_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, event_id)
);

CREATE INDEX IF NOT EXISTS idx_notification_reads_user
    ON notification_reads (user_id, tenant_id);
