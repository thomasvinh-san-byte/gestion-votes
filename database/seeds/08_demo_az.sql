-- =============================================================================
-- SEED DEMO A-Z : seance vierge prete pour demonstration pas-a-pas
-- =============================================================================
-- Depend de : 01_minimal.sql (tenant, policies) + 02_test_users.sql (users)
--
-- Cree :
--   - 1 seance en statut "scheduled" (prete a etre passee en live)
--   - 10 membres votants avec poids varies
--   - 2 resolutions ("Approbation des comptes", "Affectation du resultat")
--   - Roles de seance (president, voter)
--   - Aucune presence, aucun bulletin (a saisir pendant la demo)
--
-- Script idempotent : peut etre relance autant de fois que necessaire.
--
-- DEMO FLOW:
--   A. Admin      → verifie la seance, les resolutions, les membres
--   B. Operator   → passe la seance en live, enregistre les presences
--   C. President  → ouvre le scrutin #1, supervise les votes
--   D. Operator   → saisit les votes pour chaque present
--   E. President  → cloture le scrutin #1, ouvre le #2, cloture
--   F. Admin      → valide la seance, genere le PV
--
-- IDENTIFIANTS :
--   admin@ag-vote.local     / Admin2026!      (role: admin)
--   operator@ag-vote.local  / Operator2026!   (role: operator)
--   president@ag-vote.local / President2026!  (role: operator + meeting role president)
--   auditor@ag-vote.local   / Auditor2026!    (role: auditor)
-- =============================================================================

BEGIN;

-- ============================================================================
-- NETTOYAGE des donnees de cette seed
-- ============================================================================
ALTER TABLE ballots DISABLE TRIGGER trg_no_ballot_change_after_validation;
ALTER TABLE attendances DISABLE TRIGGER trg_no_attendance_change_after_validation;
ALTER TABLE motions DISABLE TRIGGER trg_no_motion_update_after_validation;

DELETE FROM ballots WHERE meeting_id = 'deadbeef-demo-0001-az00-000000000001';
DELETE FROM attendances WHERE meeting_id = 'deadbeef-demo-0001-az00-000000000001';
DELETE FROM proxies WHERE meeting_id = 'deadbeef-demo-0001-az00-000000000001';
UPDATE meetings SET current_motion_id = NULL WHERE id = 'deadbeef-demo-0001-az00-000000000001';
DELETE FROM motions WHERE meeting_id = 'deadbeef-demo-0001-az00-000000000001';
DELETE FROM meeting_roles WHERE meeting_id = 'deadbeef-demo-0001-az00-000000000001';
DELETE FROM meetings WHERE id = 'deadbeef-demo-0001-az00-000000000001';

ALTER TABLE ballots ENABLE TRIGGER trg_no_ballot_change_after_validation;
ALTER TABLE attendances ENABLE TRIGGER trg_no_attendance_change_after_validation;
ALTER TABLE motions ENABLE TRIGGER trg_no_motion_update_after_validation;

-- ============================================================================
-- Seance SCHEDULED (prete pour la demo)
-- ============================================================================
INSERT INTO meetings (
  id, tenant_id, title, description, status,
  quorum_policy_id, vote_policy_id,
  scheduled_at, location, president_name, convocation_no,
  created_at, updated_at
) VALUES (
  'deadbeef-demo-0001-az00-000000000001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'AG Ordinaire 2026 — Demo A-Z',
  'Seance de demonstration complete : presences, votes, cloture, validation, PV.',
  'scheduled',
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  NOW() + INTERVAL '10 minutes',
  'Salle du Conseil — Siege social',
  'Mme Dupont',
  '2026/AGO-AZ',
  NOW(), NOW()
)
ON CONFLICT (id) DO UPDATE SET
  title = EXCLUDED.title,
  description = EXCLUDED.description,
  status = 'scheduled',
  quorum_policy_id = EXCLUDED.quorum_policy_id,
  vote_policy_id = EXCLUDED.vote_policy_id,
  scheduled_at = EXCLUDED.scheduled_at,
  location = EXCLUDED.location,
  president_name = EXCLUDED.president_name,
  convocation_no = EXCLUDED.convocation_no,
  started_at = NULL,
  ended_at = NULL,
  validated_at = NULL,
  current_motion_id = NULL,
  updated_at = NOW();

