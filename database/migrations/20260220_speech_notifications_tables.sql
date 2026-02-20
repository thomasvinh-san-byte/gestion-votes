-- Migration: Create speech_requests, meeting_notifications, meeting_validation_state tables
-- Previously created at runtime via ensureSchema() calls.
-- This migration makes them proper schema-level objects.

-- Speech requests
CREATE TABLE IF NOT EXISTS speech_requests (
    id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    meeting_id uuid NOT NULL,
    member_id uuid NOT NULL,
    status text NOT NULL CHECK (status IN ('waiting','speaking','finished','cancelled')),
    created_at timestamptz NOT NULL DEFAULT now(),
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_speech_requests_meeting_status ON speech_requests (meeting_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_speech_requests_member ON speech_requests (meeting_id, member_id, updated_at DESC);

-- Meeting notifications
CREATE TABLE IF NOT EXISTS meeting_notifications (
    id bigserial PRIMARY KEY,
    tenant_id uuid NOT NULL,
    meeting_id uuid NOT NULL,
    severity text NOT NULL CHECK (severity IN ('blocking','warn','info')),
    code text NOT NULL,
    message text NOT NULL,
    audience text[] NOT NULL DEFAULT ARRAY['operator','trust'],
    data jsonb NOT NULL DEFAULT '{}'::jsonb,
    read_at timestamptz,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_meeting_notifications_meeting_id ON meeting_notifications(meeting_id, id DESC);
CREATE INDEX IF NOT EXISTS idx_meeting_notifications_audience ON meeting_notifications USING gin(audience);

-- Meeting validation state
CREATE TABLE IF NOT EXISTS meeting_validation_state (
    meeting_id uuid PRIMARY KEY,
    tenant_id uuid NOT NULL,
    ready boolean NOT NULL,
    codes jsonb NOT NULL DEFAULT '[]'::jsonb,
    updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_meeting_validation_state_tenant ON meeting_validation_state(tenant_id);

-- Manual actions (also previously ensureSchema)
CREATE TABLE IF NOT EXISTS manual_actions (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id uuid NOT NULL,
    meeting_id uuid NOT NULL,
    motion_id uuid,
    action_type text NOT NULL,
    actor_role text NOT NULL DEFAULT 'operator',
    payload jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_manual_actions_meeting ON manual_actions(meeting_id, created_at DESC);
