-- =============================================================================
-- UTILISATEURS DE TEST POUR AG-VOTE
-- =============================================================================
-- NE PAS UTILISER EN PRODUCTION
--
-- Authentification :
--   - Email / mot de passe (production) : voir tableau ci-dessous
--   - API key (legacy/fallback) : hash = HMAC-SHA256(api_key, APP_SECRET)
--     APP_SECRET = dev-secret-do-not-use-in-production-change-me-now-please-64chr
-- =============================================================================

-- Tenant de developpement
INSERT INTO tenants (id, name, slug, created_at, updated_at)
VALUES (
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Tenant de developpement',
  'dev',
  NOW(),
  NOW()
)
ON CONFLICT (id) DO NOTHING;

-- =============================================================================
-- UTILISATEUR ADMIN
-- Email: admin@ag-vote.local / Mot de passe: Admin2024!
-- API Key (legacy): admin-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '11111111-1111-1111-1111-111111111111',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'admin@ag-vote.local',
  'Admin Test',
  'admin',
  '$2y$12$r2GRtsuDzIOD0v57V6UIBeUWEcqk58xFpCYh67by0d3P8E6xTnB2a',
  '5abf0a151a493f8cb0ac941f5871f6bcef5f56521dd6e6a3e40f9a8da4ba8e67',
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
-- Email: operator@ag-vote.local / Mot de passe: Operator2024!
-- API Key (legacy): operator-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '22222222-2222-2222-2222-222222222222',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'operator@ag-vote.local',
  'Operateur Test',
  'operator',
  '$2y$12$rd0UERIoVtnzuzl82/GmSu4Ay5uPWCrKyj75LYnrf5iGL8.CnqGlq',
  '000b279c8ad165bc2dff3a340d03f9c5ff212de8638a4c257a9f6233029eb90c',
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
-- Email: auditor@ag-vote.local / Mot de passe: Auditor2024!
-- API Key (legacy): auditor-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '44444444-4444-4444-4444-444444444444',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'auditor@ag-vote.local',
  'Auditeur Test',
  'auditor',
  '$2y$12$.cHP5GA.P/EPzDY4n5uqtO9KRgMLKx/L9VfziWykkQo.eqGE.HSG6',
  '0d72d64eeca80fc606bdf65b841f433cfb450b2c066926d3968a8aa4b6fb90d6',
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
-- Email: viewer@ag-vote.local / Mot de passe: Viewer2024!
-- API Key (legacy): viewer-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, password_hash, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '55555555-5555-5555-5555-555555555555',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'viewer@ag-vote.local',
  'Viewer Test',
  'viewer',
  '$2y$12$kYDWYLnNi61lbc.uI7L0h.zs029pDnfDKIgD7CvyYq5U2BpQD3/Ce',
  '4d91dd1fb5df78e80a7cc7d01bd74d9fd4906e9542786814356769e1c8aa9501',
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
-- Email: president@ag-vote.local / Mot de passe: President2024!
-- API Key (legacy): president-key-2024-secret
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
  '$2y$12$dg6Zkb9.I//cSoOEGYGTVOdreVUOgdDYenfq8Qf4dnO6uw0vI9b9K',
  'dbbe8585b6302770f83d211338f211a13a71a64bb55c74da66036e9aacd585b0',
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
-- Email: votant@ag-vote.local / Mot de passe: Votant2024!
-- API Key (legacy): votant-key-2024-secret
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
  '$2y$12$FyfRB2mlxWc1/wsl39jG6.v7VWmu235NhvPtsEpH8hZuZoYG6Ca8C',
  '79f3e3a4aed6ef6f05212f57914f2af6e438700ede7b48bfa23c523ad3060fdb',
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
ON CONFLICT (id) DO NOTHING;

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

-- =============================================================================
-- RESUME DES IDENTIFIANTS DE TEST
-- =============================================================================
--
-- ROLES SYSTEME (connexion a la plateforme) :
-- +------------+------------------------+-----------------+-------------------+
-- | ROLE       | EMAIL                  | MOT DE PASSE    | DESCRIPTION       |
-- +------------+------------------------+-----------------+-------------------+
-- | admin      | admin@ag-vote.local    | Admin2024!      | Acces total       |
-- | operator   | operator@ag-vote.local | Operator2024!   | Gestion courante  |
-- | auditor    | auditor@ag-vote.local  | Auditor2024!    | Conformite (R/O)  |
-- | viewer     | viewer@ag-vote.local   | Viewer2024!     | Lecture seule     |
-- +------------+------------------------+-----------------+-------------------+
--
-- ROLES DE SEANCE (attribues sur la "Seance de test") :
-- +------------+---------------------------+-----------------+-------------------+
-- | ROLE       | EMAIL                     | MOT DE PASSE    | DESCRIPTION       |
-- +------------+---------------------------+-----------------+-------------------+
-- | president  | president@ag-vote.local   | President2024!  | Preside la seance |
-- | votant     | votant@ag-vote.local      | Votant2024!     | Vote en seance    |
-- +------------+---------------------------+-----------------+-------------------+
--
-- Tous les mots de passe sont hashes en bcrypt ($2y$12$...).
-- Aucun mot de passe n'est stocke en clair dans la base.
--
-- Les cles API (legacy) restent fonctionnelles en fallback :
--   admin-key-2024-secret, operator-key-2024-secret,
--   auditor-key-2024-secret, viewer-key-2024-secret,
--   president-key-2024-secret, votant-key-2024-secret
-- =============================================================================
