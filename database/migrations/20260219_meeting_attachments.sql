-- Migration: meeting_attachments
-- Adds support for PDF attachments on meetings (convocations, PJ, etc.)

CREATE TABLE IF NOT EXISTS meeting_attachments (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    original_name text NOT NULL,
    stored_name text NOT NULL,
    mime_type text NOT NULL DEFAULT 'application/pdf',
    file_size bigint NOT NULL DEFAULT 0,
    uploaded_by uuid REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_meeting_attachments_meeting
    ON meeting_attachments(meeting_id);
CREATE INDEX IF NOT EXISTS idx_meeting_attachments_tenant
    ON meeting_attachments(tenant_id);

COMMENT ON TABLE meeting_attachments IS 'Pièces jointes PDF rattachées aux séances';
