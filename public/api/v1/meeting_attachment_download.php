<?php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingAttachmentRepository;

api_require_role('public');

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '' || !api_is_uuid($id)) {
    api_fail('missing_id', 400);
}

$tenantId = api_current_tenant_id();
$repo = new MeetingAttachmentRepository();
$att = $repo->findById($id, $tenantId);

if (!$att) {
    api_fail('not_found', 404);
}

$filePath = __DIR__ . '/../../../storage/uploads/meetings/' . $att['meeting_id'] . '/' . $att['stored_name'];
if (!file_exists($filePath)) {
    api_fail('not_found', 404, ['detail' => 'Fichier introuvable sur le disque.']);
}

// Serve the file
header('Content-Type: ' . $att['mime_type']);
header('Content-Disposition: inline; filename="' . addslashes($att['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=3600');
readfile($filePath);
exit;
