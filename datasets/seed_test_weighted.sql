-- Dataset PONDÉRÉ : Copro 100 membres avec tantièmes
INSERT INTO meetings (uuid, title, status) VALUES
('22222222-2222-2222-2222-222222222222','AG Pondérée Test','preparation');

INSERT INTO members (meeting_id, name, email, weight)
SELECT m.id, 'Copro '||g, 'c'||g||'@test.local', (random()*1000)::int+50
FROM meetings m, generate_series(1,100) g
WHERE m.uuid='22222222-2222-2222-2222-222222222222';