-- Migration: resolution_documents
-- Adds support for PDF documents attached to individual motions (resolutions)

CREATE TABLE IF NOT EXISTS resolution_documents (
    id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    motion_id uuid NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
    original_name text NOT NULL,
    stored_name text NOT NULL,
    mime_type text NOT NULL DEFAULT 'application/pdf',
    file_size bigint NOT NULL DEFAULT 0,
    display_order integer NOT NULL DEFAULT 0,
    uploaded_by uuid REFERENCES users(id) ON DELETE SET NULL,
    created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_resolution_docs_motion
    ON resolution_documents(motion_id);
CREATE INDEX IF NOT EXISTS idx_resolution_docs_meeting
    ON resolution_documents(meeting_id);
CREATE INDEX IF NOT EXISTS idx_resolution_docs_tenant
    ON resolution_documents(tenant_id);

COMMENT ON TABLE resolution_documents IS 'Documents PDF rattaches aux resolutions (motions) individuelles';
