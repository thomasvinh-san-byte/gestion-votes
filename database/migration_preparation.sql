-- migration_preparation.sql
-- Module de preparation de seance
-- Tables: preparation_checklists, preparation_documents, preparation_convocations

BEGIN;

-- ============================================================
-- Checklist de preparation
-- ============================================================
CREATE TABLE IF NOT EXISTS preparation_checklist_items (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  title text NOT NULL,
  description text,
  category text NOT NULL DEFAULT 'general',
  sort_order integer NOT NULL DEFAULT 0,
  is_checked boolean NOT NULL DEFAULT false,
  checked_at timestamptz,
  checked_by_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  due_date date,
  assigned_to text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT checklist_category_check CHECK (category IN ('documents','convocations','logistique','ordre_du_jour','general'))
);
CREATE INDEX IF NOT EXISTS idx_prep_checklist_meeting ON preparation_checklist_items(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_prep_checklist_category ON preparation_checklist_items(meeting_id, category);

DROP TRIGGER IF EXISTS trg_prep_checklist_updated_at ON preparation_checklist_items;
CREATE TRIGGER trg_prep_checklist_updated_at
  BEFORE UPDATE ON preparation_checklist_items FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Documents de seance
-- ============================================================
CREATE TABLE IF NOT EXISTS preparation_documents (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  title text NOT NULL,
  description text,
  category text NOT NULL DEFAULT 'annexe',
  file_name text,
  file_size integer,
  file_type text,
  status text NOT NULL DEFAULT 'pending',
  requested_from text,
  requested_at timestamptz,
  received_at timestamptz,
  deadline date,
  uploaded_by_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  sort_order integer NOT NULL DEFAULT 0,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT doc_category_check CHECK (category IN ('convocation','ordre_du_jour','annexe','rapport','budget','pv_precedent','autre')),
  CONSTRAINT doc_status_check CHECK (status IN ('pending','requested','received','approved','rejected'))
);
CREATE INDEX IF NOT EXISTS idx_prep_docs_meeting ON preparation_documents(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_prep_docs_status ON preparation_documents(meeting_id, status);

DROP TRIGGER IF EXISTS trg_prep_docs_updated_at ON preparation_documents;
CREATE TRIGGER trg_prep_docs_updated_at
  BEFORE UPDATE ON preparation_documents FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Convocations
-- ============================================================
CREATE TABLE IF NOT EXISTS preparation_convocations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  method text NOT NULL DEFAULT 'email',
  status text NOT NULL DEFAULT 'draft',
  sent_at timestamptz,
  opened_at timestamptz,
  confirmed_at timestamptz,
  declined_at timestamptz,
  confirmation_response text,
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT convoc_method_check CHECK (method IN ('email','courrier','main_propre','affichage')),
  CONSTRAINT convoc_status_check CHECK (status IN ('draft','sent','opened','confirmed','declined','bounced')),
  UNIQUE (tenant_id, meeting_id, member_id)
);
CREATE INDEX IF NOT EXISTS idx_prep_convoc_meeting ON preparation_convocations(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_prep_convoc_status ON preparation_convocations(meeting_id, status);

DROP TRIGGER IF EXISTS trg_prep_convoc_updated_at ON preparation_convocations;
CREATE TRIGGER trg_prep_convoc_updated_at
  BEFORE UPDATE ON preparation_convocations FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Ajouter champs meeting pour preparation
-- ============================================================
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS convocation_date date;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS convocation_deadline date;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS preparation_notes text;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS preparation_status text DEFAULT 'not_started';

COMMIT;
