-- Dataset PONDÉRÉ : Copro 100 membres avec tantièmes
-- Requires: tenant 'aaaaaaaa-1111-2222-3333-444444444444' (see database/seeds/01_minimal.sql)
-- Script idempotent : nettoie et recree les donnees a chaque execution.
-- Note: les poids sont aleatoires, donc ils changent a chaque execution.

BEGIN;

-- Nettoyage
DELETE FROM ballots WHERE meeting_id = '22222222-2222-2222-2222-222222222222';
DELETE FROM attendances WHERE meeting_id = '22222222-2222-2222-2222-222222222222';
DELETE FROM proxies WHERE meeting_id = '22222222-2222-2222-2222-222222222222';
UPDATE meetings SET current_motion_id = NULL WHERE id = '22222222-2222-2222-2222-222222222222';
DELETE FROM motions WHERE meeting_id = '22222222-2222-2222-2222-222222222222';
DELETE FROM meeting_roles WHERE meeting_id = '22222222-2222-2222-2222-222222222222';
DELETE FROM meetings WHERE id = '22222222-2222-2222-2222-222222222222';

INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
VALUES ('22222222-2222-2222-2222-222222222222','aaaaaaaa-1111-2222-3333-444444444444','AG Pondérée Test','draft',now(),now());

INSERT INTO members (tenant_id, full_name, email, vote_weight)
SELECT 'aaaaaaaa-1111-2222-3333-444444444444', 'Copro '||g, 'c'||g||'@test.local', (random()*1000)::int+50
FROM generate_series(1,100) g
ON CONFLICT (tenant_id, full_name) DO UPDATE
SET email = EXCLUDED.email,
    vote_weight = EXCLUDED.vote_weight,
    updated_at = now();

COMMIT;
