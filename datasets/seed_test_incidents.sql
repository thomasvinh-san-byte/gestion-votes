-- Dataset INCIDENTS : retards, procurations, panne réseau simulée
-- Requires: tenant 'aaaaaaaa-1111-2222-3333-444444444444' (see database/seeds/test_users.sql)
INSERT INTO meetings (id, tenant_id, title, status, late_rule_quorum, late_rule_vote) VALUES
('33333333-3333-3333-3333-333333333333','aaaaaaaa-1111-2222-3333-444444444444','AG Incidents Test','draft', true, true)
ON CONFLICT (id) DO NOTHING;