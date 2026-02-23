-- ============================================================
-- Migration 002: RBAC complet + états séance (state machine)
-- Date: 2026-01-29
-- Description:
--   • Ajoute les rôles assessor (assesseur/scrutateur) et auditor (auditeur)
--   • Ajoute les états meeting frozen (config verrouillée) et validated (PV approuvé)
--   • Crée la table de référence des permissions
--   • Crée la table de transitions d'état autorisées
--   • Ajoute les colonnes de gouvernance manquantes
-- ============================================================

-- ============================================================
-- PHASE 1: Supprimer l'ancienne contrainte, migrer, ajouter la nouvelle
-- ============================================================
-- Rôles du système :
--   admin     = Super-administrateur (plateforme)
--   operator  = Opérateur (gestion de séance, émargement, incidents)
--   president = Président (gouvernance : gel, ouverture/fermeture, approbation)
--   assessor  = Assesseur/Scrutateur (co-contrôle, co-signature, lecture)
--   auditor   = Auditeur (conformité, lecture logs, vérification intégrité)
--   voter     = Électeur (vote uniquement)
--   viewer    = Observateur (lecture seule)

-- 1a-1c wrapped in transaction for atomicity
BEGIN;
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;
UPDATE users SET role = 'assessor' WHERE role = 'trust';
UPDATE users SET role = 'viewer'   WHERE role = 'readonly';
ALTER TABLE users ADD CONSTRAINT users_role_check
  CHECK (role IN ('admin','operator','president','assessor','auditor','voter','viewer'));
COMMIT;

-- ============================================================
-- PHASE 3: Ajouter les valeurs ENUM pour meeting_status
-- NOTE: ALTER TYPE ADD VALUE ne peut PAS être dans une transaction
-- ============================================================
DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_enum WHERE enumlabel = 'frozen'
    AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'meeting_status')
  ) THEN
    ALTER TYPE meeting_status ADD VALUE 'frozen' BEFORE 'live';
  END IF;
END $$;

DO $$
BEGIN
  IF NOT EXISTS (SELECT 1 FROM pg_enum WHERE enumlabel = 'validated'
    AND enumtypid = (SELECT oid FROM pg_type WHERE typname = 'meeting_status')
  ) THEN
    ALTER TYPE meeting_status ADD VALUE 'validated' BEFORE 'archived';
  END IF;
END $$;

-- ============================================================
-- PHASE 4: Tables de référence (dans une transaction)
-- ============================================================
BEGIN;

-- Table de transitions d'état autorisées
CREATE TABLE IF NOT EXISTS meeting_state_transitions (
  from_status  text NOT NULL,
  to_status    text NOT NULL,
  required_role text NOT NULL,
  description  text,
  PRIMARY KEY (from_status, to_status)
);

DELETE FROM meeting_state_transitions;
INSERT INTO meeting_state_transitions (from_status, to_status, required_role, description) VALUES
  ('draft',     'scheduled', 'operator',  'Planifier la séance (date, lieu, convocation)'),
  ('scheduled', 'frozen',    'president', 'Verrouiller la configuration (résolutions, membres)'),
  ('draft',     'frozen',    'president', 'Verrouiller directement depuis brouillon'),
  ('frozen',    'live',      'president', 'Ouvrir la séance (début des votes)'),
  ('live',      'closed',    'president', 'Clôturer la séance (fin des votes)'),
  ('closed',    'validated', 'president', 'Valider les résultats et signer le PV'),
  ('validated', 'archived',  'admin',     'Archiver la séance (scellement définitif)'),
  ('frozen',    'scheduled', 'admin',     'Dégeler la configuration (cas exceptionnel)'),
  ('scheduled', 'draft',     'admin',     'Repasser en brouillon (annulation planification)');

-- Table de référence des permissions par rôle
CREATE TABLE IF NOT EXISTS role_permissions (
  role        text NOT NULL,
  permission  text NOT NULL,
  description text,
  PRIMARY KEY (role, permission)
);