-- ============================================================================
-- 10 Membres votants (poids varies : total = 1000 tantièmes)
-- ============================================================================
INSERT INTO members (id, tenant_id, external_ref, full_name, email, vote_weight, role, is_active, created_at, updated_at)
VALUES
  ('aaa00001-demo-az00-0000-000000000001','aaaaaaaa-1111-2222-3333-444444444444','AZ-001','Mme Dupont','dupont@demo.test',150.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000002','aaaaaaaa-1111-2222-3333-444444444444','AZ-002','M. Martin','martin@demo.test',120.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000003','aaaaaaaa-1111-2222-3333-444444444444','AZ-003','Mme Bernard','bernard@demo.test',110.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000004','aaaaaaaa-1111-2222-3333-444444444444','AZ-004','M. Petit','petit@demo.test',100.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000005','aaaaaaaa-1111-2222-3333-444444444444','AZ-005','Mme Moreau','moreau@demo.test',95.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000006','aaaaaaaa-1111-2222-3333-444444444444','AZ-006','M. Leroy','leroy@demo.test',90.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000007','aaaaaaaa-1111-2222-3333-444444444444','AZ-007','Mme Roux','roux@demo.test',85.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000008','aaaaaaaa-1111-2222-3333-444444444444','AZ-008','M. Garcia','garcia@demo.test',80.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000009','aaaaaaaa-1111-2222-3333-444444444444','AZ-009','Mme Thomas','thomas@demo.test',90.0000,'member',true,NOW(),NOW()),
  ('aaa00001-demo-az00-0000-000000000010','aaaaaaaa-1111-2222-3333-444444444444','AZ-010','M. Lambert','lambert@demo.test',80.0000,'member',true,NOW(),NOW())
ON CONFLICT (tenant_id, full_name) DO UPDATE
SET external_ref = EXCLUDED.external_ref,
    email = EXCLUDED.email,
    vote_weight = EXCLUDED.vote_weight,
    role = EXCLUDED.role,
    is_active = EXCLUDED.is_active,
    updated_at = NOW();

-- ============================================================================
-- 2 Resolutions (motions) en statut draft
-- ============================================================================

-- Resolution 1 : Approbation des comptes (majorite simple)
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order, position,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  'deadbeef-demo-mot1-az00-000000000001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'deadbeef-demo-0001-az00-000000000001',
  'Resolution 1 — Approbation des comptes 2025',
  'Approbation des comptes annuels de l''exercice clos le 31 decembre 2025, tels que presentes par le conseil syndical.',
  false, 1, 1,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  'draft', NOW(), NOW()
)
ON CONFLICT (id) DO UPDATE SET
  title = EXCLUDED.title,
  description = EXCLUDED.description,
  status = 'draft',
  vote_policy_id = EXCLUDED.vote_policy_id,
  quorum_policy_id = EXCLUDED.quorum_policy_id,
  opened_at = NULL,
  closed_at = NULL,
  decision = NULL,
  updated_at = NOW();

-- Resolution 2 : Affectation du resultat (majorite 2/3)
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order, position,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  'deadbeef-demo-mot2-az00-000000000001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'deadbeef-demo-0001-az00-000000000001',
  'Resolution 2 — Affectation du resultat de l''exercice',
  'Vote sur l''affectation du resultat de l''exercice 2025 : report a nouveau, distribution, reserves.',
  false, 2, 2,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité 2/3' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  'draft', NOW(), NOW()
)
ON CONFLICT (id) DO UPDATE SET
  title = EXCLUDED.title,
  description = EXCLUDED.description,
  status = 'draft',
  vote_policy_id = EXCLUDED.vote_policy_id,
  quorum_policy_id = EXCLUDED.quorum_policy_id,
  opened_at = NULL,
  closed_at = NULL,
  decision = NULL,
  updated_at = NOW();

