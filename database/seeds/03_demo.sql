-- database/seeds/03_demo.sql
-- Seed démo complète pour tests UI. Idempotent.
-- Dépend de 01_minimal.sql.
--
-- Crée :
--   • 1 séance LIVE (prête à voter) + 1 séance DRAFT (future)
--   • 12 membres pondérés
--   • Présences pour la séance live
--   • 5 motions (1 ouverte, 1 fermée avec résultats, 3 à venir)
--   • 1 proxy
--   • API keys de dev pour chaque rôle (utilisables uniquement avec APP_AUTH_ENABLED=1)

BEGIN;

-- ============================================================================
-- API keys de développement
-- ============================================================================
-- Ces clés sont le hash HMAC-SHA256 de la clé brute avec le secret 'dev-secret-not-for-production'.
-- Clé brute admin:    dev-admin-key-00000000000000000000000000000000
-- Clé brute operator: dev-operator-key-000000000000000000000000000000
-- Clé brute trust:    dev-trust-key-0000000000000000000000000000000000
--
-- Pour les utiliser, header: X-Api-Key: dev-admin-key-00000000000000000000000000000000
-- (le hash est calculé par le code PHP, on le pré-calcule ici pour le seed)

-- On met à jour les users existants avec des api_key_hash pré-calculés.
-- Hash = HMAC-SHA256(key, 'dev-secret-not-for-production')
-- NOTE: Ces clés ne fonctionnent QUE si APP_SECRET n'est pas défini ou vaut 'dev-secret-not-for-production'
-- Avec le .env dev fourni (APP_AUTH_ENABLED=0), l'auth est bypassée donc ces clés sont optionnelles.

