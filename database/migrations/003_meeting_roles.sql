-- ============================================================
-- Migration 003: Rôles séance (meeting_roles) + rôles système
-- Date: 2026-01-29
-- Description:
--   • Sépare les rôles en 2 niveaux :
--     - Système (users.role) : admin, operator, auditor, viewer
--     - Séance (meeting_roles) : president, assessor, voter
--   • Crée la table meeting_roles (N:N users ↔ meetings)
--   • Migre les users existants avec rôles séance → rôle système
-- ============================================================

-- ============================================================
-- PHASE 1: Créer la table meeting_roles
-- ============================================================
CREATE TABLE IF NOT EXISTS meeting_roles (
  id          uuid PRIMARY KEY DEFAULT gen_random_uuid(),
  tenant_id   uuid NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
  meeting_id  uuid NOT NULL REFERENCES meetings(id) ON DELETE CASCADE,
  user_id     uuid NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role        text NOT NULL,
  assigned_by uuid REFERENCES users(id) ON DELETE SET NULL,
  assigned_at timestamptz NOT NULL DEFAULT now(),
  revoked_at  timestamptz,
  CONSTRAINT meeting_roles_role_check CHECK (role IN ('president','assessor','voter')),
  CONSTRAINT meeting_roles_unique_active UNIQUE (tenant_id, meeting_id, user_id, role)
);

CREATE INDEX IF NOT EXISTS idx_meeting_roles_meeting ON meeting_roles(tenant_id, meeting_id) WHERE revoked_at IS NULL;
CREATE INDEX IF NOT EXISTS idx_meeting_roles_user ON meeting_roles(tenant_id, user_id) WHERE revoked_at IS NULL;

