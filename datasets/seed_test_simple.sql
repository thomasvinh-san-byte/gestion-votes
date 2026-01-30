-- Dataset SIMPLE : AG 20 membres, quorum simple
-- Requires: tenant 'aaaaaaaa-1111-2222-3333-444444444444' (see database/seeds/test_users.sql)
INSERT INTO meetings (id, tenant_id, title, status) VALUES
('11111111-1111-1111-1111-111111111111','aaaaaaaa-1111-2222-3333-444444444444','AG Simple Test','draft')
ON CONFLICT (id) DO NOTHING;

INSERT INTO members (tenant_id, full_name, email, vote_weight)
SELECT 'aaaaaaaa-1111-2222-3333-444444444444', 'Membre '||g, 'm'||g||'@test.local', 1
FROM generate_series(1,20) g
ON CONFLICT (tenant_id, full_name) DO NOTHING;