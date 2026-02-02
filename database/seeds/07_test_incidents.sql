-- Dataset INCIDENTS : retards, procurations, panne reseau simulee
-- Requires: tenant 'aaaaaaaa-1111-2222-3333-444444444444' (see database/seeds/01_minimal.sql)
-- Script idempotent : nettoie et recree les donnees a chaque execution.

BEGIN;

-- Nettoyage
DELETE FROM ballots WHERE meeting_id = '33333333-3333-3333-3333-333333333333';
DELETE FROM attendances WHERE meeting_id = '33333333-3333-3333-3333-333333333333';
DELETE FROM proxies WHERE meeting_id = '33333333-3333-3333-3333-333333333333';
UPDATE meetings SET current_motion_id = NULL WHERE id = '33333333-3333-3333-3333-333333333333';
DELETE FROM motions WHERE meeting_id = '33333333-3333-3333-3333-333333333333';
DELETE FROM meeting_roles WHERE meeting_id = '33333333-3333-3333-3333-333333333333';
DELETE FROM meetings WHERE id = '33333333-3333-3333-3333-333333333333';

INSERT INTO meetings (id, tenant_id, title, status, late_rule_quorum, late_rule_vote, created_at, updated_at)
VALUES ('33333333-3333-3333-3333-333333333333','aaaaaaaa-1111-2222-3333-444444444444','AG Incidents Test','draft', true, true, now(), now());

COMMIT;
