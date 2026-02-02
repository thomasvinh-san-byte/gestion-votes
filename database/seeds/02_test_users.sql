-- =============================================================================
-- UTILISATEURS DE TEST POUR AG-VOTE
-- =============================================================================
-- NE PAS UTILISER EN PRODUCTION
--
-- Authentification :
--   - Email / mot de passe (production) : voir tableau ci-dessous
--   - API key (legacy/fallback) : hash = HMAC-SHA256(api_key, APP_SECRET)
--     APP_SECRET = dev-secret-do-not-use-in-production-change-me-now-please-64chr
--
-- Script idempotent : peut etre relance autant de fois que necessaire.
-- Les utilisateurs existants sont mis a jour (ON CONFLICT DO UPDATE).
-- Les donnees liees (meeting_roles) sont nettoyees avant reinsertion.
-- =============================================================================

BEGIN;

-- Tenant de developpement
INSERT INTO tenants (id, name, slug, created_at, updated_at)
VALUES (
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Tenant de developpement',
  'dev',
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  name = EXCLUDED.name,
  slug = EXCLUDED.slug,
  updated_at = NOW();

-- =============================================================================
-- NETTOYAGE des donnees dependantes avant reinsertion
-- =============================================================================
DELETE FROM meeting_roles WHERE meeting_id = 'bbbbbbbb-1111-2222-3333-444444444444';
DELETE FROM meetings WHERE id = 'bbbbbbbb-1111-2222-3333-444444444444';

-- =============================================================================
-- UTILISATEUR ADMIN
-- Email: admin@ag-vote.local / Mot de passe: Admin2026!
-- API Key (legacy): admin-key-2026-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '11111111-1111-1111-1111-111111111111',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'admin@ag-vote.local',
  'Admin Test',
  'admin',
  '$2y$12$BpzWieD.FL2PAGgk0D6JWe2bf.IxKPx/subD6bLI3c3/iFa0fyUZu',
  '1d2b215cf1a0e29b52260471ab2fa6e86bb7ca0ea2c009dea361945125cdb00a',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  password_hash = EXCLUDED.password_hash,
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR OPERATEUR
-- Email: operator@ag-vote.local / Mot de passe: Operator2026!
-- API Key (legacy): operator-key-2026-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '22222222-2222-2222-2222-222222222222',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'operator@ag-vote.local',
  'Operateur Test',
  'operator',
  '$2y$12$DziNeg6NTowzU1bpe2wcleySwSpX4HAgh3fcqwb42OwSfuJDejgnu',
  '34aff63bbcf077deadabac6e85717b3bfbea1dd80bd65ab7472a334290db20e2',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  password_hash = EXCLUDED.password_hash,
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR AUDITEUR
-- Email: auditor@ag-vote.local / Mot de passe: Auditor2026!
-- API Key (legacy): auditor-key-2026-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '44444444-4444-4444-4444-444444444444',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'auditor@ag-vote.local',
  'Auditeur Test',
  'auditor',
  '$2y$12$C6QB/lO9MKqfmnw3c2Yr/umfRqhpAYWjp3awda4XBqy4yzMbtM2ju',
  '8952e9b2705b7c4d4d2112e4d5824145df65af7126e5b3522ffed0d764a1f644',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  password_hash = EXCLUDED.password_hash,
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR VIEWER
-- Email: viewer@ag-vote.local / Mot de passe: Viewer2026!
-- API Key (legacy): viewer-key-2026-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '55555555-5555-5555-5555-555555555555',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'viewer@ag-vote.local',
  'Viewer Test',
  'viewer',
  '$2y$12$KDUogyLNsKiN/D9RVVRCI.g2/KOTED6KFWE4jlLLF4xCepxAMIHce',
  '0ea6c2e3db4707a3c7643423f0e714cb9f93c126bfb1b5de94969fb9f3754fc2',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  password_hash = EXCLUDED.password_hash,
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR PRESIDENT DE SEANCE
-- Email: president@ag-vote.local / Mot de passe: President2026!
-- API Key (legacy): president-key-2026-secret
-- Role systeme: operator (acces operationnel)
-- Role de seance: president (attribue sur la seance demo)
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '66666666-6666-6666-6666-666666666666',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'president@ag-vote.local',
  'President Test',
  'operator',
  '$2y$12$ViedR4kFQkzmieDB6H8fAeQBX9IRd6ZQYPHFGxs/VsSzgixzWy2iW',
  '4135460a78b04a46a0be4197ce237e3ae22c41e9343df54d54c512d42c59b73f',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  password_hash = EXCLUDED.password_hash,
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR VOTANT
-- Email: votant@ag-vote.local / Mot de passe: Votant2026!
-- API Key (legacy): votant-key-2026-secret
-- Role systeme: viewer (lecture seule au niveau plateforme)
-- Role de seance: voter (attribue sur la seance demo)
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '77777777-7777-7777-7777-777777777777',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'votant@ag-vote.local',
  'Votant Test',
  'viewer',
  '$2y$12$IMsb/IodXXhw14492MijceTje5i1Tny9DfH1V.3h40BBXLfe6xjUe',
  'f96e42de17c95c2834497e1d45fb466d0132a02f320d44f0676eb9a131051006',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  password_hash = EXCLUDED.password_hash,
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- SEANCE DEMO pour les roles de seance (president, voter)
-- =============================================================================

INSERT INTO meetings (id, tenant_id, title, description, status, scheduled_at, location, created_at, updated_at)
VALUES (
  'bbbbbbbb-1111-2222-3333-444444444444',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Seance de test',
  'Seance de demonstration pour tester les roles president et votant.',
  'draft',
  NOW() + INTERVAL '7 days',
  'Salle du conseil',
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  title = EXCLUDED.title,
  description = EXCLUDED.description,
  status = EXCLUDED.status,
  scheduled_at = EXCLUDED.scheduled_at,
  location = EXCLUDED.location,
  updated_at = NOW();

-- Attribution du role president sur la seance demo
INSERT INTO meeting_roles (id, tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
VALUES (
  'cccccccc-1111-2222-3333-444444444444',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'bbbbbbbb-1111-2222-3333-444444444444',
  '66666666-6666-6666-6666-666666666666',
  'president',
  '11111111-1111-1111-1111-111111111111',
  NOW()
)
ON CONFLICT (tenant_id, meeting_id, user_id, role) DO NOTHING;

-- Attribution du role voter sur la seance demo
INSERT INTO meeting_roles (id, tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
VALUES (
  'dddddddd-1111-2222-3333-444444444444',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'bbbbbbbb-1111-2222-3333-444444444444',
  '77777777-7777-7777-7777-777777777777',
  'voter',
  '11111111-1111-1111-1111-111111111111',
  NOW()
)
ON CONFLICT (tenant_id, meeting_id, user_id, role) DO NOTHING;

COMMIT;

-- =============================================================================
-- RESUME DES IDENTIFIANTS DE TEST
-- =============================================================================
--
-- ROLES SYSTEME (connexion a la plateforme) :
-- +------------+------------------------+-----------------+-------------------+
-- | ROLE       | EMAIL                  | MOT DE PASSE    | DESCRIPTION       |
-- +------------+------------------------+-----------------+-------------------+
-- | admin      | admin@ag-vote.local    | Admin2026!      | Acces total       |
-- | operator   | operator@ag-vote.local | Operator2026!   | Gestion courante  |
-- | auditor    | auditor@ag-vote.local  | Auditor2026!    | Conformite (R/O)  |
-- | viewer     | viewer@ag-vote.local   | Viewer2026!     | Lecture seule     |
-- +------------+------------------------+-----------------+-------------------+
--
-- ROLES DE SEANCE (attribues sur la "Seance de test") :
-- +------------+---------------------------+-----------------+-------------------+
-- | ROLE       | EMAIL                     | MOT DE PASSE    | DESCRIPTION       |
-- +------------+---------------------------+-----------------+-------------------+
-- | president  | president@ag-vote.local   | President2026!  | Preside la seance |
-- | votant     | votant@ag-vote.local      | Votant2026!     | Vote en seance    |
-- +------------+---------------------------+-----------------+-------------------+
--
-- Tous les mots de passe sont hashes en bcrypt ($2y$12$...).
-- Aucun mot de passe n'est stocke en clair dans la base.
--
-- Les cles API (legacy) restent fonctionnelles en fallback :
--   admin-key-2026-secret, operator-key-2026-secret,
--   auditor-key-2026-secret, viewer-key-2026-secret,
--   president-key-2026-secret, votant-key-2026-secret
-- =============================================================================