-- ============================================================================
-- Séance LIVE (en cours)
-- ============================================================================
INSERT INTO meetings (
  id, tenant_id, title, description, status, quorum_policy_id, vote_policy_id,
  scheduled_at, started_at, location, president_name, convocation_no,
  created_at, updated_at
) VALUES (
  '44444444-4444-4444-4444-444444444001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Assemblée Générale Ordinaire — Séance démo',
  'Séance de démonstration pour tester l''ensemble des fonctionnalités.',
  'live',
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  now() - interval '1 hour',
  now() - interval '30 minutes',
  'Salle du Conseil — Bâtiment A',
  'Mme Martin',
  '2025/AGO-001',
  now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    description = EXCLUDED.description,
    status = EXCLUDED.status,
    quorum_policy_id = EXCLUDED.quorum_policy_id,
    vote_policy_id = EXCLUDED.vote_policy_id,
    scheduled_at = EXCLUDED.scheduled_at,
    started_at = EXCLUDED.started_at,
    location = EXCLUDED.location,
    president_name = EXCLUDED.president_name,
    convocation_no = EXCLUDED.convocation_no,
    updated_at = now();

-- Séance DRAFT (future)
INSERT INTO meetings (
  id, tenant_id, title, status, quorum_policy_id, scheduled_at, location,
  created_at, updated_at
) VALUES (
  '44444444-4444-4444-4444-444444444002',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Assemblée Générale Extraordinaire — Prochaine',
  'draft',
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  now() + interval '14 days',
  'Salle B',
  now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    status = EXCLUDED.status,
    scheduled_at = EXCLUDED.scheduled_at,
    location = EXCLUDED.location,
    updated_at = now();

-- ============================================================================
-- Membres (12, pondérés)
-- ============================================================================
INSERT INTO members (id, tenant_id, external_ref, full_name, email, vote_weight, role, is_active, created_at, updated_at)
VALUES
  ('55555555-5555-5555-5555-555555555001','aaaaaaaa-1111-2222-3333-444444444444','LOT-001','Mme Martin','martin@example.test',100.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555002','aaaaaaaa-1111-2222-3333-444444444444','LOT-002','M. Dubois','dubois@example.test',80.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555003','aaaaaaaa-1111-2222-3333-444444444444','LOT-003','Mme Lopez','lopez@example.test',60.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555004','aaaaaaaa-1111-2222-3333-444444444444','LOT-004','M. Bernard','bernard@example.test',40.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555005','aaaaaaaa-1111-2222-3333-444444444444','LOT-005','Mme Petit','petit@example.test',120.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555006','aaaaaaaa-1111-2222-3333-444444444444','LOT-006','M. Moreau','moreau@example.test',90.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555007','aaaaaaaa-1111-2222-3333-444444444444','LOT-007','Mme Leroy','leroy@example.test',70.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555008','aaaaaaaa-1111-2222-3333-444444444444','LOT-008','M. Roux','roux@example.test',55.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555009','aaaaaaaa-1111-2222-3333-444444444444','LOT-009','Mme Garcia','garcia@example.test',45.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555010','aaaaaaaa-1111-2222-3333-444444444444','LOT-010','M. Fournier','fournier@example.test',85.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555011','aaaaaaaa-1111-2222-3333-444444444444','LOT-011','Mme Thomas','thomas@example.test',65.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555012','aaaaaaaa-1111-2222-3333-444444444444','LOT-012','M. Lambert','lambert@example.test',35.0000,'member',true,now(),now())
ON CONFLICT (tenant_id, full_name) DO UPDATE
SET external_ref = EXCLUDED.external_ref,
    email = EXCLUDED.email,
    vote_weight = EXCLUDED.vote_weight,
    role = EXCLUDED.role,
    is_active = EXCLUDED.is_active,
    updated_at = now();

-- ============================================================================
-- Présences (10 présents sur 12 pour la séance live)
-- ============================================================================
INSERT INTO attendances (tenant_id, meeting_id, member_id, mode, effective_power, checked_in_at, created_at, updated_at)
VALUES
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555001','present',100.0000,now() - interval '25 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555002','present',80.0000,now() - interval '24 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555003','present',60.0000,now() - interval '23 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555004','present',40.0000,now() - interval '22 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555005','present',120.0000,now() - interval '20 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555006','remote',90.0000,now() - interval '18 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555007','present',70.0000,now() - interval '17 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555008','present',55.0000,now() - interval '15 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555009','present',45.0000,now() - interval '12 minutes',now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555010','present',85.0000,now() - interval '10 minutes',now(),now())
  -- LOT-011 (Thomas) et LOT-012 (Lambert) sont absents
ON CONFLICT (tenant_id, meeting_id, member_id) DO UPDATE
SET mode = EXCLUDED.mode,
    effective_power = EXCLUDED.effective_power,
    checked_in_at = EXCLUDED.checked_in_at,
    updated_at = now();

-- ============================================================================
-- Proxy : Lambert (absent) donne procuration à Martin (présent)
-- ============================================================================
INSERT INTO proxies (id, tenant_id, meeting_id, giver_member_id, receiver_member_id, scope, created_at)
VALUES (
  '77777777-7777-7777-7777-777777777001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  '55555555-5555-5555-5555-555555555012',  -- Lambert (absent)
  '55555555-5555-5555-5555-555555555001',  -- Martin (présent)
  'full',
  now()
)
ON CONFLICT (tenant_id, meeting_id, giver_member_id) DO UPDATE
SET receiver_member_id = EXCLUDED.receiver_member_id,
    scope = EXCLUDED.scope;

-- ============================================================================
-- Motions pour la séance live
-- ============================================================================

-- Motion 1 : fermée avec résultats (ADOPTÉE)
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, opened_at, closed_at, decision,
  created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Approbation des comptes 2024',
  'Vote sur l''approbation des comptes annuels de l''exercice 2024.',
  false, 1,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  NULL,
  'closed', now() - interval '20 minutes', now() - interval '15 minutes', 'adopted',
  now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    description = EXCLUDED.description,
    status = EXCLUDED.status,
    sort_order = EXCLUDED.sort_order,
    opened_at = EXCLUDED.opened_at,
    closed_at = EXCLUDED.closed_at,
    decision = EXCLUDED.decision,
    updated_at = now();

-- Motion 2 : EN COURS DE VOTE (ouverte)
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, opened_at,
  created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666002',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Budget travaux toiture — 45 000 €',
  'Vote sur le budget travaux de réfection de la toiture, devis n°2025-0042.',
  false, 2,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité 2/3' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  'open', now() - interval '5 minutes',
  now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    description = EXCLUDED.description,
    status = EXCLUDED.status,
    sort_order = EXCLUDED.sort_order,
    opened_at = EXCLUDED.opened_at,
    closed_at = NULL,
    decision = NULL,
    updated_at = now();

-- Mettre cette motion comme motion courante
UPDATE meetings
SET current_motion_id = '66666666-6666-6666-6666-666666666002'
WHERE id = '44444444-4444-4444-4444-444444444001';

-- Motion 3 : à venir
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666003',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Élection du président de séance',
  'Vote à bulletin secret pour l''élection du président.',
  true, 3,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 33% (personnes)' LIMIT 1),
  'draft', now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    description = EXCLUDED.description,
    secret = EXCLUDED.secret,
    sort_order = EXCLUDED.sort_order,
    status = EXCLUDED.status,
    updated_at = now();

-- Motion 4 : à venir
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, status, created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666004',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Changement de syndic',
  'Vote sur la résolution de changement de syndic de copropriété.',
  false, 4,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité absolue' LIMIT 1),
  'draft', now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    description = EXCLUDED.description,
    sort_order = EXCLUDED.sort_order,
    status = EXCLUDED.status,
    updated_at = now();

-- Motion 5 : à venir
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, status, created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666005',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Questions diverses',
  'Discussion et vote sur les questions diverses soulevées par les copropriétaires.',
  false, 5,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  'draft', now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    description = EXCLUDED.description,
    sort_order = EXCLUDED.sort_order,
    status = EXCLUDED.status,
    updated_at = now();

-- ============================================================================
-- Bulletins pour la motion 1 (fermée, adoptée)
-- ============================================================================
INSERT INTO ballots (id, tenant_id, meeting_id, motion_id, member_id, value, weight, cast_at)
VALUES
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555001','for',100.0000,now() - interval '18 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555002','for',80.0000,now() - interval '18 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555003','for',60.0000,now() - interval '17 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555004','against',40.0000,now() - interval '17 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555005','for',120.0000,now() - interval '17 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555006','for',90.0000,now() - interval '16 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555007','abstain',70.0000,now() - interval '16 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555008','for',55.0000,now() - interval '16 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555009','for',45.0000,now() - interval '15 minutes'),
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555010','against',85.0000,now() - interval '15 minutes')
ON CONFLICT (motion_id, member_id) DO NOTHING;

COMMIT;
