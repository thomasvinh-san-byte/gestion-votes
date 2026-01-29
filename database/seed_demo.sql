-- database/seed_demo.sql
-- Seed démo (UI/smoke): 1 séance + membres + présences + motions. Idempotent.
-- Dépend de seed_minimal.sql.

BEGIN;

-- Meeting draft
INSERT INTO meetings (
  id, tenant_id, title, status, quorum_policy_id, scheduled_at, location, created_at, updated_at
) VALUES (
  '44444444-4444-4444-4444-444444444001',
  'aaaaaaaa-1111-2222-3333-444444444444',
  'Séance démo — Assemblée générale',
  'draft',
  (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 50% (personnes)' LIMIT 1),
  now() + interval '2 days',
  'Salle A',
  now(), now()
)
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    status = EXCLUDED.status,
    quorum_policy_id = EXCLUDED.quorum_policy_id,
    scheduled_at = EXCLUDED.scheduled_at,
    location = EXCLUDED.location,
    updated_at = now();

-- Members (pondérés)
INSERT INTO members (id, tenant_id, external_ref, full_name, email, vote_weight, role, is_active, created_at, updated_at)
VALUES
  ('55555555-5555-5555-5555-555555555001','aaaaaaaa-1111-2222-3333-444444444444','LOT-001','Mme Martin','martin@example.test',100.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555002','aaaaaaaa-1111-2222-3333-444444444444','LOT-002','M. Dubois','dubois@example.test',80.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555003','aaaaaaaa-1111-2222-3333-444444444444','LOT-003','Mme Lopez','lopez@example.test',60.0000,'member',true,now(),now()),
  ('55555555-5555-5555-5555-555555555004','aaaaaaaa-1111-2222-3333-444444444444','LOT-004','M. Bernard','bernard@example.test',40.0000,'member',true,now(),now())
ON CONFLICT (tenant_id, external_ref) DO UPDATE
SET full_name = EXCLUDED.full_name,
    email = EXCLUDED.email,
    vote_weight = EXCLUDED.vote_weight,
    role = EXCLUDED.role,
    is_active = EXCLUDED.is_active,
    updated_at = now();

-- Attendances: tout le monde présent
INSERT INTO attendances (tenant_id, meeting_id, member_id, mode, effective_power, created_at, updated_at)
VALUES
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555001','present',100.0000,now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555002','present',80.0000,now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555003','present',60.0000,now(),now()),
  ('aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001','55555555-5555-5555-5555-555555555004','present',40.0000,now(),now())
ON CONFLICT (tenant_id, meeting_id, member_id) DO UPDATE
SET mode = EXCLUDED.mode,
    effective_power = EXCLUDED.effective_power,
    updated_at = now();

-- Motions
INSERT INTO motions (
  id, tenant_id, meeting_id, title, description, secret, vote_policy_id, quorum_policy_id, created_at, updated_at
) VALUES
  ('66666666-6666-6666-6666-666666666001','aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001',
   'Approbation des comptes', 'Vote sur l’approbation des comptes annuels.', false,
   (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
   NULL, now(), now()),
  ('66666666-6666-6666-6666-666666666002','aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001',
   'Budget travaux', 'Vote sur le budget travaux.', false,
   (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité 2/3' LIMIT 1),
   NULL, now(), now()),
  ('66666666-6666-6666-6666-666666666003','aaaaaaaa-1111-2222-3333-444444444444','44444444-4444-4444-4444-444444444001',
   'Élection du président de séance', 'Vote simple.', false,
   (SELECT id FROM vote_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Majorité simple' LIMIT 1),
   (SELECT id FROM quorum_policies WHERE tenant_id='aaaaaaaa-1111-2222-3333-444444444444' AND name='Quorum 33% (personnes)' LIMIT 1),
   now(), now())
ON CONFLICT (id) DO UPDATE
SET title = EXCLUDED.title,
    description = EXCLUDED.description,
    secret = EXCLUDED.secret,
    vote_policy_id = EXCLUDED.vote_policy_id,
    quorum_policy_id = EXCLUDED.quorum_policy_id,
    updated_at = now();

COMMIT;