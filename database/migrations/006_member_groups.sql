-- Migration 006: Groupes et categories de membres
-- Permet de regrouper les membres (colleges electoraux, departements, categories)
--
-- Usage:
--   psql -U agvote -d agvote -f 006_member_groups.sql

BEGIN;

-- ============================================================
-- Table: member_groups
-- Stocke les definitions de groupes par tenant
-- ============================================================
CREATE TABLE IF NOT EXISTS member_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6366f1',  -- Couleur hex pour l'affichage
    sort_order INT DEFAULT 0,             -- Ordre d'affichage
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT member_groups_unique_name UNIQUE (tenant_id, name),
    CONSTRAINT member_groups_color_format CHECK (color ~ '^#[0-9A-Fa-f]{6}$')
);

CREATE INDEX IF NOT EXISTS idx_member_groups_tenant ON member_groups(tenant_id);
CREATE INDEX IF NOT EXISTS idx_member_groups_tenant_active ON member_groups(tenant_id) WHERE is_active = true;

-- Trigger pour updated_at
DROP TRIGGER IF EXISTS trg_member_groups_updated_at ON member_groups;
CREATE TRIGGER trg_member_groups_updated_at
    BEFORE UPDATE ON member_groups FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Table: member_group_assignments
-- Relation N:N entre membres et groupes
-- Un membre peut appartenir a plusieurs groupes
-- ============================================================
CREATE TABLE IF NOT EXISTS member_group_assignments (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    member_id UUID NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    group_id UUID NOT NULL REFERENCES member_groups(id) ON DELETE CASCADE,
    assigned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    assigned_by UUID REFERENCES users(id) ON DELETE SET NULL,

    CONSTRAINT member_group_assignments_unique UNIQUE (member_id, group_id)
);

CREATE INDEX IF NOT EXISTS idx_member_group_assignments_member ON member_group_assignments(member_id);
CREATE INDEX IF NOT EXISTS idx_member_group_assignments_group ON member_group_assignments(group_id);

-- ============================================================
-- Vue: member_groups_with_count
-- Groupes avec le nombre de membres
-- ============================================================
CREATE OR REPLACE VIEW member_groups_with_count AS
SELECT
    mg.id,
    mg.tenant_id,
    mg.name,
    mg.description,
    mg.color,
    mg.sort_order,
    mg.is_active,
    mg.created_at,
    mg.updated_at,
    COUNT(mga.member_id) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL) AS member_count,
    COALESCE(SUM(COALESCE(m.voting_power, 1.0)) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL), 0) AS total_weight
FROM member_groups mg
LEFT JOIN member_group_assignments mga ON mga.group_id = mg.id
LEFT JOIN members m ON m.id = mga.member_id
GROUP BY mg.id, mg.tenant_id, mg.name, mg.description, mg.color, mg.sort_order, mg.is_active, mg.created_at, mg.updated_at;

-- ============================================================
-- Vue: members_with_groups
-- Membres avec leurs groupes concatenes
-- ============================================================
CREATE OR REPLACE VIEW members_with_groups AS
SELECT
    m.*,
    COALESCE(
        STRING_AGG(mg.name, ', ' ORDER BY mg.sort_order, mg.name),
        ''
    ) AS group_names,
    COALESCE(
        ARRAY_AGG(mg.id ORDER BY mg.sort_order, mg.name) FILTER (WHERE mg.id IS NOT NULL),
        ARRAY[]::uuid[]
    ) AS group_ids
FROM members m
LEFT JOIN member_group_assignments mga ON mga.member_id = m.id
LEFT JOIN member_groups mg ON mg.id = mga.group_id AND mg.is_active = true
GROUP BY m.id;

-- ============================================================
-- Commentaires
-- ============================================================
COMMENT ON TABLE member_groups IS 'Groupes et categories de membres (colleges, departements, etc.)';
COMMENT ON COLUMN member_groups.color IS 'Couleur hexadecimale pour l affichage (#RRGGBB)';
COMMENT ON COLUMN member_groups.sort_order IS 'Ordre d affichage (plus petit = plus haut)';

COMMENT ON TABLE member_group_assignments IS 'Relation N:N entre membres et groupes';

COMMIT;
