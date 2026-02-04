-- 005_email_system.sql
-- Systeme d'invitations ameliore: templates, file d'attente, rappels, tracking.
-- Idempotent : peut être relancé sans effet si déjà appliqué.

BEGIN;

-- ============================================================
-- Phase 1: Templates email personnalisables
-- ============================================================
CREATE TABLE IF NOT EXISTS email_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    template_type VARCHAR(50) NOT NULL DEFAULT 'invitation',
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    is_default BOOLEAN DEFAULT false,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT email_templates_type_check CHECK (template_type IN ('invitation', 'reminder', 'confirmation', 'custom')),
    CONSTRAINT email_templates_unique_name UNIQUE(tenant_id, name)
);

CREATE INDEX IF NOT EXISTS idx_email_templates_tenant ON email_templates(tenant_id);
CREATE INDEX IF NOT EXISTS idx_email_templates_type ON email_templates(tenant_id, template_type);
CREATE INDEX IF NOT EXISTS idx_email_templates_default ON email_templates(tenant_id, is_default) WHERE is_default = true;

DROP TRIGGER IF EXISTS trg_email_templates_updated_at ON email_templates;
CREATE TRIGGER trg_email_templates_updated_at
    BEFORE UPDATE ON email_templates FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Phase 2: File d'attente emails et rappels programmés
-- ============================================================
CREATE TABLE IF NOT EXISTS email_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    meeting_id UUID REFERENCES meetings(id) ON DELETE CASCADE,
    member_id UUID REFERENCES members(id) ON DELETE CASCADE,
    invitation_id UUID REFERENCES invitations(id) ON DELETE CASCADE,
    template_id UUID REFERENCES email_templates(id) ON DELETE SET NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    scheduled_at TIMESTAMPTZ NOT NULL,
    sent_at TIMESTAMPTZ,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    retry_count INT DEFAULT 0,
    max_retries INT DEFAULT 3,
    last_error TEXT,
    priority INT DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT email_queue_status_check CHECK (status IN ('pending', 'processing', 'sent', 'failed', 'cancelled'))
);

CREATE INDEX IF NOT EXISTS idx_email_queue_scheduled ON email_queue(scheduled_at) WHERE status = 'pending';
CREATE INDEX IF NOT EXISTS idx_email_queue_status ON email_queue(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_email_queue_meeting ON email_queue(meeting_id) WHERE meeting_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_email_queue_processing ON email_queue(status, scheduled_at) WHERE status IN ('pending', 'processing');

DROP TRIGGER IF EXISTS trg_email_queue_updated_at ON email_queue;
CREATE TRIGGER trg_email_queue_updated_at
    BEFORE UPDATE ON email_queue FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- Table des programmations de rappels
CREATE TABLE IF NOT EXISTS reminder_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    meeting_id UUID NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
    template_id UUID REFERENCES email_templates(id) ON DELETE SET NULL,
    days_before INT NOT NULL,
    send_time TIME DEFAULT '09:00',
    is_active BOOLEAN DEFAULT true,
    last_executed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT reminder_schedules_unique UNIQUE(tenant_id, meeting_id, days_before),
    CONSTRAINT reminder_schedules_days_check CHECK (days_before >= 0 AND days_before <= 30)
);

CREATE INDEX IF NOT EXISTS idx_reminder_schedules_meeting ON reminder_schedules(meeting_id);
CREATE INDEX IF NOT EXISTS idx_reminder_schedules_active ON reminder_schedules(is_active, days_before) WHERE is_active = true;

DROP TRIGGER IF EXISTS trg_reminder_schedules_updated_at ON reminder_schedules;
CREATE TRIGGER trg_reminder_schedules_updated_at
    BEFORE UPDATE ON reminder_schedules FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Phase 3: Tracking et metriques emails
-- ============================================================

-- Colonnes additionnelles sur invitations pour tracking
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS opened_at TIMESTAMPTZ;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS clicked_at TIMESTAMPTZ;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS open_count INT DEFAULT 0;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS click_count INT DEFAULT 0;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS last_error TEXT;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS template_id UUID REFERENCES email_templates(id) ON DELETE SET NULL;

-- Table des evenements email (historique detaille)
CREATE TABLE IF NOT EXISTS email_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    invitation_id UUID REFERENCES invitations(id) ON DELETE CASCADE,
    queue_id UUID REFERENCES email_queue(id) ON DELETE CASCADE,
    event_type VARCHAR(20) NOT NULL,
    event_data JSONB DEFAULT '{}'::jsonb,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT email_events_type_check CHECK (event_type IN ('queued', 'sent', 'delivered', 'bounced', 'opened', 'clicked', 'failed', 'cancelled'))
);

CREATE INDEX IF NOT EXISTS idx_email_events_invitation ON email_events(invitation_id) WHERE invitation_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_email_events_queue ON email_events(queue_id) WHERE queue_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_email_events_type ON email_events(tenant_id, event_type);
CREATE INDEX IF NOT EXISTS idx_email_events_time ON email_events(tenant_id, created_at DESC);

-- Vue pour statistiques d'envoi par seance
CREATE OR REPLACE VIEW email_stats_by_meeting AS
SELECT
    i.tenant_id,
    i.meeting_id,
    COUNT(*) AS total_invitations,
    COUNT(*) FILTER (WHERE i.status = 'pending') AS pending_count,
    COUNT(*) FILTER (WHERE i.status = 'sent') AS sent_count,
    COUNT(*) FILTER (WHERE i.status = 'opened') AS opened_count,
    COUNT(*) FILTER (WHERE i.status = 'accepted') AS accepted_count,
    COUNT(*) FILTER (WHERE i.status = 'declined') AS declined_count,
    COUNT(*) FILTER (WHERE i.status = 'bounced') AS bounced_count,
    COALESCE(SUM(i.open_count), 0) AS total_opens,
    COALESCE(SUM(i.click_count), 0) AS total_clicks,
    ROUND(
        CASE WHEN COUNT(*) FILTER (WHERE i.status IN ('sent', 'opened', 'accepted', 'declined')) > 0
        THEN COUNT(*) FILTER (WHERE i.status IN ('opened', 'accepted', 'declined'))::numeric /
             COUNT(*) FILTER (WHERE i.status IN ('sent', 'opened', 'accepted', 'declined')) * 100
        ELSE 0 END, 1
    ) AS open_rate,
    ROUND(
        CASE WHEN COUNT(*) FILTER (WHERE i.status = 'sent') > 0
        THEN COUNT(*) FILTER (WHERE i.status = 'bounced')::numeric /
             (COUNT(*) FILTER (WHERE i.status = 'sent') + COUNT(*) FILTER (WHERE i.status = 'bounced')) * 100
        ELSE 0 END, 1
    ) AS bounce_rate
FROM invitations i
GROUP BY i.tenant_id, i.meeting_id;

COMMIT;
