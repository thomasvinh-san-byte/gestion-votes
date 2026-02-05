-- 007_export_templates.sql
-- Phase 3: Templates d'export personnalisables
-- Permet aux utilisateurs de definir des modeles d'export avec colonnes selectionnees.
-- Idempotent : peut etre relance sans effet si deja applique.

BEGIN;

-- ============================================================
-- Table des templates d'export
-- ============================================================
CREATE TABLE IF NOT EXISTS export_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    export_type VARCHAR(50) NOT NULL,
    columns JSONB NOT NULL DEFAULT '[]'::jsonb,
    is_default BOOLEAN DEFAULT false,
    created_by UUID REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT export_templates_type_check CHECK (
        export_type IN ('attendance', 'votes', 'members', 'motions', 'audit', 'proxies')
    ),
    CONSTRAINT export_templates_unique_name UNIQUE(tenant_id, name, export_type),
    CONSTRAINT export_templates_columns_valid CHECK (jsonb_typeof(columns) = 'array')
);

COMMENT ON TABLE export_templates IS 'Templates d''export personnalisables par tenant';
COMMENT ON COLUMN export_templates.export_type IS 'Type d''export: attendance, votes, members, motions, audit, proxies';
COMMENT ON COLUMN export_templates.columns IS 'Configuration des colonnes: [{field, label, order, width?}]';
COMMENT ON COLUMN export_templates.is_default IS 'Template par defaut pour ce type (un seul par type/tenant)';

-- Index pour recherche rapide
CREATE INDEX IF NOT EXISTS idx_export_templates_tenant ON export_templates(tenant_id);
CREATE INDEX IF NOT EXISTS idx_export_templates_type ON export_templates(tenant_id, export_type);
CREATE INDEX IF NOT EXISTS idx_export_templates_default ON export_templates(tenant_id, export_type, is_default)
    WHERE is_default = true;

-- Trigger pour updated_at
DROP TRIGGER IF EXISTS trg_export_templates_updated_at ON export_templates;
CREATE TRIGGER trg_export_templates_updated_at
    BEFORE UPDATE ON export_templates FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Fonction pour assurer un seul template par defaut par type
-- ============================================================
CREATE OR REPLACE FUNCTION ensure_single_default_export_template()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.is_default = true THEN
        UPDATE export_templates
        SET is_default = false, updated_at = now()
        WHERE tenant_id = NEW.tenant_id
          AND export_type = NEW.export_type
          AND is_default = true
          AND id != NEW.id;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_export_templates_single_default ON export_templates;
CREATE TRIGGER trg_export_templates_single_default
    BEFORE INSERT OR UPDATE OF is_default ON export_templates
    FOR EACH ROW
    WHEN (NEW.is_default = true)
    EXECUTE FUNCTION ensure_single_default_export_template();

COMMIT;