DELETE FROM role_permissions;
INSERT INTO role_permissions (role, permission, description) VALUES
  -- ADMIN : tous les droits
  ('admin', 'meeting:create',    'Créer une séance'),
  ('admin', 'meeting:read',      'Consulter une séance'),
  ('admin', 'meeting:update',    'Modifier une séance'),
  ('admin', 'meeting:delete',    'Supprimer une séance'),
  ('admin', 'meeting:freeze',    'Verrouiller la configuration'),
  ('admin', 'meeting:open',      'Ouvrir la séance'),
  ('admin', 'meeting:close',     'Clôturer la séance'),
  ('admin', 'meeting:validate',  'Valider les résultats / signer PV'),
  ('admin', 'meeting:archive',   'Archiver la séance'),
  ('admin', 'meeting:unfreeze',  'Dégeler la configuration (exceptionnel)'),
  ('admin', 'motion:create',     'Créer une résolution'),
  ('admin', 'motion:read',       'Consulter les résolutions'),
  ('admin', 'motion:update',     'Modifier une résolution'),
  ('admin', 'motion:delete',     'Supprimer une résolution'),
  ('admin', 'motion:open',       'Ouvrir le vote sur une résolution'),
  ('admin', 'motion:close',      'Fermer le vote sur une résolution'),
  ('admin', 'vote:cast',         'Voter (mode dégradé)'),
  ('admin', 'vote:read',         'Consulter les résultats de vote'),
  ('admin', 'vote:manual',       'Saisie manuelle de vote'),
  ('admin', 'member:create',     'Ajouter un membre'),
  ('admin', 'member:read',       'Consulter les membres'),
  ('admin', 'member:update',     'Modifier un membre'),
  ('admin', 'member:delete',     'Supprimer un membre'),
  ('admin', 'member:import',     'Importer des membres'),
  ('admin', 'attendance:create', 'Enregistrer une présence'),
  ('admin', 'attendance:read',   'Consulter les présences'),
  ('admin', 'attendance:update', 'Modifier une présence'),
  ('admin', 'proxy:create',      'Créer une procuration'),
  ('admin', 'proxy:read',        'Consulter les procurations'),
  ('admin', 'proxy:delete',      'Supprimer une procuration'),
  ('admin', 'audit:read',        'Consulter les logs d''audit'),
  ('admin', 'audit:export',      'Exporter les logs d''audit'),
  ('admin', 'report:generate',   'Générer le PV'),
  ('admin', 'report:read',       'Consulter le PV'),
  ('admin', 'report:export',     'Exporter le PV'),
  ('admin', 'admin:users',       'Gérer les utilisateurs'),
  ('admin', 'admin:policies',    'Gérer les politiques de quorum/vote'),
  ('admin', 'admin:system',      'Consulter le statut système'),
  ('admin', 'admin:roles',       'Gérer les rôles et permissions'),

  -- OPERATOR : gestion opérationnelle de séance
  ('operator', 'meeting:create',    'Créer une séance'),
  ('operator', 'meeting:read',      'Consulter une séance'),
  ('operator', 'meeting:update',    'Modifier une séance (si non gelée)'),
  ('operator', 'meeting:archive',   'Archiver une séance validée'),
  ('operator', 'motion:create',     'Créer une résolution'),
  ('operator', 'motion:read',       'Consulter les résolutions'),
  ('operator', 'motion:update',     'Modifier une résolution (si non gelée)'),
  ('operator', 'motion:delete',     'Supprimer une résolution (si non gelée)'),
  ('operator', 'motion:open',       'Ouvrir le vote sur une résolution'),
  ('operator', 'motion:close',      'Fermer le vote sur une résolution'),
  ('operator', 'vote:cast',         'Voter (mode dégradé)'),
  ('operator', 'vote:read',         'Consulter les résultats de vote'),
  ('operator', 'vote:manual',       'Saisie manuelle de vote'),
  ('operator', 'member:create',     'Ajouter un membre'),
  ('operator', 'member:read',       'Consulter les membres'),
  ('operator', 'member:update',     'Modifier un membre'),
  ('operator', 'member:import',     'Importer des membres'),
  ('operator', 'attendance:create', 'Enregistrer une présence (émargement)'),
  ('operator', 'attendance:read',   'Consulter les présences'),
  ('operator', 'attendance:update', 'Modifier une présence'),
  ('operator', 'proxy:create',      'Créer une procuration'),
  ('operator', 'proxy:read',        'Consulter les procurations'),
  ('operator', 'proxy:delete',      'Supprimer une procuration'),
  ('operator', 'report:generate',   'Générer le PV'),
  ('operator', 'report:read',       'Consulter le PV'),
  ('operator', 'report:export',     'Exporter le PV'),

  -- PRESIDENT : gouvernance et approbation
  ('president', 'meeting:read',     'Consulter une séance'),
  ('president', 'meeting:freeze',   'Verrouiller la configuration'),
  ('president', 'meeting:open',     'Ouvrir la séance'),
  ('president', 'meeting:close',    'Clôturer la séance'),
  ('president', 'meeting:validate', 'Valider les résultats / signer PV'),
  ('president', 'motion:read',      'Consulter les résolutions'),
  ('president', 'motion:close',     'Fermer le vote (co-décision)'),
  ('president', 'vote:read',        'Consulter les résultats de vote'),
  ('president', 'member:read',      'Consulter les membres'),
  ('president', 'attendance:read',  'Consulter les présences'),
  ('president', 'proxy:read',       'Consulter les procurations'),
  ('president', 'audit:read',       'Consulter les logs (transparence)'),
  ('president', 'audit:export',     'Exporter les logs'),
  ('president', 'report:generate',  'Générer le PV'),
  ('president', 'report:read',      'Consulter le PV'),
  ('president', 'report:export',    'Exporter le PV'),

  -- ASSESSOR : co-contrôle et co-signature
  ('assessor', 'meeting:read',     'Consulter une séance'),
  ('assessor', 'motion:read',      'Consulter les résolutions'),
  ('assessor', 'vote:read',        'Consulter les résultats de vote'),
  ('assessor', 'member:read',      'Consulter les membres'),
  ('assessor', 'attendance:read',  'Consulter les présences'),
  ('assessor', 'proxy:read',       'Consulter les procurations'),
  ('assessor', 'audit:read',       'Consulter les logs'),
  ('assessor', 'report:read',      'Consulter le PV'),

  -- AUDITOR : conformité et vérification
  ('auditor', 'meeting:read',     'Consulter une séance'),
  ('auditor', 'motion:read',      'Consulter les résolutions'),
  ('auditor', 'vote:read',        'Consulter les résultats (anonymisés si secret)'),
  ('auditor', 'member:read',      'Consulter les membres'),
  ('auditor', 'attendance:read',  'Consulter les présences'),
  ('auditor', 'proxy:read',       'Consulter les procurations'),
  ('auditor', 'audit:read',       'Consulter les logs d''audit'),
  ('auditor', 'audit:export',     'Exporter les logs d''audit'),
  ('auditor', 'report:read',      'Consulter le PV'),
  ('auditor', 'report:export',    'Exporter le PV'),

  -- VOTER : vote uniquement
  ('voter', 'meeting:read',       'Consulter la séance en cours'),
  ('voter', 'motion:read',        'Consulter les résolutions'),
  ('voter', 'vote:cast',          'Voter'),

  -- VIEWER : lecture seule (observateur invité)
  ('viewer', 'meeting:read',      'Consulter une séance'),
  ('viewer', 'motion:read',       'Consulter les résolutions'),
  ('viewer', 'attendance:read',   'Consulter les présences'),
  ('viewer', 'report:read',       'Consulter le PV');

-- ============================================================
-- PHASE 5: Colonnes de gouvernance
-- ============================================================

ALTER TABLE meetings ADD COLUMN IF NOT EXISTS frozen_at timestamptz;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS frozen_by uuid REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS opened_by uuid REFERENCES users(id) ON DELETE SET NULL;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS closed_by uuid REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX IF NOT EXISTS idx_meetings_frozen ON meetings(tenant_id) WHERE status = 'frozen';
CREATE INDEX IF NOT EXISTS idx_meetings_validated ON meetings(tenant_id) WHERE status = 'validated';

COMMIT;
