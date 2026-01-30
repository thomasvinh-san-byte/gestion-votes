-- =============================================================================
-- API KEYS DE TEST POUR AG-VOTE
-- =============================================================================
-- NE PAS UTILISER EN PRODUCTION
-- Hash = HMAC-SHA256(api_key, APP_SECRET)
-- APP_SECRET requis: dev-secret-do-not-use-in-production-change-me-now-please-64chr
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
-- API Key: admin-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '11111111-1111-1111-1111-111111111111',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'admin@ag-vote.local',
  'Admin Test',
  'admin',
  '5abf0a151a493f8cb0ac941f5871f6bcef5f56521dd6e6a3e40f9a8da4ba8e67',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR OPERATEUR
-- API Key: operator-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '22222222-2222-2222-2222-222222222222',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'operator@ag-vote.local',
  'Operateur Test',
  'operator',
  '000b279c8ad165bc2dff3a340d03f9c5ff212de8638a4c257a9f6233029eb90c',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR AUDITEUR
-- API Key: auditor-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '44444444-4444-4444-4444-444444444444',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'auditor@ag-vote.local',
  'Auditeur Test',
  'auditor',
  '0d72d64eeca80fc606bdf65b841f433cfb450b2c066926d3968a8aa4b6fb90d6',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR VIEWER
-- API Key: viewer-key-2024-secret
-- =============================================================================

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '55555555-5555-5555-5555-555555555555',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'viewer@ag-vote.local',
  'Viewer Test',
  'viewer',
  '4d91dd1fb5df78e80a7cc7d01bd74d9fd4906e9542786814356769e1c8aa9501',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (id) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- RESUME DES CLES DE TEST
-- =============================================================================
--
-- +--------------+----------------------------+
-- | ROLE         | API KEY (header X-Api-Key)  |
-- +--------------+----------------------------+
-- | admin        | admin-key-2024-secret       |
-- | operator     | operator-key-2024-secret    |
-- | auditor      | auditor-key-2024-secret     |
-- | viewer       | viewer-key-2024-secret      |
-- +--------------+----------------------------+
--
-- Hash = HMAC-SHA256(api_key, APP_SECRET)
-- APP_SECRET = dev-secret-do-not-use-in-production-change-me-now-please-64chr
-- =============================================================================
