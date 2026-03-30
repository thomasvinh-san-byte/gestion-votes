-- Migration: tenant_settings table
-- Created: 2026-03-22
-- Purpose: Per-tenant key/value settings store for settings page persistence

CREATE TABLE IF NOT EXISTS tenant_settings (
    id SERIAL PRIMARY KEY,
    tenant_id TEXT NOT NULL,
    key TEXT NOT NULL,
    value TEXT,
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, key)
);