-- ============================================================
-- PHASE 2: Migrer les users avec rôles séance → rôle système
-- ============================================================
-- president → operator (ils ont besoin d'accès opérationnel)
-- assessor  → viewer   (lecture seule au niveau système)
-- voter     → viewer   (lecture seule au niveau système)

-- D'abord, retirer la contrainte existante
ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check;

-- Migrer les rôles
UPDATE users SET role = 'operator' WHERE role = 'president';
UPDATE users SET role = 'viewer'   WHERE role = 'assessor';
UPDATE users SET role = 'viewer'   WHERE role = 'voter';

-- Nouvelle contrainte : rôles SYSTÈME uniquement
ALTER TABLE users ADD CONSTRAINT users_role_check
  CHECK (role IN ('admin','operator','auditor','viewer'));

-- ============================================================
-- PHASE 3: Mettre à jour role_permissions pour le nouveau modèle
-- ============================================================
DELETE FROM role_permissions;
INSERT INTO role_permissions (role, permission, description) VALUES
  -- ── ADMIN (système) : tous les droits ──
  ('admin', 'meeting:create',    'Créer une séance'),
  ('admin', 'meeting:read',      'Consulter une séance'),
  ('admin', 'meeting:update',    'Modifier une séance'),
  ('admin', 'meeting:delete',    'Supprimer une séance'),
  ('admin', 'meeting:freeze',    'Verrouiller la configuration'),
  ('admin', 'meeting:open',      'Ouvrir la séance'),
  ('admin', 'meeting:close',     'Clôturer la séance'),
  ('admin', 'meeting:validate',  'Valider les résultats / signer PV'),
  ('admin', 'meeting:archive',   'Archiver la séance'),
  ('admin', 'meeting:unfreeze',  'Dégeler la configuration'),
  ('admin', 'meeting:assign_roles', 'Attribuer des rôles de séance'),
  ('admin', 'motion:create',     'Créer une résolution'),
  ('admin', 'motion:read',       'Consulter les résolutions'),
  ('admin', 'motion:update',     'Modifier une résolution'),
  ('admin', 'motion:delete',     'Supprimer une résolution'),
  ('admin', 'motion:open',       'Ouvrir le vote'),
  ('admin', 'motion:close',      'Fermer le vote'),
  ('admin', 'vote:cast',         'Voter (mode dégradé)'),
  ('admin', 'vote:read',         'Consulter les résultats'),
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
  ('admin', 'admin:policies',    'Gérer les politiques'),
  ('admin', 'admin:system',      'Consulter le statut système'),
  ('admin', 'admin:roles',       'Gérer les rôles'),

  -- ── OPERATOR (système) : gestion opérationnelle ──
  ('operator', 'meeting:create',    'Créer une séance'),
  ('operator', 'meeting:read',      'Consulter une séance'),
  ('operator', 'meeting:update',    'Modifier une séance'),
  ('operator', 'meeting:archive',   'Archiver une séance validée'),
  ('operator', 'meeting:assign_roles', 'Attribuer des rôles de séance'),
  ('operator', 'motion:create',     'Créer une résolution'),
  ('operator', 'motion:read',       'Consulter les résolutions'),
  ('operator', 'motion:update',     'Modifier une résolution'),
  ('operator', 'motion:delete',     'Supprimer une résolution'),
  ('operator', 'motion:open',       'Ouvrir le vote'),
  ('operator', 'motion:close',      'Fermer le vote'),
  ('operator', 'vote:cast',         'Voter (mode dégradé)'),
  ('operator', 'vote:read',         'Consulter les résultats'),
  ('operator', 'vote:manual',       'Saisie manuelle'),
  ('operator', 'member:create',     'Ajouter un membre'),
  ('operator', 'member:read',       'Consulter les membres'),
  ('operator', 'member:update',     'Modifier un membre'),
  ('operator', 'member:import',     'Importer des membres'),
  ('operator', 'attendance:create', 'Enregistrer une présence'),
  ('operator', 'attendance:read',   'Consulter les présences'),
  ('operator', 'attendance:update', 'Modifier une présence'),
  ('operator', 'proxy:create',      'Créer une procuration'),
  ('operator', 'proxy:read',        'Consulter les procurations'),
  ('operator', 'proxy:delete',      'Supprimer une procuration'),
  ('operator', 'report:generate',   'Générer le PV'),
  ('operator', 'report:read',       'Consulter le PV'),
  ('operator', 'report:export',     'Exporter le PV'),

  -- ── AUDITOR (système) : conformité ──
  ('auditor', 'meeting:read',     'Consulter une séance'),
  ('auditor', 'motion:read',      'Consulter les résolutions'),
  ('auditor', 'vote:read',        'Consulter les résultats'),
  ('auditor', 'member:read',      'Consulter les membres'),
  ('auditor', 'attendance:read',  'Consulter les présences'),
  ('auditor', 'proxy:read',       'Consulter les procurations'),
  ('auditor', 'audit:read',       'Consulter les logs d''audit'),
  ('auditor', 'audit:export',     'Exporter les logs d''audit'),
  ('auditor', 'report:read',      'Consulter le PV'),
  ('auditor', 'report:export',    'Exporter le PV'),

  -- ── VIEWER (système) : lecture seule ──
  ('viewer', 'meeting:read',      'Consulter une séance'),
  ('viewer', 'motion:read',       'Consulter les résolutions'),
  ('viewer', 'attendance:read',   'Consulter les présences'),
  ('viewer', 'report:read',       'Consulter le PV'),

  -- ── PRESIDENT (séance) : gouvernance par séance ──
  ('president', 'meeting:read',     'Consulter la séance'),
  ('president', 'meeting:freeze',   'Verrouiller la configuration'),
  ('president', 'meeting:open',     'Ouvrir la séance'),
  ('president', 'meeting:close',    'Clôturer la séance'),
  ('president', 'meeting:validate', 'Valider / signer le PV'),
  ('president', 'motion:read',      'Consulter les résolutions'),
  ('president', 'motion:close',     'Fermer le vote (co-décision)'),
  ('president', 'vote:read',        'Consulter les résultats'),
  ('president', 'member:read',      'Consulter les membres'),
  ('president', 'attendance:read',  'Consulter les présences'),
  ('president', 'proxy:read',       'Consulter les procurations'),
  ('president', 'audit:read',       'Consulter les logs'),
  ('president', 'audit:export',     'Exporter les logs'),
  ('president', 'report:generate',  'Générer le PV'),
  ('president', 'report:read',      'Consulter le PV'),
  ('president', 'report:export',    'Exporter le PV'),

  -- ── ASSESSOR (séance) : co-contrôle ──
  ('assessor', 'meeting:read',     'Consulter la séance'),
  ('assessor', 'motion:read',      'Consulter les résolutions'),
  ('assessor', 'vote:read',        'Consulter les résultats'),
  ('assessor', 'member:read',      'Consulter les membres'),
  ('assessor', 'attendance:read',  'Consulter les présences'),
  ('assessor', 'proxy:read',       'Consulter les procurations'),
  ('assessor', 'audit:read',       'Consulter les logs'),
  ('assessor', 'report:read',      'Consulter le PV'),

  -- ── VOTER (séance) : vote uniquement ──
  ('voter', 'meeting:read',       'Consulter la séance'),
  ('voter', 'motion:read',        'Consulter les résolutions'),
  ('voter', 'vote:cast',          'Voter');

-- Mettre à jour les transitions pour le nouveau modèle
-- Le président est maintenant un rôle de SÉANCE, pas un rôle système.
-- Les transitions avec required_role='president' nécessitent que l'utilisateur
-- ait le meeting_role 'president' pour CETTE séance.
-- (La logique est dans AuthMiddleware, pas dans la DB)

-- ============================================================
-- PHASE 4: Mettre à jour la colonne role par défaut
-- ============================================================
ALTER TABLE users ALTER COLUMN role SET DEFAULT 'viewer';
