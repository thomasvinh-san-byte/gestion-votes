-- =============================================================================
-- SEED E2E : Parcours complet d'une seance de A a Z
-- =============================================================================
-- Depend de : 01_minimal.sql (politiques, tenant) + 02_test_users.sql (comptes)
--
-- Cree une seance en DRAFT avec tout le necessaire pour tester le cycle :
--   DRAFT -> SCHEDULED -> FROZEN -> LIVE -> (votes) -> CLOSED -> VALIDATED -> ARCHIVED
--
-- Script idempotent : nettoie les donnees dependantes avant reinsertion.
-- Peut etre relance autant de fois que necessaire pour retrouver un etat stable.
--
-- COMPTES DE TEST (voir test_users.sql pour les mots de passe) :
-- +------------+---------------------------+-----------------+
-- | ROLE       | EMAIL                     | MOT DE PASSE    |
-- +------------+---------------------------+-----------------+
-- | admin      | admin@ag-vote.local       | Admin2026!      |
-- | operator   | operator@ag-vote.local    | Operator2026!   |
-- | president  | president@ag-vote.local   | President2026!  |
-- | votant     | votant@ag-vote.local      | Votant2026!     |
-- +------------+---------------------------+-----------------+
--
-- PARCOURS DE TEST :
-- 1. Se connecter en tant qu'operator -> /meetings.htmx.html
-- 2. La seance "Conseil Municipal — Seance E2E" apparait en DRAFT
-- 3. Operator : modifier les details, verifier les membres (12 elus)
-- 4. Operator : passer en SCHEDULED (bouton transition)
-- 5. Se connecter en tant que president -> /president.htmx.html
-- 6. President : passer en FROZEN (verrouiller la configuration)
-- 7. President : passer en LIVE (ouvrir la seance)
-- 8. Operator : enregistrer les presences (10/12 presents)
-- 9. Operator : ouvrir le vote sur la 1ere resolution
-- 10. Se connecter en tant que votant -> /vote.htmx.html
-- 11. Votant : voter (POUR / CONTRE / ABSTENTION)
-- 12. Operator : saisir les votes manuels pour les autres membres
-- 13. Operator : fermer le vote -> resultats calcules
-- 14. Repeter pour les autres resolutions
-- 15. President : cloturer la seance (LIVE -> CLOSED)
-- 16. President : valider et signer le PV (CLOSED -> VALIDATED)
-- 17. Admin : archiver la seance (VALIDATED -> ARCHIVED)
-- =============================================================================

BEGIN;

-- ============================================================================
-- NETTOYAGE COMPLET des donnees de cette seed
-- (ordre inverse des dependances pour respecter les FK)
-- ============================================================================
DELETE FROM ballots WHERE meeting_id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
DELETE FROM attendances WHERE meeting_id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
DELETE FROM proxies WHERE meeting_id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
UPDATE meetings SET current_motion_id = NULL WHERE id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
DELETE FROM motions WHERE meeting_id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
DELETE FROM agendas WHERE meeting_id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
DELETE FROM meeting_roles WHERE meeting_id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';
DELETE FROM meetings WHERE id = 'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001';

-- ============================================================================
-- SEANCE E2E (DRAFT, prete a etre lancee)
-- ============================================================================
INSERT INTO meetings (
  id, tenant_id, title, description, status,
  quorum_policy_id, vote_policy_id,
  scheduled_at, location, convocation_no,
  president_name, notes,
  created_at, updated_at
) VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Conseil Municipal — Seance E2E',
  'Seance complete de test pour parcourir le cycle de vie entier : '
  'creation, preparation, ouverture, votes, cloture, validation, archivage.',
  'draft',
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  now() + interval '1 hour',
  'Salle du Conseil Municipal — Hotel de Ville',
  '2026/CM-E2E-001',
  'Mme Dupont (President Test)',
  'Seance de test E2E. Ne pas utiliser en production.',
  now(), now()
);

-- ============================================================================
-- ROLES DE SEANCE (president, assessor, voter) pour les comptes de test
-- ============================================================================

-- President Test (president@ag-vote.local) -> president de cette seance
INSERT INTO meeting_roles (id, tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00010',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  '66666666-6666-6666-6666-666666666666',  -- president@ag-vote.local
  'president',
  '11111111-1111-1111-1111-111111111111',  -- admin
  now()
);

-- Operator Test -> assessor (co-controle)
INSERT INTO meeting_roles (id, tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00011',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  '22222222-2222-2222-2222-222222222222',  -- operator@ag-vote.local
  'assessor',
  '11111111-1111-1111-1111-111111111111',
  now()
);

-- Votant Test -> voter
INSERT INTO meeting_roles (id, tenant_id, meeting_id, user_id, role, assigned_by, assigned_at)
VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00012',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  '77777777-7777-7777-7777-777777777777',  -- votant@ag-vote.local
  'voter',
  '11111111-1111-1111-1111-111111111111',
  now()
);

