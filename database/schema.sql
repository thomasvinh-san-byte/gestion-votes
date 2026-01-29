-- database/schema.sql
-- Source de vérité PostgreSQL (compatible endpoints PHP /public/api/v1)
-- Installation idempotente (psql -f).

BEGIN;

-- Extensions
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS citext;

-- ============================================================
-- Types
-- ============================================================
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'meeting_status') THEN
    CREATE TYPE meeting_status AS ENUM ('draft','scheduled','live','closed','archived');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'attendance_mode') THEN
    CREATE TYPE attendance_mode AS ENUM ('present','remote','proxy');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'proxy_scope') THEN
    CREATE TYPE proxy_scope AS ENUM ('full','agenda_items');
  END IF;
  IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'motion_value') THEN
    CREATE TYPE motion_value AS ENUM ('for','against','abstain','nsp');
  END IF;
END $$;

-- ============================================================
-- Triggers utilitaires
-- ============================================================
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS trigger AS $$
BEGIN
  NEW.updated_at = now();
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- Tenants
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
-- Users (API key + RBAC)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  email citext,
  name text,
  role text NOT NULL DEFAULT 'operator',
  api_key_hash char(64),
  is_active boolean NOT NULL DEFAULT true,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT users_role_check CHECK (role IN ('admin','operator','president','trust','viewer','readonly','voter'))
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_tenant_email ON users(tenant_id, email) WHERE email IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_users_tenant_api_key_hash ON users(tenant_id, api_key_hash) WHERE api_key_hash IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_users_tenant_role ON users(tenant_id, role);

DROP TRIGGER IF EXISTS trg_users_updated_at ON users;
CREATE TRIGGER trg_users_updated_at
  BEFORE UPDATE ON users FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- ============================================================
