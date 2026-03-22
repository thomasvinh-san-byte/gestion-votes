-- Migration: tenant_settings table
-- Created: 2026-03-22
-- Purpose: Per-tenant key/value settings store for settings page persistence

CREATE TABLE IF NOT EXISTS tenant_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tenant_id TEXT NOT NULL,
    key TEXT NOT NULL,
    value TEXT,
    updated_at TEXT DEFAULT (datetime('now')),
    UNIQUE(tenant_id, key)
);
