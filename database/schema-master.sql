-- ============================================================
-- AG-VOTE - SCRIPT-MAITRE BASE DE DONNEES
-- ============================================================
-- Version: 2026-02-04
--
-- Ce script unifie le schema de base et toutes les migrations.
-- Il est idempotent (peut etre relance sans effet si deja applique).
--
-- Usage:
--   psql -U agvote -d agvote -f database/schema-master.sql
--
-- Ce fichier remplace:
--   - database/schema.sql (schema de base)
--   - database/migrations/*.sql (toutes les migrations)
-- ============================================================

-- Extensions requises
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;

-- ============================================================
-- TYPES ENUMERES
-- ============================================================
DO $$
BEGIN
  -- meeting_status: etats de la seance
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'meeting_status') THEN
    CREATE TYPE meeting_status AS ENUM ('draft','scheduled','frozen','live','closed','validated','archived');
  ELSE
    -- Ajouter les valeurs manquantes si necessaire
    BEGIN
      ALTER TYPE meeting_status ADD VALUE IF NOT EXISTS 'frozen' BEFORE 'live';
    EXCEPTION WHEN others THEN NULL;
    END;
    BEGIN
      ALTER TYPE meeting_status ADD VALUE IF NOT EXISTS 'validated' BEFORE 'archived';
    EXCEPTION WHEN others THEN NULL;
    END;
  END IF;

  -- attendance_mode: modes de presence
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'attendance_mode') THEN
    CREATE TYPE attendance_mode AS ENUM ('present','remote','proxy','excused');
  ELSE
    BEGIN
      ALTER TYPE attendance_mode ADD VALUE IF NOT EXISTS 'excused';
    EXCEPTION WHEN others THEN NULL;
    END;
  END IF;

  -- proxy_scope: portee de la procuration
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'proxy_scope') THEN
    CREATE TYPE proxy_scope AS ENUM ('full','agenda_items');
  END IF;

  -- motion_value: valeurs de vote
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'motion_value') THEN
    CREATE TYPE motion_value AS ENUM ('for','against','abstain','nsp');
  END IF;
END $$;

-- ============================================================
-- FONCTIONS UTILITAIRES
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS trigger AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Fonction de generation de slug
CREATE OR REPLACE FUNCTION generate_slug(title TEXT, uuid_val UUID)
RETURNS TEXT AS $$
DECLARE
    base_slug TEXT;
    suffix TEXT;
BEGIN
    base_slug := lower(translate(title, 'àâäéèêëîïôùûüçÀÂÄÉÈÊËÎÏÔÙÛÜÇ', 'aaaeeeeiioouucaaaeeeeiioouuc'));
    base_slug := regexp_replace(base_slug, '[^a-z0-9]+', '-', 'g');
    base_slug := trim(both '-' from base_slug);
    base_slug := left(base_slug, 40);
    suffix := encode(substring(uuid_val::text::bytea from 1 for 4), 'hex');
    RETURN base_slug || '-' || suffix;
END;
$$ LANGUAGE plpgsql IMMUTABLE;

-- ============================================================
-- TABLE: tenants
-- ============================================================
CREATE TABLE IF NOT EXISTS tenants (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  name text NOT NULL,
  slug text UNIQUE,
  timezone text DEFAULT 'Europe/Paris',
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_tenants_slug ON tenants(slug);

DROP TRIGGER IF EXISTS trg_tenants_updated_at ON tenants;
CREATE TRIGGER trg_tenants_updated_at
  BEFORE UPDATE ON tenants FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  email citext,
  name text,
  display_name text,
  role text NOT NULL DEFAULT 'viewer',
  password_hash text,
  api_key_hash char(64),
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

-- Contrainte de role (roles SYSTEME uniquement)
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
ALTER TABLE users ADD CONSTRAINT users_role_check
  CHECK (role IN ('admin','operator','auditor','viewer'));

CREATE UNIQUE INDEX IF NOT EXISTS ux_users_tenant_email ON users(tenant_id, email) WHERE email IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_tenant_api_key_hash ON users(tenant_id, api_key_hash) WHERE api_key_hash IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_users_tenant_role ON users(tenant_id, role);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
  BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at();

COMMENT ON COLUMN users.password_hash IS 'Hash bcrypt/argon2 du mot de passe. NULL si auth par cle API uniquement.';

-- ============================================================
-- TABLE: member_groups
-- ============================================================
CREATE TABLE IF NOT EXISTS member_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6366f1',
    sort_order INT DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    CONSTRAINT member_groups_unique_name UNIQUE (tenant_id, name),
    CONSTRAINT member_groups_color_format CHECK (color ~ '^#[0-9A-Fa-f]{6}$')
);

CREATE INDEX IF NOT EXISTS idx_member_groups_tenant ON member_groups(tenant_id);
CREATE INDEX IF NOT EXISTS idx_member_groups_tenant_active ON member_groups(tenant_id) WHERE is_active = true;

DROP TRIGGER IF EXISTS trg_member_groups_updated_at ON member_groups;
CREATE TRIGGER trg_member_groups_updated_at
    BEFORE UPDATE ON member_groups FOR EACH ROW EXECUTE FUNCTION update_updated_at();

COMMENT ON TABLE member_groups IS 'Groupes et categories de membres (colleges, departements, etc.)';

-- ============================================================
-- TABLE: members
-- ============================================================
CREATE TABLE IF NOT EXISTS members (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  external_ref text,
  full_name text NOT NULL,
  name text,
  email citext,
  role text,
  -- NOTE: vote_weight is legacy, voting_power is the new standard.
  -- Code uses COALESCE(voting_power, vote_weight, 1.0) for compatibility.
  -- Future migration will unify to voting_power only.
  vote_weight numeric(12,4) NOT NULL DEFAULT 1.0,
  voting_power numeric(12,4),
  is_active boolean NOT NULL DEFAULT true,
  deleted_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT members_vote_weight_positive CHECK (vote_weight >= 0)
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_members_tenant_full_name ON members(tenant_id, full_name);
CREATE UNIQUE INDEX IF NOT EXISTS ux_members_tenant_external_ref ON members(tenant_id, external_ref);
CREATE INDEX IF NOT EXISTS idx_members_tenant_active ON members(tenant_id, is_active) WHERE deleted_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_members_name ON members(tenant_id, lower(full_name));

DROP TRIGGER IF EXISTS trg_members_updated_at ON members;
CREATE TRIGGER trg_members_updated_at
  BEFORE UPDATE ON members FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- TABLE: member_group_assignments
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
-- TABLE: quorum_policies
-- ============================================================
CREATE TABLE IF NOT EXISTS quorum_policies (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name text NOT NULL,
  description text,
  mode text NOT NULL DEFAULT 'single',
  denominator text NOT NULL DEFAULT 'eligible_members',
  threshold numeric(6,5) NOT NULL,
  threshold_call2 numeric(6,5),
  denominator2 text,
  threshold2 numeric(6,5),
  include_proxies boolean NOT NULL DEFAULT true,
  count_remote boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT quorum_mode_check CHECK (mode IN ('single','evolving','double')),
  CONSTRAINT quorum_den_check CHECK (denominator IN ('eligible_members','eligible_weight')),
  CONSTRAINT quorum_den2_check CHECK (denominator2 IS NULL OR denominator2 IN ('eligible_members','eligible_weight')),
  CONSTRAINT quorum_threshold_check CHECK (threshold >= 0 AND threshold <= 1),
  CONSTRAINT quorum_threshold_call2_check CHECK (threshold_call2 IS NULL OR (threshold_call2 >= 0 AND threshold_call2 <= 1)),
  CONSTRAINT quorum_threshold2_check CHECK (threshold2 IS NULL OR (threshold2 >= 0 AND threshold2 <= 1)),
  CONSTRAINT quorum_policies_unique_name UNIQUE (tenant_id, name)
);

CREATE INDEX IF NOT EXISTS idx_quorum_policies_tenant ON quorum_policies(tenant_id);

DROP TRIGGER IF EXISTS trg_quorum_policies_updated_at ON quorum_policies;
CREATE TRIGGER trg_quorum_policies_updated_at
  BEFORE UPDATE ON quorum_policies FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- TABLE: vote_policies
-- ============================================================
CREATE TABLE IF NOT EXISTS vote_policies (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name text NOT NULL,
  description text,
  base text NOT NULL DEFAULT 'expressed',
  threshold numeric(6,5) NOT NULL,
  abstention_as_against boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT vote_base_check CHECK (base IN ('expressed','total_eligible')),
  CONSTRAINT vote_threshold_check CHECK (threshold >= 0 AND threshold <= 1),
  CONSTRAINT vote_policies_unique_name UNIQUE (tenant_id, name)
);

CREATE INDEX IF NOT EXISTS idx_vote_policies_tenant ON vote_policies(tenant_id);

DROP TRIGGER IF EXISTS trg_vote_policies_updated_at ON vote_policies;
CREATE TRIGGER trg_vote_policies_updated_at
  BEFORE UPDATE ON vote_policies FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- TABLE: meetings
-- ============================================================
CREATE TABLE IF NOT EXISTS meetings (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  title text NOT NULL,
  slug text,
  description text,
  status meeting_status NOT NULL DEFAULT 'draft',
  scheduled_at timestamptz,
  started_at timestamptz,
  ended_at timestamptz,
  location text,
  notes text,
  quorum_policy_id uuid REFERENCES quorum_policies(id) ON DELETE SET NULL,
  vote_policy_id uuid REFERENCES vote_policies(id) ON DELETE SET NULL,
  current_motion_id uuid,
  president_name text,
  president_member_id uuid,
  president_source text,
  convocation_no text,
  validated_by text,
  validated_by_user_id uuid,
  validated_at timestamptz,
  archived_at timestamptz,
  frozen_at timestamptz,
  frozen_by uuid,
  opened_by uuid,
  closed_by uuid,
  late_rule_quorum boolean NOT NULL DEFAULT true,
  late_rule_vote boolean NOT NULL DEFAULT true,
  ready_to_sign boolean DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT meetings_times_ok CHECK (
    (started_at IS NULL OR scheduled_at IS NULL OR started_at >= scheduled_at)
    AND (ended_at IS NULL OR started_at IS NULL OR ended_at >= started_at)
  )
);

CREATE INDEX IF NOT EXISTS idx_meetings_tenant ON meetings(tenant_id);
CREATE INDEX IF NOT EXISTS idx_meetings_status ON meetings(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_meetings_time ON meetings(tenant_id, COALESCE(started_at, scheduled_at, created_at));
CREATE INDEX IF NOT EXISTS idx_meetings_frozen ON meetings(tenant_id) WHERE status = 'frozen';
CREATE INDEX IF NOT EXISTS idx_meetings_validated ON meetings(tenant_id) WHERE status = 'validated';
CREATE UNIQUE INDEX IF NOT EXISTS ux_meetings_tenant_slug ON meetings(tenant_id, slug);

DROP TRIGGER IF EXISTS trg_meetings_updated_at ON meetings;
CREATE TRIGGER trg_meetings_updated_at
  BEFORE UPDATE ON meetings FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- Trigger auto-generation slug
CREATE OR REPLACE FUNCTION auto_generate_meeting_slug()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.slug IS NULL THEN
        NEW.slug := generate_slug(NEW.title, NEW.id);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_meetings_auto_slug ON meetings;
CREATE TRIGGER trg_meetings_auto_slug
    BEFORE INSERT ON meetings
    FOR EACH ROW
    EXECUTE FUNCTION auto_generate_meeting_slug();

-- FKs ajoutees apres creation des tables dependantes
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='meetings' AND constraint_name='meetings_president_member_id_fkey'
  ) THEN
    ALTER TABLE meetings ADD CONSTRAINT meetings_president_member_id_fkey
      FOREIGN KEY (president_member_id) REFERENCES members(id) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='meetings' AND constraint_name='meetings_validated_by_user_id_fkey'
  ) THEN
    ALTER TABLE meetings ADD CONSTRAINT meetings_validated_by_user_id_fkey
      FOREIGN KEY (validated_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='meetings' AND constraint_name='meetings_frozen_by_fkey'
  ) THEN
    ALTER TABLE meetings ADD CONSTRAINT meetings_frozen_by_fkey
      FOREIGN KEY (frozen_by) REFERENCES users(id) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='meetings' AND constraint_name='meetings_opened_by_fkey'
  ) THEN
    ALTER TABLE meetings ADD CONSTRAINT meetings_opened_by_fkey
      FOREIGN KEY (opened_by) REFERENCES users(id) ON DELETE SET NULL;
  END IF;
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='meetings' AND constraint_name='meetings_closed_by_fkey'
  ) THEN
    ALTER TABLE meetings ADD CONSTRAINT meetings_closed_by_fkey
      FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL;
  END IF;
END $$;

COMMENT ON COLUMN meetings.slug IS 'Identifiant URL court et opaque pour cette seance';

-- ============================================================
-- TABLE: agendas
-- ============================================================
CREATE TABLE IF NOT EXISTS agendas (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  idx integer NOT NULL CHECK (idx > 0),
  title text NOT NULL,
  description text,
  is_approved boolean DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, meeting_id, idx)
);

CREATE INDEX IF NOT EXISTS idx_agendas_meeting ON agendas(tenant_id, meeting_id);

DROP TRIGGER IF EXISTS trg_agendas_updated_at ON agendas;
CREATE TRIGGER trg_agendas_updated_at
  BEFORE UPDATE ON agendas FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- TABLE: motions
-- ============================================================
CREATE TABLE IF NOT EXISTS motions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  agenda_id uuid REFERENCES agendas(id) ON DELETE SET NULL,
  title text NOT NULL,
  slug text,
  description text,
  body text,
  secret boolean NOT NULL DEFAULT false,
  status text,
  position integer,
  sort_order integer,
  vote_policy_id uuid REFERENCES vote_policies(id) ON DELETE SET NULL,
  quorum_policy_id uuid REFERENCES quorum_policies(id) ON DELETE SET NULL,
  opened_at timestamptz,
  closed_at timestamptz,
  tally_status text,
  decision text,
  decision_reason text,
  evote_results jsonb,
  manual_tally jsonb,
  manual_total integer,
  manual_for integer,
  manual_against integer,
  manual_abstain integer,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT motions_times_ok CHECK (closed_at IS NULL OR opened_at IS NULL OR closed_at >= opened_at)
);

CREATE INDEX IF NOT EXISTS idx_motions_meeting ON motions(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_motions_open ON motions(tenant_id, meeting_id, opened_at) WHERE closed_at IS NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_motions_meeting_slug ON motions(meeting_id, slug);

DROP TRIGGER IF EXISTS trg_motions_updated_at ON motions;
CREATE TRIGGER trg_motions_updated_at
  BEFORE UPDATE ON motions FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- Trigger body depuis description
CREATE OR REPLACE FUNCTION motions_body_from_description()
RETURNS trigger AS $$
BEGIN
  IF NEW.body IS NULL OR NEW.body = '' THEN
    NEW.body := NEW.description;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_motions_body_sync ON motions;
CREATE TRIGGER trg_motions_body_sync
  BEFORE INSERT OR UPDATE ON motions
  FOR EACH ROW EXECUTE FUNCTION motions_body_from_description();

-- Trigger auto-generation slug
CREATE OR REPLACE FUNCTION auto_generate_motion_slug()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.slug IS NULL THEN
        NEW.slug := generate_slug(NEW.title, NEW.id);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_motions_auto_slug ON motions;
CREATE TRIGGER trg_motions_auto_slug
    BEFORE INSERT ON motions
    FOR EACH ROW
    EXECUTE FUNCTION auto_generate_motion_slug();

COMMENT ON COLUMN motions.slug IS 'Identifiant URL court et opaque pour cette resolution';

-- FK meeting.current_motion_id -> motions.id
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='meetings' AND constraint_name='meetings_current_motion_id_fkey'
  ) THEN
    ALTER TABLE meetings
      ADD CONSTRAINT meetings_current_motion_id_fkey
      FOREIGN KEY (current_motion_id) REFERENCES motions(id) ON DELETE SET NULL;
  END IF;
END $$;

-- ============================================================
-- TABLE: email_templates
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
-- TABLE: invitations
-- ============================================================
CREATE TABLE IF NOT EXISTS invitations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  email citext,
  token text UNIQUE,
  token_hash char(64),
  status text NOT NULL DEFAULT 'pending',
  sent_at timestamptz,
  responded_at timestamptz,
  response_notes text,
  opened_at timestamptz,
  clicked_at timestamptz,
  open_count int DEFAULT 0,
  click_count int DEFAULT 0,
  last_error text,
  template_id uuid REFERENCES email_templates(id) ON DELETE SET NULL,
  revoked_at timestamptz,
  last_used_at timestamptz,
  expires_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT invitations_status_check CHECK (status IN ('pending','sent','opened','accepted','declined','bounced')),
  UNIQUE (tenant_id, meeting_id, member_id)
);

CREATE INDEX IF NOT EXISTS idx_invitations_meeting ON invitations(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_invitations_token ON invitations(token) WHERE token IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_invitations_token_hash ON invitations(token_hash);

DROP TRIGGER IF EXISTS trg_invitations_updated_at ON invitations;
CREATE TRIGGER trg_invitations_updated_at
  BEFORE UPDATE ON invitations FOR EACH ROW EXECUTE FUNCTION update_updated_at();

COMMENT ON COLUMN invitations.token_hash IS 'Hash SHA256 du token d''invitation (securite)';
COMMENT ON COLUMN invitations.expires_at IS 'Date d''expiration de l''invitation';

-- ============================================================
-- TABLE: proxies
-- ============================================================
CREATE TABLE IF NOT EXISTS proxies (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  giver_member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  receiver_member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  scope proxy_scope NOT NULL DEFAULT 'full',
  agenda_limits text[],
  created_at timestamptz NOT NULL DEFAULT now(),
  revoked_at timestamptz,
  CHECK (giver_member_id <> receiver_member_id),
  UNIQUE (tenant_id, meeting_id, giver_member_id)
);

CREATE INDEX IF NOT EXISTS idx_proxies_meeting_receiver ON proxies(tenant_id, meeting_id, receiver_member_id);
CREATE INDEX IF NOT EXISTS idx_proxies_meeting_active ON proxies(tenant_id, meeting_id) WHERE revoked_at IS NULL;

-- ============================================================
-- TABLE: attendances
-- ============================================================
CREATE TABLE IF NOT EXISTS attendances (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  mode attendance_mode NOT NULL,
  checked_in_at timestamptz NOT NULL DEFAULT now(),
  checked_out_at timestamptz,
  present_from_at timestamptz,
  effective_power numeric(12,4),
  notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  UNIQUE (tenant_id, meeting_id, member_id)
);

CREATE INDEX IF NOT EXISTS idx_attendances_meeting ON attendances(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_attendances_member ON attendances(tenant_id, member_id);

DROP TRIGGER IF EXISTS trg_attendances_updated_at ON attendances;
CREATE TRIGGER trg_attendances_updated_at
  BEFORE UPDATE ON attendances FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- TABLE: ballots
-- ============================================================
CREATE TABLE IF NOT EXISTS ballots (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE CASCADE,
  motion_id uuid NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
  member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  value motion_value NOT NULL,
  choice text,
  weight numeric(12,4) NOT NULL DEFAULT 1.0,
  effective_power numeric(12,4),
  source text,
  cast_at timestamptz NOT NULL DEFAULT now(),
  is_proxy_vote boolean DEFAULT false,
  proxy_source_member_id uuid REFERENCES members(id) ON DELETE SET NULL,
  UNIQUE (motion_id, member_id)
);

CREATE INDEX IF NOT EXISTS idx_ballots_tenant_meeting ON ballots(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_ballots_motion ON ballots(motion_id);

-- Remplir meeting_id/tenant_id si absent
CREATE OR REPLACE FUNCTION ballots_fill_context()
RETURNS trigger AS $$
DECLARE
  m record;
BEGIN
  SELECT meeting_id, tenant_id INTO m FROM motions WHERE id = NEW.motion_id LIMIT 1;
  IF NEW.meeting_id IS NULL THEN NEW.meeting_id := m.meeting_id; END IF;
  IF NEW.tenant_id IS NULL THEN NEW.tenant_id := m.tenant_id; END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_ballots_fill_context ON ballots;
CREATE TRIGGER trg_ballots_fill_context
  BEFORE INSERT OR UPDATE ON ballots
  FOR EACH ROW EXECUTE FUNCTION ballots_fill_context();

-- ============================================================
-- TABLE: audit_events (avec hash chain)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_events (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE SET NULL,
  actor_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  actor_role text,
  action text NOT NULL,
  resource_type text,
  resource_id uuid,
  payload jsonb NOT NULL DEFAULT '{}'::jsonb,
  ip_address inet,
  user_agent text,
  prev_hash bytea,
  this_hash bytea,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_audit_tenant_time ON audit_events(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_resource ON audit_events(resource_type, resource_id) WHERE resource_type IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_audit_events_action ON audit_events(action, created_at DESC);

CREATE OR REPLACE FUNCTION audit_events_compute_hash() RETURNS trigger AS $$
DECLARE
  prev bytea;
BEGIN
  SELECT this_hash INTO prev
  FROM audit_events
  WHERE tenant_id = NEW.tenant_id
  ORDER BY created_at DESC
  LIMIT 1;

  NEW.prev_hash := prev;
  NEW.this_hash := digest(
    coalesce(encode(NEW.prev_hash,'hex'),'') || '|' ||
    coalesce(NEW.tenant_id::text,'') || '|' ||
    coalesce(NEW.actor_user_id::text,'') || '|' ||
    coalesce(NEW.action,'') || '|' ||
    coalesce(NEW.resource_type,'') || '|' ||
    coalesce(NEW.resource_id::text,'') || '|' ||
    coalesce(NEW.payload::text,'') || '|' ||
    coalesce(NEW.created_at::text,''),
    'sha256'
  );
  RETURN NEW;
END; $$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_hash ON audit_events;
CREATE TRIGGER trg_audit_hash
  BEFORE INSERT ON audit_events
  FOR EACH ROW EXECUTE FUNCTION audit_events_compute_hash();

-- ============================================================
-- TABLE: email_queue
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

-- ============================================================
-- TABLE: reminder_schedules
-- ============================================================
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
-- TABLE: email_events
-- ============================================================
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

-- ============================================================
-- TABLE: meeting_notifications
-- ============================================================
CREATE TABLE IF NOT EXISTS meeting_notifications (
  id bigserial PRIMARY KEY,
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
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

-- ============================================================
-- TABLE: manual_actions (mode degrade)
-- ============================================================
CREATE TABLE IF NOT EXISTS manual_actions (
  id bigserial PRIMARY KEY,
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  motion_id uuid REFERENCES motions(id) ON DELETE SET NULL,
  resolution_id uuid,
  member_id uuid REFERENCES members(id) ON DELETE SET NULL,
  action_type text NOT NULL,
  value jsonb NOT NULL DEFAULT '{}'::jsonb,
  justification text,
  operator_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  operator_id uuid,
  signature_hash text,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_manual_actions_meeting ON manual_actions(meeting_id, created_at DESC);

-- ============================================================
-- TABLES SYSTEME
-- ============================================================
CREATE TABLE IF NOT EXISTS system_alerts (
  id bigserial PRIMARY KEY,
  created_at timestamptz NOT NULL DEFAULT now(),
  code text NOT NULL,
  severity text NOT NULL,
  message text NOT NULL,
  details_json jsonb
);
CREATE INDEX IF NOT EXISTS idx_system_alerts_created_at ON system_alerts(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_system_alerts_code ON system_alerts(code);

CREATE TABLE IF NOT EXISTS system_metrics (
  id bigserial PRIMARY KEY,
  created_at timestamptz NOT NULL DEFAULT now(),
  server_time timestamptz,
  db_latency_ms double precision,
  db_active_connections integer,
  disk_free_bytes bigint,
  disk_total_bytes bigint,
  count_meetings integer,
  count_motions integer,
  count_vote_tokens integer,
  count_audit_events integer,
  auth_failures_15m integer
);
CREATE INDEX IF NOT EXISTS idx_system_metrics_created_at ON system_metrics(created_at DESC);

CREATE TABLE IF NOT EXISTS auth_failures (
  id bigserial PRIMARY KEY,
  created_at timestamptz NOT NULL DEFAULT now(),
  ip text,
  user_agent text,
  key_prefix text,
  reason text
);
CREATE INDEX IF NOT EXISTS idx_auth_failures_created_at ON auth_failures(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_auth_failures_ip ON auth_failures(ip, created_at DESC);

CREATE TABLE IF NOT EXISTS vote_tokens (
  token_hash char(64) PRIMARY KEY,
  tenant_id uuid REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE CASCADE,
  member_id uuid REFERENCES members(id) ON DELETE CASCADE,
  motion_id uuid REFERENCES motions(id) ON DELETE CASCADE,
  expires_at timestamptz NOT NULL,
  used_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_vote_tokens_expires_at ON vote_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_vote_tokens_meeting_motion ON vote_tokens(meeting_id, motion_id);

CREATE TABLE IF NOT EXISTS device_heartbeats (
  device_id uuid PRIMARY KEY,
  tenant_id uuid REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE CASCADE,
  role text,
  ip text,
  user_agent text,
  battery_pct integer,
  is_charging boolean,
  last_seen_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_device_heartbeats_tenant_device ON device_heartbeats(tenant_id, device_id);
CREATE INDEX IF NOT EXISTS idx_device_heartbeats_last_seen ON device_heartbeats(last_seen_at DESC);

CREATE TABLE IF NOT EXISTS device_blocks (
  id bigserial PRIMARY KEY,
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE CASCADE,
  device_id uuid NOT NULL,
  is_blocked boolean NOT NULL DEFAULT true,
  reason text,
  blocked_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_device_blocks_scope_device
ON device_blocks ( (COALESCE(meeting_id, '00000000-0000-0000-0000-000000000000'::uuid)), device_id );
CREATE INDEX IF NOT EXISTS idx_device_blocks_lookup
ON device_blocks(tenant_id, device_id, updated_at DESC);

CREATE TABLE IF NOT EXISTS device_commands (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE CASCADE,
  device_id uuid NOT NULL,
  command text NOT NULL,
  payload jsonb,
  created_at timestamptz NOT NULL DEFAULT now(),
  consumed_at timestamptz
);
CREATE INDEX IF NOT EXISTS idx_device_commands_pending
ON device_commands(tenant_id, device_id, command, created_at DESC)
WHERE consumed_at IS NULL;

CREATE TABLE IF NOT EXISTS meeting_emergency_checks (
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  procedure_code text NOT NULL,
  item_index integer NOT NULL,
  checked boolean NOT NULL DEFAULT false,
  checked_at timestamptz,
  checked_by text,
  PRIMARY KEY(meeting_id, procedure_code, item_index)
);

CREATE TABLE IF NOT EXISTS meeting_reports (
  meeting_id uuid PRIMARY KEY REFERENCES meetings(id) ON DELETE CASCADE,
  html text NOT NULL,
  sha256 text,
  generated_at timestamptz,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS paper_ballots (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  motion_id uuid NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
  code text NOT NULL,
  code_hash char(64) NOT NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  used_at timestamptz,
  used_by_operator boolean NOT NULL DEFAULT false
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_paper_ballots_code_hash ON paper_ballots(code_hash);
CREATE INDEX IF NOT EXISTS idx_paper_ballots_unused ON paper_ballots(code_hash) WHERE used_at IS NULL;

CREATE TABLE IF NOT EXISTS emergency_procedures (
  code text PRIMARY KEY,
  title text NOT NULL,
  audience text NOT NULL,
  steps_json jsonb NOT NULL DEFAULT '[]'::jsonb,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_emergency_procedures_audience ON emergency_procedures(audience);

-- ============================================================
-- TABLES DE REFERENCE: transitions et permissions
-- ============================================================
CREATE TABLE IF NOT EXISTS meeting_state_transitions (
  from_status text NOT NULL,
  to_status text NOT NULL,
  required_role text NOT NULL,
  description text,
  PRIMARY KEY (from_status, to_status)
);

DELETE FROM meeting_state_transitions;
INSERT INTO meeting_state_transitions (from_status, to_status, required_role, description) VALUES
  ('draft',     'scheduled', 'operator',  'Planifier la seance (date, lieu, convocation)'),
  ('scheduled', 'frozen',    'president', 'Verrouiller la configuration (resolutions, membres)'),
  ('draft',     'frozen',    'president', 'Verrouiller directement depuis brouillon'),
  ('frozen',    'live',      'president', 'Ouvrir la seance (debut des votes)'),
  ('live',      'closed',    'president', 'Cloturer la seance (fin des votes)'),
  ('closed',    'validated', 'president', 'Valider les resultats et signer le PV'),
  ('validated', 'archived',  'admin',     'Archiver la seance (scellement definitif)'),
  ('frozen',    'scheduled', 'admin',     'Degeler la configuration (cas exceptionnel)'),
  ('scheduled', 'draft',     'admin',     'Repasser en brouillon (annulation planification)');

CREATE TABLE IF NOT EXISTS role_permissions (
  role text NOT NULL,
  permission text NOT NULL,
  description text,
  PRIMARY KEY (role, permission)
);

DELETE FROM role_permissions;
INSERT INTO role_permissions (role, permission, description) VALUES
  -- ADMIN (systeme)
  ('admin', 'meeting:create', 'Creer une seance'),
  ('admin', 'meeting:read', 'Consulter une seance'),
  ('admin', 'meeting:update', 'Modifier une seance'),
  ('admin', 'meeting:delete', 'Supprimer une seance'),
  ('admin', 'meeting:freeze', 'Verrouiller la configuration'),
  ('admin', 'meeting:open', 'Ouvrir la seance'),
  ('admin', 'meeting:close', 'Cloturer la seance'),
  ('admin', 'meeting:validate', 'Valider les resultats'),
  ('admin', 'meeting:archive', 'Archiver la seance'),
  ('admin', 'meeting:unfreeze', 'Degeler la configuration'),
  ('admin', 'meeting:assign_roles', 'Attribuer des roles de seance'),
  ('admin', 'motion:create', 'Creer une resolution'),
  ('admin', 'motion:read', 'Consulter les resolutions'),
  ('admin', 'motion:update', 'Modifier une resolution'),
  ('admin', 'motion:delete', 'Supprimer une resolution'),
  ('admin', 'motion:open', 'Ouvrir le vote'),
  ('admin', 'motion:close', 'Fermer le vote'),
  ('admin', 'vote:cast', 'Voter'),
  ('admin', 'vote:read', 'Consulter les resultats'),
  ('admin', 'vote:manual', 'Saisie manuelle de vote'),
  ('admin', 'member:create', 'Ajouter un membre'),
  ('admin', 'member:read', 'Consulter les membres'),
  ('admin', 'member:update', 'Modifier un membre'),
  ('admin', 'member:delete', 'Supprimer un membre'),
  ('admin', 'member:import', 'Importer des membres'),
  ('admin', 'attendance:create', 'Enregistrer une presence'),
  ('admin', 'attendance:read', 'Consulter les presences'),
  ('admin', 'attendance:update', 'Modifier une presence'),
  ('admin', 'proxy:create', 'Creer une procuration'),
  ('admin', 'proxy:read', 'Consulter les procurations'),
  ('admin', 'proxy:delete', 'Supprimer une procuration'),
  ('admin', 'audit:read', 'Consulter les logs d''audit'),
  ('admin', 'audit:export', 'Exporter les logs'),
  ('admin', 'report:generate', 'Generer le PV'),
  ('admin', 'report:read', 'Consulter le PV'),
  ('admin', 'report:export', 'Exporter le PV'),
  ('admin', 'admin:users', 'Gerer les utilisateurs'),
  ('admin', 'admin:policies', 'Gerer les politiques'),
  ('admin', 'admin:system', 'Consulter le statut systeme'),
  ('admin', 'admin:roles', 'Gerer les roles'),
  -- OPERATOR (systeme)
  ('operator', 'meeting:create', 'Creer une seance'),
  ('operator', 'meeting:read', 'Consulter une seance'),
  ('operator', 'meeting:update', 'Modifier une seance'),
  ('operator', 'meeting:archive', 'Archiver une seance validee'),
  ('operator', 'meeting:assign_roles', 'Attribuer des roles de seance'),
  ('operator', 'motion:create', 'Creer une resolution'),
  ('operator', 'motion:read', 'Consulter les resolutions'),
  ('operator', 'motion:update', 'Modifier une resolution'),
  ('operator', 'motion:delete', 'Supprimer une resolution'),
  ('operator', 'motion:open', 'Ouvrir le vote'),
  ('operator', 'motion:close', 'Fermer le vote'),
  ('operator', 'vote:cast', 'Voter'),
  ('operator', 'vote:read', 'Consulter les resultats'),
  ('operator', 'vote:manual', 'Saisie manuelle'),
  ('operator', 'member:create', 'Ajouter un membre'),
  ('operator', 'member:read', 'Consulter les membres'),
  ('operator', 'member:update', 'Modifier un membre'),
  ('operator', 'member:import', 'Importer des membres'),
  ('operator', 'attendance:create', 'Enregistrer une presence'),
  ('operator', 'attendance:read', 'Consulter les presences'),
  ('operator', 'attendance:update', 'Modifier une presence'),
  ('operator', 'proxy:create', 'Creer une procuration'),
  ('operator', 'proxy:read', 'Consulter les procurations'),
  ('operator', 'proxy:delete', 'Supprimer une procuration'),
  ('operator', 'report:generate', 'Generer le PV'),
  ('operator', 'report:read', 'Consulter le PV'),
  ('operator', 'report:export', 'Exporter le PV'),
  -- AUDITOR (systeme)
  ('auditor', 'meeting:read', 'Consulter une seance'),
  ('auditor', 'motion:read', 'Consulter les resolutions'),
  ('auditor', 'vote:read', 'Consulter les resultats'),
  ('auditor', 'member:read', 'Consulter les membres'),
  ('auditor', 'attendance:read', 'Consulter les presences'),
  ('auditor', 'proxy:read', 'Consulter les procurations'),
  ('auditor', 'audit:read', 'Consulter les logs'),
  ('auditor', 'audit:export', 'Exporter les logs'),
  ('auditor', 'report:read', 'Consulter le PV'),
  ('auditor', 'report:export', 'Exporter le PV'),
  -- VIEWER (systeme)
  ('viewer', 'meeting:read', 'Consulter une seance'),
  ('viewer', 'motion:read', 'Consulter les resolutions'),
  ('viewer', 'attendance:read', 'Consulter les presences'),
  ('viewer', 'report:read', 'Consulter le PV'),
  -- PRESIDENT (seance)
  ('president', 'meeting:read', 'Consulter la seance'),
  ('president', 'meeting:freeze', 'Verrouiller la configuration'),
  ('president', 'meeting:open', 'Ouvrir la seance'),
  ('president', 'meeting:close', 'Cloturer la seance'),
  ('president', 'meeting:validate', 'Valider le PV'),
  ('president', 'motion:read', 'Consulter les resolutions'),
  ('president', 'motion:close', 'Fermer le vote'),
  ('president', 'vote:read', 'Consulter les resultats'),
  ('president', 'member:read', 'Consulter les membres'),
  ('president', 'attendance:read', 'Consulter les presences'),
  ('president', 'proxy:read', 'Consulter les procurations'),
  ('president', 'audit:read', 'Consulter les logs'),
  ('president', 'audit:export', 'Exporter les logs'),
  ('president', 'report:generate', 'Generer le PV'),
  ('president', 'report:read', 'Consulter le PV'),
  ('president', 'report:export', 'Exporter le PV'),
  -- ASSESSOR (seance)
  ('assessor', 'meeting:read', 'Consulter la seance'),
  ('assessor', 'motion:read', 'Consulter les resolutions'),
  ('assessor', 'vote:read', 'Consulter les resultats'),
  ('assessor', 'member:read', 'Consulter les membres'),
  ('assessor', 'attendance:read', 'Consulter les presences'),
  ('assessor', 'proxy:read', 'Consulter les procurations'),
  ('assessor', 'audit:read', 'Consulter les logs'),
  ('assessor', 'report:read', 'Consulter le PV'),
  -- VOTER (seance)
  ('voter', 'meeting:read', 'Consulter la seance'),
  ('voter', 'motion:read', 'Consulter les resolutions'),
  ('voter', 'vote:cast', 'Voter');

-- ============================================================
-- TABLE: meeting_roles (roles de seance)
-- ============================================================
CREATE TABLE IF NOT EXISTS meeting_roles (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  user_id uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role text NOT NULL,
  assigned_by uuid REFERENCES users(id) ON DELETE SET NULL,
  assigned_at timestamptz NOT NULL DEFAULT now(),
  revoked_at timestamptz,
  CONSTRAINT meeting_roles_role_check CHECK (role IN ('president','assessor','voter')),
  CONSTRAINT meeting_roles_unique_active UNIQUE (tenant_id, meeting_id, user_id, role)
);

CREATE INDEX IF NOT EXISTS idx_meeting_roles_meeting ON meeting_roles(tenant_id, meeting_id) WHERE revoked_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_meeting_roles_user ON meeting_roles(tenant_id, user_id) WHERE revoked_at IS NULL;

-- ============================================================
-- PROTECTION POST-VALIDATION
-- ============================================================
CREATE OR REPLACE FUNCTION prevent_changes_after_meeting_validation()
RETURNS trigger AS $$
DECLARE
  v_validated timestamptz;
  v_meeting_id uuid;
BEGIN
  v_meeting_id := COALESCE(NEW.meeting_id, OLD.meeting_id);

  SELECT validated_at INTO v_validated
  FROM meetings
  WHERE id = v_meeting_id;

  IF v_validated IS NOT NULL THEN
    RAISE EXCEPTION 'Meeting % is validated and cannot be modified', v_meeting_id
      USING ERRCODE = 'check_violation';
  END IF;

  IF TG_OP = 'DELETE' THEN
    RETURN OLD;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_no_motion_update_after_validation ON motions;
CREATE TRIGGER trg_no_motion_update_after_validation
BEFORE UPDATE OR DELETE ON motions
FOR EACH ROW EXECUTE FUNCTION prevent_changes_after_meeting_validation();

DROP TRIGGER IF EXISTS trg_no_ballot_change_after_validation ON ballots;
CREATE TRIGGER trg_no_ballot_change_after_validation
BEFORE INSERT OR UPDATE OR DELETE ON ballots
FOR EACH ROW EXECUTE FUNCTION prevent_changes_after_meeting_validation();

DROP TRIGGER IF EXISTS trg_no_attendance_change_after_validation ON attendances;
CREATE TRIGGER trg_no_attendance_change_after_validation
BEFORE INSERT OR UPDATE OR DELETE ON attendances
FOR EACH ROW EXECUTE FUNCTION prevent_changes_after_meeting_validation();

-- ============================================================
-- VUES UTILITAIRES
-- ============================================================

-- Stats email par seance
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

-- Groupes avec compteurs
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
    COALESCE(SUM(m.vote_weight) FILTER (WHERE m.is_active = true AND m.deleted_at IS NULL), 0) AS total_weight
FROM member_groups mg
LEFT JOIN member_group_assignments mga ON mga.group_id = mg.id
LEFT JOIN members m ON m.id = mga.member_id
GROUP BY mg.id, mg.tenant_id, mg.name, mg.description, mg.color, mg.sort_order, mg.is_active, mg.created_at, mg.updated_at;

-- Membres avec leurs groupes
CREATE OR REPLACE VIEW members_with_groups AS
SELECT
    m.*,
    COALESCE(STRING_AGG(mg.name, ', ' ORDER BY mg.sort_order, mg.name), '') AS group_names,
    COALESCE(ARRAY_AGG(mg.id ORDER BY mg.sort_order, mg.name) FILTER (WHERE mg.id IS NOT NULL), ARRAY[]::uuid[]) AS group_ids
FROM members m
LEFT JOIN member_group_assignments mga ON mga.member_id = m.id
LEFT JOIN member_groups mg ON mg.id = mga.group_id AND mg.is_active = true
GROUP BY m.id;

-- ============================================================
-- MIGRATION DES DONNEES EXISTANTES
-- ============================================================

-- Generer les slugs manquants pour meetings
UPDATE meetings SET slug = generate_slug(title, id) WHERE slug IS NULL;

-- Generer les slugs manquants pour motions
UPDATE motions SET slug = generate_slug(title, id) WHERE slug IS NULL;

-- ============================================================
-- PROTECTION DE LA PISTE D'AUDIT (immutabilite)
-- ============================================================

CREATE OR REPLACE FUNCTION prevent_audit_delete()
RETURNS TRIGGER AS $$
BEGIN
  RAISE EXCEPTION 'Suppression interdite sur audit_events : la piste d''audit est immuable';
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_audit_no_delete ON audit_events;

CREATE TRIGGER trg_audit_no_delete
  BEFORE DELETE ON audit_events
  FOR EACH ROW
  EXECUTE FUNCTION prevent_audit_delete();

-- ============================================================
-- FIN DU SCRIPT-MAITRE
-- ============================================================
