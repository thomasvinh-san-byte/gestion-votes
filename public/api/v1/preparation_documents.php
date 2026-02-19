<?php
// public/api/v1/preparation_documents.php
// GET: list documents for a meeting
// POST: create a new document entry
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

try {
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    if ($method === 'GET') {
        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
        }

        $items = db_all(
            "SELECT d.*
             FROM preparation_documents d
             JOIN meetings m ON m.id = d.meeting_id AND m.tenant_id = d.tenant_id
             WHERE d.tenant_id = :tid AND d.meeting_id = :mid
             ORDER BY d.category, d.sort_order, d.created_at",
            [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
        );

        api_ok(['items' => $items]);
    }

    if ($method === 'POST') {
        api_require_role('operator');
        $in = api_request('POST');

        $meetingId = api_require_uuid($in, 'meeting_id');

        // Verify meeting exists
        $meeting = db_one(
            "SELECT id FROM meetings WHERE tenant_id = :tid AND id = :mid",
            [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
        );
        if (!$meeting) api_fail('meeting_not_found', 404);

        $title = trim((string)($in['title'] ?? ''));
        if ($title === '') api_fail('missing_title', 422, ['detail' => 'title est obligatoire.']);
        if (mb_strlen($title) > 300) api_fail('title_too_long', 422);

        $description = trim((string)($in['description'] ?? ''));
        $category = trim((string)($in['category'] ?? 'annexe'));
        $validCategories = ['convocation', 'ordre_du_jour', 'annexe', 'rapport', 'budget', 'pv_precedent', 'autre'];
        if (!in_array($category, $validCategories, true)) {
            $category = 'annexe';
        }

        $deadline = trim((string)($in['deadline'] ?? ''));
        $requestedFrom = trim((string)($in['requested_from'] ?? ''));

        // Get next sort_order
        $maxSort = db_scalar(
            "SELECT COALESCE(MAX(sort_order), 0) FROM preparation_documents
             WHERE tenant_id = :tid AND meeting_id = :mid",
            [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId]
        );

        $id = api_uuid4();

        db_execute(
            "INSERT INTO preparation_documents
                (id, tenant_id, meeting_id, title, description, category, sort_order, deadline, requested_from,
                 status, requested_at)
             VALUES (:id, :tid, :mid, :title, :desc, :cat, :sort, NULLIF(:deadline,'')::date, NULLIF(:from,''),
                     'pending', CASE WHEN :from2 != '' THEN now() ELSE NULL END)",
            [
                ':id' => $id,
                ':tid' => DEFAULT_TENANT_ID,
                ':mid' => $meetingId,
                ':title' => $title,
                ':desc' => $description,
                ':cat' => $category,
                ':sort' => ((int)$maxSort) + 1,
                ':deadline' => $deadline,
                ':from' => $requestedFrom,
                ':from2' => $requestedFrom,
            ]
        );

        audit_log('document_created', 'preparation_document', $id, [
            'meeting_id' => $meetingId,
            'title' => $title,
            'category' => $category,
        ], $meetingId);

        api_ok(['id' => $id, 'created' => true], 201);
    }

    api_fail('method_not_allowed', 405);

} catch (Throwable $e) {
    error_log('preparation_documents.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
