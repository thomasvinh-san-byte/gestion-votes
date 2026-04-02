<?php

declare(strict_types=1);

namespace AgVote\Controller;

use finfo;

/**
 * Consolidates meeting_attachments.php.
 */
final class MeetingAttachmentController extends AbstractController {
    public function listForMeeting(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();
        $repo = $this->repo()->meetingAttachment();
        $items = $repo->listForMeeting($meetingId, $tenantId);
        api_ok(['attachments' => $items]);
    }

    public function upload(): void {
        $in = api_request('POST');
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();

        $meetingRepo = $this->repo()->meeting();
        if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) {
            api_fail('meeting_not_found', 404);
        }

        $file = api_file('file');
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            api_fail('upload_error', 400, ['detail' => "Upload error code: {$code}"]);
        }
        $maxSize = 10 * 1024 * 1024; // 10 MB

        if ($file['size'] > $maxSize) {
            api_fail('file_too_large', 400, ['detail' => 'Le fichier ne doit pas dépasser 10 Mo.']);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['application/pdf'];

        if (!in_array($mime, $allowedMimes, true)) {
            api_fail('invalid_mime_type', 400, ['detail' => "Seuls les fichiers PDF sont acceptés. Type détecté : {$mime}"]);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
            api_fail('invalid_file_type', 400, ['detail' => 'Extension .pdf requise.']);
        }

        $uploadDir = AG_UPLOAD_DIR . '/meetings/' . $meetingId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o750, true);
        }

        $repo = $this->repo()->meetingAttachment();
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
            (int) $file['size'],
            api_current_user_id(),
        );

        audit_log('meeting_attachment_uploaded', 'meeting_attachment', $id, [
            'meeting_id' => $meetingId,
            'original_name' => basename($file['name']),
            'file_size' => (int) $file['size'],
        ]);

        api_ok([
            'attachment' => [
                'id' => $id,
                'original_name' => basename($file['name']),
                'file_size' => (int) $file['size'],
                'mime_type' => $mime,
            ],
        ], 201);
    }

    public function delete(): void {
        $input = api_request('DELETE');
        $id = trim((string) ($input['id'] ?? ''));
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_id', 400);
        }

        $tenantId = api_current_tenant_id();
        $repo = $this->repo()->meetingAttachment();
        $att = $repo->findById($id, $tenantId);
        if (!$att) {
            api_fail('not_found', 404);
        }

        $filePath = AG_UPLOAD_DIR . '/meetings/' . $att['meeting_id'] . '/' . $att['stored_name'];
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

    public function listPublic(): void
    {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        // Dual auth: session users (operator/admin) OR vote token holders (voters)
        $userId = api_current_user_id();

        if ($userId !== null) {
            // Session-authenticated user — use their tenant
            $tenantId = api_current_tenant_id();
        } else {
            // No session — require a vote token via ?token= query param
            $rawToken = api_query('token');
            if ($rawToken === '') {
                api_fail('authentication_required', 401);
            }

            // Hash and look up the token
            $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);
            $tokenRow = $this->repo()->voteToken()->findByHash($tokenHash);
            if ($tokenRow === null) {
                api_fail('invalid_token', 401);
            }

            // Verify token belongs to the requested meeting
            if ($tokenRow['meeting_id'] !== $meetingId) {
                api_fail('access_denied', 403);
            }

            $tenantId = $tokenRow['tenant_id'];
        }

        $items = $this->repo()->meetingAttachment()->listForMeeting($meetingId, $tenantId);

        // Map to safe-fields-only — never expose stored_name to clients
        $safe = array_map(static function (array $att): array {
            return [
                'id'            => $att['id'],
                'original_name' => $att['original_name'],
                'file_size'     => $att['file_size'],
                'created_at'    => $att['created_at'],
            ];
        }, $items);

        api_ok(['attachments' => $safe]);
    }

    public function serve(): void
    {
        $id = api_query('id');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_id', 400);
        }

        // Dual auth: session users (operator/admin) OR vote token holders (voters)
        $userId = api_current_user_id();

        if ($userId !== null) {
            // Session-authenticated user (operator/admin) — use their tenant
            $tenantId = api_current_tenant_id();
        } else {
            // No session — require a vote token via ?token= query param
            $rawToken = api_query('token');
            if ($rawToken === '') {
                api_fail('authentication_required', 401);
            }

            // Hash and look up the token (findByHash, not consume — voter may have already voted)
            $tokenHash = hash_hmac('sha256', $rawToken, APP_SECRET);
            $tokenRow = $this->repo()->voteToken()->findByHash($tokenHash);
            if ($tokenRow === null) {
                api_fail('invalid_token', 401);
            }

            $tenantId = $tokenRow['tenant_id'];
            $tokenMeetingId = $tokenRow['meeting_id'];
        }

        $att = $this->repo()->meetingAttachment()->findById($id, $tenantId);
        if (!$att) {
            api_fail('not_found', 404);
        }

        // For vote token users: verify the attachment belongs to the token's meeting
        if ($userId === null && isset($tokenMeetingId) && $tokenMeetingId !== $att['meeting_id']) {
            api_fail('access_denied', 403);
        }

        $path = AG_UPLOAD_DIR . '/meetings/' . $att['meeting_id'] . '/' . $att['stored_name'];
        if (!file_exists($path) || !is_readable($path)) {
            api_fail('file_not_found', 404);
        }

        // Sanitize filename for Content-Disposition header
        $safeFilename = preg_replace('/[^\w\s\-\.]/', '', $att['original_name']);
        $safeFilename = basename($safeFilename) ?: 'document.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
        header('Content-Length: ' . (int) $att['file_size']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('X-Frame-Options: SAMEORIGIN');

        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            throw new FileServedOkException($path, 'application/pdf', $safeFilename, (int) $att['file_size']);
        }
        readfile($path);
        exit;
    }
}
