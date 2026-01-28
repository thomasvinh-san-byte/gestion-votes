-- =============================================================================
-- API KEYS DE TEST POUR AG-VOTE
-- =============================================================================
-- ⚠️  NE PAS UTILISER EN PRODUCTION
-- Ces clés sont générées avec le APP_SECRET de développement
-- APP_SECRET requis: dev-secret-change-me-in-production-32ch
-- =============================================================================

-- Tenant de développement
INSERT INTO tenants (id, name, slug, is_active, created_at)
VALUES (
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Tenant de développement',
  'dev',
  true,
  NOW()
)
ON CONFLICT (id) DO NOTHING;

-- =============================================================================
-- UTILISATEUR ADMIN
-- =============================================================================
-- API Key: a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2
-- Hash: 5a8f9c3e7b2d1a4f6e8c0b9d2a5f7e3c1b8d6a4f2e9c7b5d3a1f8e6c4b2d0a9f7

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '11111111-1111-1111-1111-111111111111',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'admin@ag-vote.local',
  'Admin Test',
  'admin',
  '5a8f9c3e7b2d1a4f6e8c0b9d2a5f7e3c1b8d6a4f2e9c7b5d3a1f8e6c4b2d0a9f7',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (email) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR OPÉRATEUR
-- =============================================================================
-- API Key: op1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1
-- Hash: 7c2e9f1b5d8a3c6f0e4b7d2a9f5c8e1b3d6a0f4c7e2b9d5a8f1c4e7b0d3a6f9c2

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '22222222-2222-2222-2222-222222222222',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'operator@ag-vote.local',
  'Opérateur Test',
  'operator',
  '7c2e9f1b5d8a3c6f0e4b7d2a9f5c8e1b3d6a0f4c7e2b9d5a8f1c4e7b0d3a6f9c2',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (email) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR PRÉSIDENT
-- =============================================================================
-- API Key: pr1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1
-- Hash: 3f8d2a6c0e5b9f1d4a7c2e8b5f0d3a6c9e2b5f8d1a4c7e0b3f6d9a2c5e8b1f4d7

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '33333333-3333-3333-3333-333333333333',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'president@ag-vote.local',
  'Président Test',
  'president',
  '3f8d2a6c0e5b9f1d4a7c2e8b5f0d3a6c9e2b5f8d1a4c7e0b3f6d9a2c5e8b1f4d7',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (email) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR TRUST
-- =============================================================================
-- API Key: tr1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1
-- Hash: 9b4f1e7c3a0d5f8b2e6c9a3f7d1b4e8c2a5f9d3b7e1c4a8f2d6b0e3c7a1f5d9b3

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '44444444-4444-4444-4444-444444444444',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'trust@ag-vote.local',
  'Trust Test',
  'trust',
  '9b4f1e7c3a0d5f8b2e6c9a3f7d1b4e8c2a5f9d3b7e1c4a8f2d6b0e3c7a1f5d9b3',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (email) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- UTILISATEUR LECTURE SEULE
-- =============================================================================
-- API Key: ro1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1
-- Hash: 1d7a3f9e5b2c8d4a0f6e2b8c4d0a6f2e8b4c0d6a2f8e4b0c6d2a8f4e0b6c2d8a4

INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)
VALUES (
  '55555555-5555-5555-5555-555555555555',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'readonly@ag-vote.local',
  'Lecture Seule',
  'readonly',
  '1d7a3f9e5b2c8d4a0f6e2b8c4d0a6f2e8b4c0d6a2f8e4b0c6d2a8f4e0b6c2d8a4',
  true,
  NOW(),
  NOW()
)
ON CONFLICT (email) DO UPDATE SET
  api_key_hash = EXCLUDED.api_key_hash,
  role = EXCLUDED.role,
  updated_at = NOW();

-- =============================================================================
-- RÉSUMÉ DES CLÉS DE TEST
-- =============================================================================
-- 
-- ┌──────────────┬────────────────────────────────────────────────────────────────────┐
-- │ RÔLE         │ API KEY (header X-Api-Key)                                          │
-- ├──────────────┼────────────────────────────────────────────────────────────────────┤
-- │ admin        │ a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1f2   │
-- │ operator     │ op1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1   │
-- │ president    │ pr1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1   │
-- │ trust        │ tr1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1   │
-- │ readonly     │ ro1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6a7b8c9d0e1   │
-- └──────────────┴────────────────────────────────────────────────────────────────────┘
--
-- Note: Ces clés ne fonctionnent qu'avec APP_SECRET=dev-secret-change-me-in-production-32ch
-- =============================================================================