-- Members
-- ============================================================
CREATE TABLE IF NOT EXISTS members (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  external_ref text, -- ex: LOT-001
  full_name text NOT NULL,
  email citext,
  role text,
  vote_weight numeric(12,4) NOT NULL DEFAULT 1.0,
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
-- Policies: quorum / vote
-- ============================================================
CREATE TABLE IF NOT EXISTS quorum_policies (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name text NOT NULL,
  description text,
  mode text NOT NULL DEFAULT 'single', -- single|evolving|double
  denominator text NOT NULL DEFAULT 'eligible_members', -- eligible_members|eligible_weight
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

CREATE TABLE IF NOT EXISTS vote_policies (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  name text NOT NULL,
  description text,
  base text NOT NULL DEFAULT 'expressed', -- expressed|total_eligible
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
-- Meetings / agendas / motions
-- ============================================================
CREATE TABLE IF NOT EXISTS meetings (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  title text NOT NULL,
  description text,
  status meeting_status NOT NULL DEFAULT 'draft',
  scheduled_at timestamptz,
  started_at timestamptz,
  ended_at timestamptz,
  location text,
  notes text,
  quorum_policy_id uuid REFERENCES quorum_policies(id) ON DELETE SET NULL,
  current_motion_id uuid,
  president_name text,
  validated_by text,
  validated_at timestamptz,
  archived_at timestamptz,
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

DROP TRIGGER IF EXISTS trg_meetings_updated_at ON meetings;
CREATE TRIGGER trg_meetings_updated_at
  BEFORE UPDATE ON meetings FOR EACH ROW EXECUTE FUNCTION update_updated_at();

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

CREATE TABLE IF NOT EXISTS motions (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  agenda_id uuid REFERENCES agendas(id) ON DELETE SET NULL,
  title text NOT NULL,
  description text,
  body text,
  secret boolean NOT NULL DEFAULT false,
  vote_policy_id uuid REFERENCES vote_policies(id) ON DELETE SET NULL,
  quorum_policy_id uuid REFERENCES quorum_policies(id) ON DELETE SET NULL,
  opened_at timestamptz,
  closed_at timestamptz,
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

DROP TRIGGER IF EXISTS trg_motions_updated_at ON motions;
CREATE TRIGGER trg_motions_updated_at
  BEFORE UPDATE ON motions FOR EACH ROW EXECUTE FUNCTION update_updated_at();

-- Compat: certains endpoints lisent motions.body, d'autres écrivent motions.description
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

-- FK meeting.current_motion_id -> motions.id
DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.table_constraints
    WHERE table_name='meetings' AND constraint_type='FOREIGN KEY'
      AND constraint_name='meetings_current_motion_id_fkey'
  ) THEN
    ALTER TABLE meetings
      ADD CONSTRAINT meetings_current_motion_id_fkey
      FOREIGN KEY (current_motion_id) REFERENCES motions(id) ON DELETE SET NULL;
  END IF;
END $$;

-- ============================================================
-- Invitations / proxies / attendances
-- ============================================================
CREATE TABLE IF NOT EXISTS invitations (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  email citext,
  token text UNIQUE,
  status text NOT NULL DEFAULT 'pending',
  sent_at timestamptz,
  responded_at timestamptz,
  response_notes text,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT invitations_status_check CHECK (status IN ('pending','sent','opened','accepted','declined','bounced')),
  UNIQUE (tenant_id, meeting_id, member_id)
);
CREATE INDEX IF NOT EXISTS idx_invitations_meeting ON invitations(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_invitations_token ON invitations(token) WHERE token IS NOT NULL;

DROP TRIGGER IF EXISTS trg_invitations_updated_at ON invitations;
CREATE TRIGGER trg_invitations_updated_at
  BEFORE UPDATE ON invitations FOR EACH ROW EXECUTE FUNCTION update_updated_at();

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

CREATE TABLE IF NOT EXISTS attendances (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  mode attendance_mode NOT NULL,
  checked_in_at timestamptz NOT NULL DEFAULT now(),
  checked_out_at timestamptz,
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
-- Ballots (votes)
-- ============================================================
CREATE TABLE IF NOT EXISTS ballots (
  id uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE CASCADE,
  motion_id uuid NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
  member_id uuid NOT NULL REFERENCES members(id) ON DELETE CASCADE,
  value motion_value NOT NULL,
  weight numeric(12,4) NOT NULL DEFAULT 1.0,
  cast_at timestamptz NOT NULL DEFAULT now(),
  is_proxy_vote boolean DEFAULT false,
  proxy_source_member_id uuid REFERENCES members(id),
  UNIQUE (motion_id, member_id)
);
CREATE INDEX IF NOT EXISTS idx_ballots_tenant_meeting ON ballots(tenant_id, meeting_id);
CREATE INDEX IF NOT EXISTS idx_ballots_motion ON ballots(motion_id);

-- Remplir meeting_id/tenant_id si absent (certains services ne fournissent pas meeting_id)
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
-- Audit events (minimal + hash chain optionnel)
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
  prev_hash bytea,
  this_hash bytea,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_audit_tenant_time ON audit_events(tenant_id, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_resource ON audit_events(resource_type, resource_id) WHERE resource_type IS NOT NULL;

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
-- Notifications (polling)
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
-- Mode dégradé (journal)
-- ============================================================
CREATE TABLE IF NOT EXISTS manual_actions (
  id bigserial PRIMARY KEY,
  tenant_id uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  motion_id uuid REFERENCES motions(id) ON DELETE SET NULL,
  member_id uuid REFERENCES members(id) ON DELETE SET NULL,
  action_type text NOT NULL,
  value jsonb NOT NULL DEFAULT '{}'::jsonb,
  justification text,
  operator_user_id uuid REFERENCES users(id) ON DELETE SET NULL,
  signature_hash text,
  created_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_manual_actions_meeting ON manual_actions(meeting_id, created_at DESC);

-- ============================================================
-- Tables manquantes identifiées (dashboards / devices / papier)
-- ============================================================
CREATE TABLE IF NOT EXISTS system_alerts (
  id            bigserial PRIMARY KEY,
  created_at    timestamptz NOT NULL DEFAULT now(),
  code          text NOT NULL,
  severity      text NOT NULL,
  message       text NOT NULL,
  details_json  jsonb
);
CREATE INDEX IF NOT EXISTS idx_system_alerts_created_at ON system_alerts(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_system_alerts_code ON system_alerts(code);

CREATE TABLE IF NOT EXISTS system_metrics (
  id                    bigserial PRIMARY KEY,
  created_at            timestamptz NOT NULL DEFAULT now(),
  server_time           timestamptz,
  db_latency_ms         double precision,
  db_active_connections integer,
  disk_free_bytes       bigint,
  disk_total_bytes      bigint,
  count_meetings        integer,
  count_motions         integer,
  count_vote_tokens     integer,
  count_audit_events    integer,
  auth_failures_15m     integer
);
CREATE INDEX IF NOT EXISTS idx_system_metrics_created_at ON system_metrics(created_at DESC);

CREATE TABLE IF NOT EXISTS auth_failures (
  id         bigserial PRIMARY KEY,
  created_at timestamptz NOT NULL DEFAULT now(),
  ip         text,
  user_agent text,
  key_prefix text,
  reason     text
);
CREATE INDEX IF NOT EXISTS idx_auth_failures_created_at ON auth_failures(created_at DESC);

CREATE TABLE IF NOT EXISTS vote_tokens (
  token_hash   char(64) PRIMARY KEY,
  tenant_id    uuid REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id   uuid REFERENCES meetings(id) ON DELETE CASCADE,
  member_id    uuid REFERENCES members(id) ON DELETE CASCADE,
  motion_id    uuid REFERENCES motions(id) ON DELETE CASCADE,
  expires_at   timestamptz NOT NULL,
  used_at      timestamptz,
  created_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_vote_tokens_expires_at ON vote_tokens(expires_at);
CREATE INDEX IF NOT EXISTS idx_vote_tokens_meeting_motion ON vote_tokens(meeting_id, motion_id);

CREATE TABLE IF NOT EXISTS device_heartbeats (
  device_id    uuid PRIMARY KEY,
  tenant_id    uuid REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id   uuid REFERENCES meetings(id) ON DELETE CASCADE,
  role         text,
  ip           text,
  user_agent   text,
  battery_pct  integer,
  is_charging  boolean,
  last_seen_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_device_heartbeats_tenant_device ON device_heartbeats(tenant_id, device_id);
CREATE INDEX IF NOT EXISTS idx_device_heartbeats_last_seen ON device_heartbeats(last_seen_at DESC);

CREATE TABLE IF NOT EXISTS device_blocks (
  id         bigserial PRIMARY KEY,
  tenant_id  uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id uuid REFERENCES meetings(id) ON DELETE CASCADE,
  device_id  uuid NOT NULL,
  is_blocked boolean NOT NULL DEFAULT true,
  reason     text,
  blocked_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_device_blocks_scope_device
ON device_blocks ( (COALESCE(meeting_id, '00000000-0000-0000-0000-000000000000'::uuid)), device_id );
CREATE INDEX IF NOT EXISTS idx_device_blocks_lookup
ON device_blocks(tenant_id, device_id, updated_at DESC);

CREATE TABLE IF NOT EXISTS device_commands (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id  uuid REFERENCES meetings(id) ON DELETE CASCADE,
  device_id   uuid NOT NULL,
  command     text NOT NULL,
  payload     jsonb,
  created_at  timestamptz NOT NULL DEFAULT now(),
  consumed_at timestamptz
);
CREATE INDEX IF NOT EXISTS idx_device_commands_pending
ON device_commands(tenant_id, device_id, command, created_at DESC)
WHERE consumed_at IS NULL;

CREATE TABLE IF NOT EXISTS meeting_emergency_checks (
  meeting_id      uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  procedure_code  text NOT NULL,
  item_index      integer NOT NULL,
  checked         boolean NOT NULL DEFAULT false,
  checked_at      timestamptz,
  checked_by      text,
  PRIMARY KEY(meeting_id, procedure_code, item_index)
);

CREATE TABLE IF NOT EXISTS meeting_reports (
  meeting_id  uuid PRIMARY KEY REFERENCES meetings(id) ON DELETE CASCADE,
  html        text NOT NULL,
  created_at  timestamptz NOT NULL DEFAULT now(),
  updated_at  timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS paper_ballots (
  id                uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  meeting_id         uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  motion_id          uuid NOT NULL REFERENCES motions(id) ON DELETE CASCADE,
  code               text NOT NULL,
  code_hash          char(64) NOT NULL,
  created_at         timestamptz NOT NULL DEFAULT now(),
  used_at            timestamptz,
  used_by_operator   boolean NOT NULL DEFAULT false
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_paper_ballots_code_hash ON paper_ballots(code_hash);
CREATE INDEX IF NOT EXISTS idx_paper_ballots_unused ON paper_ballots(code_hash) WHERE used_at IS NULL;

COMMIT;


-- ============================================================
-- PATCH: compléter schema.sql pour couvrir /public/api/v1
-- (Ajouté automatiquement le 2026-01-26)
-- ============================================================

-- 1) emergency_procedures (utilisé par emergency_panel / emergency_procedures)
CREATE TABLE IF NOT EXISTS emergency_procedures (
  code       text PRIMARY KEY,
  title      text NOT NULL,
  audience   text NOT NULL,     -- ex: 'operator' | 'president' | 'admin'
  steps_json jsonb NOT NULL DEFAULT '[]'::jsonb,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_emergency_procedures_audience ON emergency_procedures(audience);

-- 2) meetings: late rules + convocation + vote policy
ALTER TABLE meetings
  ADD COLUMN IF NOT EXISTS late_rule_quorum boolean NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS late_rule_vote   boolean NOT NULL DEFAULT true,
  ADD COLUMN IF NOT EXISTS convocation_no   text,
  ADD COLUMN IF NOT EXISTS vote_policy_id   uuid REFERENCES vote_policies(id) ON DELETE SET NULL;

-- 3) attendances: présent à partir de...
ALTER TABLE attendances
  ADD COLUMN IF NOT EXISTS present_from_at timestamptz;

-- 4) meeting_emergency_checks: champs de check
ALTER TABLE meeting_emergency_checks
  ADD COLUMN IF NOT EXISTS checked    boolean NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS checked_at timestamptz,
  ADD COLUMN IF NOT EXISTS checked_by text;

-- 5) manual_actions: compatibilité endpoints (paper ballot / degraded)
-- (On ajoute les noms attendus par l’API v1 au lieu de casser le code)
ALTER TABLE manual_actions
  ADD COLUMN IF NOT EXISTS resolution_id uuid,
  ADD COLUMN IF NOT EXISTS operator_id   uuid;

-- NOTE: Si ton schéma impose tenant_id NOT NULL sur manual_actions et que certains endpoints
-- ne le fournissent pas, l’approche la plus robuste est de corriger l’API pour passer tenant_id.
-- En attendant, on peut décommenter le DEFAULT ci-dessous pour éviter les erreurs d’insertion.
-- ALTER TABLE manual_actions
--   ALTER COLUMN tenant_id SET DEFAULT '00000000-0000-0000-0000-000000000000'::uuid;

-- Compat patch (PostgreSQL) pour aligner schema.sql avec /public/api/v1 (audit colonnes)
-- Généré le 2026-01-26
BEGIN;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS president_member_id uuid REFERENCES members(id) ON DELETE SET NULL;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS president_source text;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS ready_to_sign boolean DEFAULT false;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS validated_by_user_id uuid REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS display_name text;
ALTER TABLE members ADD COLUMN IF NOT EXISTS name text;
ALTER TABLE members ADD COLUMN IF NOT EXISTS voting_power numeric(12,4);
ALTER TABLE attendances ADD COLUMN IF NOT EXISTS checked_in_at timestamptz;
ALTER TABLE attendances ADD COLUMN IF NOT EXISTS checked_out_at timestamptz;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS revoked_at timestamptz;
ALTER TABLE invitations ADD COLUMN IF NOT EXISTS last_used_at timestamptz;
ALTER TABLE ballots ADD COLUMN IF NOT EXISTS choice text;
ALTER TABLE ballots ADD COLUMN IF NOT EXISTS effective_power numeric(12,4);
ALTER TABLE ballots ADD COLUMN IF NOT EXISTS source text;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS status text;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS position integer;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS sort_order integer;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS tally_status text;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS decision text;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS decision_reason text;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS evote_results jsonb;
ALTER TABLE motions ADD COLUMN IF NOT EXISTS manual_tally jsonb;
ALTER TABLE meeting_reports ADD COLUMN IF NOT EXISTS sha256 text;
ALTER TABLE meeting_reports ADD COLUMN IF NOT EXISTS generated_at timestamptz;
COMMIT;

-- =========================================================
-- DB LOCKDOWN POST-VALIDATION (v1)
-- Prevent any mutation once a meeting is validated
-- =========================================================

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

  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Motions: no update/delete after validation
DROP TRIGGER IF EXISTS trg_no_motion_update_after_validation ON motions;
CREATE TRIGGER trg_no_motion_update_after_validation
BEFORE UPDATE OR DELETE ON motions
FOR EACH ROW EXECUTE FUNCTION prevent_changes_after_meeting_validation();

-- Ballots: no insert/update/delete after validation
DROP TRIGGER IF EXISTS trg_no_ballot_change_after_validation ON ballots;
CREATE TRIGGER trg_no_ballot_change_after_validation
BEFORE INSERT OR UPDATE OR DELETE ON ballots
FOR EACH ROW EXECUTE FUNCTION prevent_changes_after_meeting_validation();

-- Attendances: no insert/update/delete after validation
DROP TRIGGER IF EXISTS trg_no_attendance_change_after_validation ON attendances;
CREATE TRIGGER trg_no_attendance_change_after_validation
BEFORE INSERT OR UPDATE OR DELETE ON attendances
FOR EACH ROW EXECUTE FUNCTION prevent_changes_after_meeting_validation();
