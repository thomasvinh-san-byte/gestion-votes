-- Dataset SIMPLE : AG 20 membres, quorum simple
INSERT INTO meetings (uuid, title, status) VALUES
('11111111-1111-1111-1111-111111111111','AG Simple Test','preparation');

INSERT INTO members (meeting_id, name, email, weight)
SELECT m.id, 'Membre '||g, 'm'||g||'@test.local', 1
FROM meetings m, generate_series(1,20) g
WHERE m.uuid='11111111-1111-1111-1111-111111111111';