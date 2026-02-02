-- database/seeds/03_demo.sql
-- Seed demo complete pour tests UI. Idempotent.
-- Depend de 01_minimal.sql.
--
-- Cree :
--   - 1 seance LIVE (prete a voter) + 1 seance DRAFT (future)
--   - 12 membres ponderes
--   - Presences pour la seance live
--   - 5 motions (1 ouverte, 1 fermee avec resultats, 3 a venir)
--   - 1 proxy
--
-- Script idempotent : nettoie les donnees dependantes avant reinsertion.
-- Peut etre relance autant de fois que necessaire pour retrouver un etat stable.

BEGIN;

-- ============================================================================
-- NETTOYAGE COMPLET des donnees de cette seed
-- Desactive les triggers de protection pour permettre le nettoyage,
-- puis les reactive apres.
-- ============================================================================
ALTER TABLE ballots DISABLE TRIGGER trg_no_ballot_change_after_validation;
ALTER TABLE attendances DISABLE TRIGGER trg_no_attendance_change_after_validation;
ALTER TABLE motions DISABLE TRIGGER trg_no_motion_update_after_validation;

DELETE FROM ballots WHERE meeting_id IN (
  '44444444-4444-4444-4444-444444444001',
  '44444444-4444-4444-4444-444444444002'
);
DELETE FROM attendances WHERE meeting_id IN (
  '44444444-4444-4444-4444-444444444001',
  '44444444-4444-4444-4444-444444444002'
);
DELETE FROM proxies WHERE meeting_id IN (
  '44444444-4444-4444-4444-444444444001',
  '44444444-4444-4444-4444-444444444002'
);
-- Detacher la motion courante avant de supprimer les motions
UPDATE meetings SET current_motion_id = NULL WHERE id IN (
  '44444444-4444-4444-4444-444444444001',
  '44444444-4444-4444-4444-444444444002'
);
DELETE FROM motions WHERE meeting_id IN (
  '44444444-4444-4444-4444-444444444001',
  '44444444-4444-4444-4444-444444444002'
);
DELETE FROM meeting_roles WHERE meeting_id IN (
  '44444444-4444-4444-4444-444444444001',
  '44444444-4444-4444-4444-444444444002'
);
DELETE FROM meetings WHERE id IN (
  '44444444-4444-4444-4444-444444444001',
  '44444444-4444-4444-4444-444444444002'
);

ALTER TABLE ballots ENABLE TRIGGER trg_no_ballot_change_after_validation;
ALTER TABLE attendances ENABLE TRIGGER trg_no_attendance_change_after_validation;
ALTER TABLE motions ENABLE TRIGGER trg_no_motion_update_after_validation;

-- ============================================================================
-- Seance LIVE (en cours)
-- ============================================================================
INSERT INTO meetings (
  id, tenant_id, title, description, status, quorum_policy_id, vote_policy_id,
  scheduled_at, started_at, location, president_name, convocation_no,
  created_at, updated_at
) VALUES (
  '44444444-4444-4444-4444-444444444001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Assemblee Generale Ordinaire — Seance demo',
  'Seance de demonstration pour tester l''ensemble des fonctionnalites.',
  'live',
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  now() - interval '1 hour',
  now() - interval '30 minutes',
  'Salle du Conseil — Batiment A',
  'Mme Martin',
  '2026/AGO-001',
  now(), now()
)
ON CONFLICT (id) DO UPDATE SET
  title = EXCLUDED.title, status = EXCLUDED.status, updated_at = now();

-- Seance DRAFT (future)
INSERT INTO meetings (
  id, tenant_id, title, status, quorum_policy_id, scheduled_at, location,
  created_at, updated_at
) VALUES (
  '44444444-4444-4444-4444-444444444002',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Assemblee Generale Extraordinaire — Prochaine',
  'draft',
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  now() + interval '14 days',
  'Salle B',
  now(), now()
)
ON CONFLICT (id) DO UPDATE SET
  title = EXCLUDED.title, status = EXCLUDED.status, updated_at = now();

-- ============================================================================
-- Membres (12, ponderes)
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
-- Presences (10 presents sur 12 pour la seance live)
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
SET mode = EXCLUDED.mode, effective_power = EXCLUDED.effective_power, updated_at = now();

-- ============================================================================
-- Proxy : Lambert (absent) donne procuration a Martin (present)
-- ============================================================================
INSERT INTO proxies (id, tenant_id, meeting_id, giver_member_id, receiver_member_id, scope, created_at)
VALUES (
  '77777777-7777-7777-7777-777777777001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  '55555555-5555-5555-5555-555555555012',  -- Lambert (absent)
  '55555555-5555-5555-5555-555555555001',  -- Martin (present)
  'full',
  now()
)
ON CONFLICT (tenant_id, meeting_id, giver_member_id) DO UPDATE
SET receiver_member_id = EXCLUDED.receiver_member_id, scope = EXCLUDED.scope;

-- ============================================================================
-- Motions pour la seance live
-- ============================================================================

-- Motion 1 : fermee avec resultats (ADOPTEE)
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, opened_at, closed_at, decision,
  created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Approbation des comptes 2025',
  'Vote sur l''approbation des comptes annuels de l''exercice 2025.',
  false, 1,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  NULL,
  'closed', now() - interval '20 minutes', now() - interval '15 minutes', 'adopted',
  now(), now()
)
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, status = EXCLUDED.status, updated_at = now();

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
  'Budget travaux toiture — 45 000 EUR',
  'Vote sur le budget travaux de refection de la toiture, devis n.2026-0042.',
  false, 2,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité 2/3' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  'open', now() - interval '5 minutes',
  now(), now()
)
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, status = EXCLUDED.status, updated_at = now();

-- Mettre cette motion comme motion courante
UPDATE meetings
SET current_motion_id = '66666666-6666-6666-6666-666666666002'
WHERE id = '44444444-4444-4444-4444-444444444001';

-- Motion 3 : a venir
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666003',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Election du president de seance',
  'Vote a bulletin secret pour l''election du president.',
  true, 3,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 33% (personnes)' LIMIT 1),
  'draft', now(), now()
)
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, status = EXCLUDED.status, updated_at = now();

-- Motion 4 : a venir
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, status, created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666004',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Changement de syndic',
  'Vote sur la resolution de changement de syndic de copropriete.',
  false, 4,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité absolue' LIMIT 1),
  'draft', now(), now()
)
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, status = EXCLUDED.status, updated_at = now();

-- Motion 5 : a venir
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, sort_order,
  vote_policy_id, status, created_at, updated_at
) VALUES (
  '66666666-6666-6666-6666-666666666005',
  'aaaaaaaa-1111-2222-3333-444444444444',
  '44444444-4444-4444-4444-444444444001',
  'Questions diverses',
  'Discussion et vote sur les questions diverses soulevees par les coproprietaires.',
  false, 5,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  'draft', now(), now()
)
ON CONFLICT (id) DO UPDATE SET title = EXCLUDED.title, status = EXCLUDED.status, updated_at = now();

-- ============================================================================
-- Bulletins pour la motion 1 (fermee, adoptee)
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
  (gen_random_uuid(),'aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','66666666-6666-6666-6666-666666666001','55555555-5555-5555-5555-555555555010','against',85.0000,now() - interval '15 minutes');

COMMIT;
