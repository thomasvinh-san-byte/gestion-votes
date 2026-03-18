<?php

declare(strict_types=1);

namespace AgVote\Controller;

use finfo;
use AgVote\WebSocket\EventBroadcaster;

/**
 * Consolidates resolution_documents endpoints.
 *
 * Handles upload, listing, deletion, and authenticated serving of PDF
 * documents attached to individual motions (resolutions).
 */
final class ResolutionDocumentController extends AbstractController {
    public function listForMotion(): void {
        $motionId = api_query('motion_id');
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('missing_motion_id', 400);
        }

        $tenantId = api_current_tenant_id();
        $items = $this->repo()->resolutionDocument()->listForMotion($motionId, $tenantId);
        api_ok(['documents' => $items]);
    }

    public function upload(): void {
        $in = api_request('POST');
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $motionId = trim((string) ($in['motion_id'] ?? ''));
        if ($motionId === '' || !api_is_uuid($motionId)) {
            api_fail('missing_motion_id', 400);
        }

        $tenantId = api_current_tenant_id();

        $motion = $this->repo()->motion()->findByIdForTenant($motionId, $tenantId);
        if (!$motion) {
            api_fail('motion_not_found', 404);
        }

        $file = api_file('filepond');
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

        $uploadDir = AG_UPLOAD_DIR . '/resolutions/' . $motionId;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0o750, true);
        }

        $repo = $this->repo()->resolutionDocument();
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
            $motionId,
            basename($file['name']),
            $storedName,
            $mime,
            (int) $file['size'],
            api_current_user_id(),
        );

        audit_log('resolution_document_uploaded', 'resolution_document', $id, [
            'meeting_id' => $meetingId,
            'motion_id' => $motionId,
            'original_name' => basename($file['name']),
            'file_size' => (int) $file['size'],
        ]);

        // Broadcast SSE event for live session document additions
        EventBroadcaster::documentAdded($meetingId, $motionId, [
            'id' => $id,
            'original_name' => basename($file['name']),
            'file_size' => (int) $file['size'],
        ]);

        api_ok([
            'document' => [
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
        $repo = $this->repo()->resolutionDocument();
        $doc = $repo->findById($id, $tenantId);
        if (!$doc) {
            api_fail('not_found', 404);
        }

        $filePath = AG_UPLOAD_DIR . '/resolutions/' . $doc['motion_id'] . '/' . $doc['stored_name'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $repo->delete($id, $tenantId);

        audit_log('resolution_document_deleted', 'resolution_document', $id, [
            'meeting_id' => $doc['meeting_id'],
            'motion_id' => $doc['motion_id'],
            'original_name' => $doc['original_name'],
        ]);

        EventBroadcaster::documentRemoved($doc['meeting_id'], $doc['motion_id'], $id);

        api_ok(['deleted' => true]);
    }

    public function serve(): void
    {
        $id = api_query('id');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_id', 400);
        }

        // Auth check: accept both session auth and vote token
        // For session users: api_current_tenant_id() works
        // For vote token holders: check if the request has a valid session or token
        $tenantId = api_current_tenant_id();

        $doc = $this->repo()->resolutionDocument()->findById($id, $tenantId);
        if (!$doc) {
            api_fail('not_found', 404);
        }

        // Meeting membership check:
        // Session-based users (operator/admin) — tenant match is sufficient
        // Vote token users — verify meeting_id matches their token's meeting
        $userId = api_current_user_id();
        if ($userId === null) {
            // Voter token path: verify meeting_id matches the voter's session meeting
            $sessionMeetingId = $_SESSION['meeting_id'] ?? null;
            if ($sessionMeetingId === null || $sessionMeetingId !== $doc['meeting_id']) {
                api_fail('access_denied', 403);
            }
        }

        $path = AG_UPLOAD_DIR . '/resolutions/' . $doc['motion_id'] . '/' . $doc['stored_name'];
        if (!file_exists($path) || !is_readable($path)) {
            api_fail('file_not_found', 404);
        }

        // Sanitize filename for Content-Disposition header
        $safeFilename = preg_replace('/[^\w\s\-\.]/', '', $doc['original_name']);
        $safeFilename = basename($safeFilename) ?: 'document.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $safeFilename . '"');
        header('Content-Length: ' . (int) $doc['file_size']);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        header('X-Frame-Options: SAMEORIGIN');

        readfile($path);
        exit;
    }
}
