<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingAttachmentRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Consolidates meeting_attachments.php.
 */
final class MeetingAttachmentController extends AbstractController
{
    public function listForMeeting(): void
    {
        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();
        $repo = new MeetingAttachmentRepository();
        $items = $repo->listForMeeting($meetingId, $tenantId);
        api_ok(['attachments' => $items]);
    }

    public function upload(): void
    {
        $meetingId = trim((string)($_POST['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404);
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            api_fail('upload_error', 400, ['detail' => "Upload error code: $code"]);
        }

        $file = $_FILES['file'];
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($file['size'] > $maxSize) {
            api_fail('file_too_large', 400, ['detail' => 'Le fichier ne doit pas dépasser 10 Mo.']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['application/pdf'];

        if (!in_array($mime, $allowedMimes, true)) {
            api_fail('invalid_mime_type', 400, ['detail' => "Seuls les fichiers PDF sont acceptés. Type détecté : $mime"]);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            api_fail('invalid_file_type', 400, ['detail' => 'Extension .pdf requise.']);
        }

        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/meetings/' . $meetingId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        $repo = new MeetingAttachmentRepository();
        $id = $repo->generateUuid();
        $storedName = $id . '.pdf';
        $destPath = $uploadDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            api_fail('upload_error', 500, ['detail' => 'Impossible de stocker le fichier.']);
        }

        $repo->create(
            $id,
            $tenantId,
            $meetingId,
            basename($file['name']),
            $storedName,
            $mime,
            (int)$file['size'],
            api_current_user_id()
        );

        audit_log('meeting_attachment_uploaded', 'meeting_attachment', $id, [
            'meeting_id' => $meetingId,
            'original_name' => basename($file['name']),
            'file_size' => (int)$file['size'],
        ]);

        api_ok([
            'attachment' => [
                'id' => $id,
                'original_name' => basename($file['name']),
                'file_size' => (int)$file['size'],
                'mime_type' => $mime,
            ],
        ], 201);
    }

    public function delete(): void
    {
        $input = api_request('DELETE');
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_id', 400);
        }

        $tenantId = api_current_tenant_id();
        $repo = new MeetingAttachmentRepository();
        $att = $repo->findById($id, $tenantId);
        if (!$att) {
            api_fail('not_found', 404);
        }

        $filePath = dirname(__DIR__, 2) . '/storage/uploads/meetings/' . $att['meeting_id'] . '/' . $att['stored_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $repo->delete($id, $tenantId);

        audit_log('meeting_attachment_deleted', 'meeting_attachment', $id, [
            'meeting_id' => $att['meeting_id'],
            'original_name' => $att['original_name'],
        ]);

        api_ok(['deleted' => true]);
    }
}
