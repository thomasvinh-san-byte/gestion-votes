-- Dataset SIMPLE : AG 20 membres, quorum simple
-- Requires: tenant 'aaaaaaaa-1111-2222-3333-444444444444' (see database/seeds/01_minimal.sql)
-- Script idempotent : nettoie et recree les donnees a chaque execution.

BEGIN;

-- Nettoyage
DELETE FROM ballots WHERE meeting_id = '11111111-1111-1111-1111-111111111111';
DELETE FROM attendances WHERE meeting_id = '11111111-1111-1111-1111-111111111111';
DELETE FROM proxies WHERE meeting_id = '11111111-1111-1111-1111-111111111111';
UPDATE meetings SET current_motion_id = NULL WHERE id = '11111111-1111-1111-1111-111111111111';
DELETE FROM motions WHERE meeting_id = '11111111-1111-1111-1111-111111111111';
DELETE FROM meeting_roles WHERE meeting_id = '11111111-1111-1111-1111-111111111111';
DELETE FROM meetings WHERE id = '11111111-1111-1111-1111-111111111111';

INSERT INTO meetings (id, tenant_id, title, status, created_at, updated_at)
VALUES ('11111111-1111-1111-1111-111111111111','aaaaaaaa-1111-2222-3333-444444444444','AG Simple Test','draft',now(),now());

INSERT INTO members (tenant_id, full_name, email, voting_power)
SELECT 'aaaaaaaa-1111-2222-3333-444444444444', 'Membre '||g, 'm'||g||'@test.local', 1
FROM generate_series(1,20) g
ON CONFLICT (tenant_id, full_name) DO UPDATE
SET email = EXCLUDED.email,
    voting_power = EXCLUDED.voting_power,
    updated_at = now();

COMMIT;