-- ============================================================================
-- Roles de seance
-- ============================================================================

-- President de seance (user president@ag-vote.local)
INSERT INTO meeting_roles (id, tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
VALUES (
  'deadbeef-demo-role-pres-000000000001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'deadbeef-demo-0001-az00-000000000001',
  '66666666-6666-6666-6666-666666666666',
  'president',
  '11111111-1111-1111-1111-111111111111',
  NOW()
)
ON CONFLICT (tenant_id, meeting_id, user_id, role) DO NOTHING;

-- Operateur sur la seance (user operator@ag-vote.local, role assessor pour le meeting)
INSERT INTO meeting_roles (id, tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
VALUES (
  'deadbeef-demo-role-asse-000000000001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'deadbeef-demo-0001-az00-000000000001',
  '22222222-2222-2222-2222-222222222222',
  'assessor',
  '11111111-1111-1111-1111-111111111111',
  NOW()
)
ON CONFLICT (tenant_id, meeting_id, user_id, role) DO NOTHING;

COMMIT;

-- =============================================================================
-- RECAPITULATIF DEMO A-Z
-- =============================================================================
--
-- Meeting ID : deadbeef-demo-0001-az00-000000000001
-- Statut     : scheduled (pret a passer en live)
-- Lieu       : Salle du Conseil — Siege social
-- Presidente : Mme Dupont
--
-- 10 MEMBRES (total 1000 tantiemes) :
-- +------+---------------+--------+-------------------------------------------+
-- | Ref  | Nom           | Poids  | UUID                                      |
-- +------+---------------+--------+-------------------------------------------+
-- | AZ-001 | Mme Dupont  |  150   | aaa00001-demo-az00-0000-000000000001      |
-- | AZ-002 | M. Martin   |  120   | aaa00001-demo-az00-0000-000000000002      |
-- | AZ-003 | Mme Bernard |  110   | aaa00001-demo-az00-0000-000000000003      |
-- | AZ-004 | M. Petit    |  100   | aaa00001-demo-az00-0000-000000000004      |
-- | AZ-005 | Mme Moreau  |   95   | aaa00001-demo-az00-0000-000000000005      |
-- | AZ-006 | M. Leroy    |   90   | aaa00001-demo-az00-0000-000000000006      |
-- | AZ-007 | Mme Roux    |   85   | aaa00001-demo-az00-0000-000000000007      |
-- | AZ-008 | M. Garcia   |   80   | aaa00001-demo-az00-0000-000000000008      |
-- | AZ-009 | Mme Thomas  |   90   | aaa00001-demo-az00-0000-000000000009      |
-- | AZ-010 | M. Lambert  |   80   | aaa00001-demo-az00-0000-000000000010      |
-- +------+---------------+--------+-------------------------------------------+
--
-- 2 RESOLUTIONS :
-- #1  Approbation des comptes 2025        (majorite simple, quorum 50%)
-- #2  Affectation du resultat             (majorite 2/3, quorum 50%)
--
-- SCENARIO SUGGERE POUR LA DEMO :
--   1. Login admin     → verifier seance + resolutions
--   2. Login operator  → transition scheduled→live, enregistrer 7/10 presents
--      (quorum 50% = 5 presents minimum → OK avec 7)
--   3. Login president → ouvrir scrutin #1
--   4. Login operator  → saisir 7 votes (5 pour, 1 contre, 1 abstention)
--   5. Login president → cloturer scrutin #1 → resultat affiche
--   6. Login president → ouvrir scrutin #2
--   7. Login operator  → saisir 7 votes (4 pour, 2 contre, 1 abstention)
--   8. Login president → cloturer scrutin #2 → resultat affiche
--   9. Login auditor   → consolider les resultats
--  10. Login president → valider la seance → generer le PV
--  11. Login auditor   → consulter le PV, les anomalies, lecture seule
-- =============================================================================