-- ============================================================================
-- MEMBRES (12 elus municipaux avec poids de vote egal)
-- ============================================================================
INSERT INTO members (id, tenant_id, external_ref, full_name, email, vote_weight, role, is_active, created_at, updated_at)
VALUES
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00101','aaaaaaaa-1111-2222-3333-444444444444','CM-001','Mme Dupont (Maire)','dupont@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00102','aaaaaaaa-1111-2222-3333-444444444444','CM-002','M. Lefebvre (1er Adjoint)','lefebvre@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00103','aaaaaaaa-1111-2222-3333-444444444444','CM-003','Mme Girard (2e Adjointe)','girard@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00104','aaaaaaaa-1111-2222-3333-444444444444','CM-004','M. Blanc','blanc@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00105','aaaaaaaa-1111-2222-3333-444444444444','CM-005','Mme Rousseau','rousseau@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00106','aaaaaaaa-1111-2222-3333-444444444444','CM-006','M. Mercier','mercier@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00107','aaaaaaaa-1111-2222-3333-444444444444','CM-007','Mme Faure','faure@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00108','aaaaaaaa-1111-2222-3333-444444444444','CM-008','M. Andre','andre@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00109','aaaaaaaa-1111-2222-3333-444444444444','CM-009','Mme Bonnet','bonnet@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00110','aaaaaaaa-1111-2222-3333-444444444444','CM-010','M. Clement','clement@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00111','aaaaaaaa-1111-2222-3333-444444444444','CM-011','Mme Dumas','dumas@mairie.test',1.0000,'member',true,now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00112','aaaaaaaa-1111-2222-3333-444444444444','CM-012','M. Fontaine','fontaine@mairie.test',1.0000,'member',true,now(),now())
ON CONFLICT (tenant_id, full_name) DO UPDATE
SET external_ref = EXCLUDED.external_ref,
    email = EXCLUDED.email,
    vote_weight = EXCLUDED.vote_weight,
    is_active = EXCLUDED.is_active,
    updated_at = now();

-- ============================================================================
-- ORDRE DU JOUR (5 points)
-- ============================================================================
INSERT INTO agendas (id, tenant_id, meeting_id, idx, title, description, created_at, updated_at)
VALUES
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00201','aaaaaaaa-1111-2222-3333-444444444444','eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',1,
   'Approbation du PV de la seance precedente',
   'Lecture et approbation du proces-verbal de la derniere seance du conseil municipal.',
   now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00202','aaaaaaaa-1111-2222-3333-444444444444','eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',2,
   'Budget supplementaire 2026',
   'Examen et vote du budget supplementaire pour l''exercice 2026.',
   now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00203','aaaaaaaa-1111-2222-3333-444444444444','eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',3,
   'Renovation de la salle des fetes',
   'Deliberation sur le programme de travaux de renovation et le financement.',
   now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00204','aaaaaaaa-1111-2222-3333-444444444444','eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',4,
   'Convention intercommunale dechets',
   'Approbation de la convention avec la communaute de communes pour la gestion des dechets.',
   now(),now()),
  ('eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00205','aaaaaaaa-1111-2222-3333-444444444444','eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',5,
   'Questions diverses',
   'Discussion ouverte sur les points divers souleves par les elus.',
   now(),now());

-- ============================================================================
-- RESOLUTIONS / MOTIONS (5, toutes en draft, pretes a voter)
-- ============================================================================

-- Resolution 1 : Approbation PV (majorite simple)
INSERT INTO motions (
  id, tenant_id, meeting_id, agenda_id,
  title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00301',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00201',
  'Approbation du PV de la seance du 15 janvier 2026',
  'Le conseil municipal est invite a approuver le proces-verbal de la seance du 15 janvier 2026, '
  'tel que distribue aux membres.',
  false, 1,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  'draft', now(), now()
);

-- Resolution 2 : Budget (majorite absolue)
INSERT INTO motions (
  id, tenant_id, meeting_id, agenda_id,
  title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00302',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00202',
  'Vote du budget supplementaire 2026 — 150 000 EUR',
  'Le conseil delibere sur l''adoption du budget supplementaire d''un montant de 150 000 EUR, '
  'reparti entre les sections de fonctionnement (80 000 EUR) et d''investissement (70 000 EUR).',
  false, 2,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité absolue' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  'draft', now(), now()
);

-- Resolution 3 : Travaux (majorite 2/3)
INSERT INTO motions (
  id, tenant_id, meeting_id, agenda_id,
  title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00303',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00203',
  'Programme de renovation salle des fetes — 280 000 EUR',
  'Le conseil delibere sur le lancement du programme de travaux de renovation de la salle des fetes, '
  'incluant la mise aux normes accessibilite et la refection de la toiture. '
  'Financement : emprunt 200 000 EUR + subvention departementale 80 000 EUR.',
  false, 3,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité 2/3' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  'draft', now(), now()
);

-- Resolution 4 : Convention (majorite simple)
INSERT INTO motions (
  id, tenant_id, meeting_id, agenda_id,
  title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00304',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00204',
  'Convention intercommunale gestion des dechets 2026-2029',
  'Approbation de la convention tripartite avec la communaute de communes et le prestataire SUEZ '
  'pour la collecte et le traitement des dechets menagers, duree 3 ans.',
  false, 4,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  NULL,
  'draft', now(), now()
);

-- Resolution 5 : Election a bulletin secret
INSERT INTO motions (
  id, tenant_id, meeting_id, agenda_id,
  title, description, secret, sort_order,
  vote_policy_id, quorum_policy_id,
  status, created_at, updated_at
) VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00305',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00205',
  'Designation du delegue intercommunal',
  'Election a bulletin secret du representant de la commune au sein du conseil communautaire.',
  true, 5,
  (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 33% (personnes)' LIMIT 1),
  'draft', now(), now()
);

-- ============================================================================
-- PROCURATIONS (1 proxy pre-configure)
-- Fontaine (absent) donne procuration a Dupont (maire)
-- ============================================================================
INSERT INTO proxies (id, tenant_id, meeting_id, giver_member_id, receiver_member_id, scope, created_at)
VALUES (
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00401',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeeee0001',
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00112',  -- M. Fontaine (absent)
  'eeeeeeee-e2e0-e2e0-e2e0-eeeeeee00101',  -- Mme Dupont (presente)
  'full',
  now()
);

COMMIT;
