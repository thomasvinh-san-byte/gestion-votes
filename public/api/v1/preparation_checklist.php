<?php
// public/api/v1/preparation_checklist.php
// GET: list checklist items for a meeting
// POST: create a new checklist item
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
            "SELECT ci.*
             FROM preparation_checklist_items ci
             JOIN meetings m ON m.id = ci.meeting_id AND m.tenant_id = ci.tenant_id
             WHERE ci.tenant_id = :tid AND ci.meeting_id = :mid
             ORDER BY ci.category, ci.sort_order, ci.created_at",
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
        if (mb_strlen($title) > 200) api_fail('title_too_long', 422);

        $description = trim((string)($in['description'] ?? ''));
        $category = trim((string)($in['category'] ?? 'general'));
        $validCategories = ['documents', 'convocations', 'logistique', 'ordre_du_jour', 'general'];
        if (!in_array($category, $validCategories, true)) {
            $category = 'general';
        }

        $dueDate = trim((string)($in['due_date'] ?? ''));
        $assignedTo = trim((string)($in['assigned_to'] ?? ''));

        // Get next sort_order
        $maxSort = db_scalar(
            "SELECT COALESCE(MAX(sort_order), 0) FROM preparation_checklist_items
             WHERE tenant_id = :tid AND meeting_id = :mid AND category = :cat",
            [':tid' => DEFAULT_TENANT_ID, ':mid' => $meetingId, ':cat' => $category]
        );

        $id = api_uuid4();

        db_execute(
            "INSERT INTO preparation_checklist_items
                (id, tenant_id, meeting_id, title, description, category, sort_order, due_date, assigned_to)
             VALUES (:id, :tid, :mid, :title, :desc, :cat, :sort, NULLIF(:due,'')::date, NULLIF(:assign,''))",
            [
                ':id' => $id,
                ':tid' => DEFAULT_TENANT_ID,
                ':mid' => $meetingId,
                ':title' => $title,
                ':desc' => $description,
                ':cat' => $category,
                ':sort' => ((int)$maxSort) + 1,
                ':due' => $dueDate,
                ':assign' => $assignedTo,
            ]
        );

        audit_log('checklist_item_created', 'preparation_checklist', $id, [
            'meeting_id' => $meetingId,
            'title' => $title,
            'category' => $category,
        ], $meetingId);

        api_ok(['id' => $id, 'created' => true], 201);
    }

    api_fail('method_not_allowed', 405);

} catch (Throwable $e) {
    error_log('preparation_checklist.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
