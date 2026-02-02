-- Dataset PONDÉRÉ : Copro 100 membres avec tantièmes
-- Requires: tenant 'aaaaaaaa-1111-2222-3333-444444444444' (see database/seeds/01_minimal.sql)
INSERT INTO meetings (id, tenant_id, title, status) VALUES
('22222222-2222-2222-2222-222222222222','aaaaaaaa-1111-2222-3333-444444444444','AG Pondérée Test','draft')
ON CONFLICT (id) DO NOTHING;

INSERT INTO members (tenant_id, full_name, email, vote_weight)
SELECT 'aaaaaaaa-1111-2222-3333-444444444444', 'Copro '||g, 'c'||g||'@test.local', (random()*1000)::int+50
FROM generate_series(1,100) g
ON CONFLICT (tenant_id, full_name) DO NOTHING;