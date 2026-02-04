<?php
// public/api/v1/meetings.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\PolicyRepository;

api_require_role('operator');

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$repo = new MeetingRepository();

try {
    if ($method === 'GET') {
        // Single meeting by id
        $singleId = trim($_GET['id'] ?? '');
        if ($singleId !== '') {
            $meeting = $repo->findByIdForTenant($singleId, api_current_tenant_id());
            if (!$meeting) {
                api_fail('meeting_not_found', 404);
            }
            api_ok($meeting);
        }
        // List meetings - filter by active_only if requested
        $activeOnly = filter_var($_GET['active_only'] ?? '0', FILTER_VALIDATE_BOOLEAN);
        if ($activeOnly) {
            $rows = $repo->listActiveByTenant(api_current_tenant_id());
        } else {
            $rows = $repo->listByTenant(api_current_tenant_id());
        }
        api_ok(['meetings' => $rows]);

    } elseif ($method === 'POST') {
        $input = json_decode($GLOBALS['__ag_vote_raw_body'] ?? file_get_contents('php://input'), true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $scheduledAt = $input['scheduled_at'] ?? null;
        $location    = trim($input['location'] ?? '');

        if ($title === '') {
            api_fail('missing_title', 422, [
                'detail' => 'Le titre de la séance est obligatoire.',
            ]);
        }

        $id = $repo->generateUuid();
        $repo->create(
            $id,
            api_current_tenant_id(),
            $title,
            $description !== '' ? $description : null,
            $scheduledAt ?: null,
            $location !== '' ? $location : null
        );

        // Auto-assign tenant's first vote/quorum policies as defaults
        $policyRepo = new PolicyRepository();
        $votePolicies = $policyRepo->listVotePolicies(api_current_tenant_id());
        $quorumPolicies = $policyRepo->listQuorumPolicies(api_current_tenant_id());
        $defaults = [];
        if (!empty($votePolicies))   $defaults['vote_policy_id']   = $votePolicies[0]['id'];
        if (!empty($quorumPolicies)) $defaults['quorum_policy_id'] = $quorumPolicies[0]['id'];
        if ($defaults) {
            $repo->updateFields($id, api_current_tenant_id(), $defaults);
        }

        audit_log('meeting_created', 'meeting', $id, [
            'title'       => $title,
            'scheduled_at'=> $scheduledAt,
            'location'    => $location,
        ]);

        api_ok([
            'meeting_id' => $id,
            'title'      => $title,
        ], 201);

    } else {
        api_fail('method_not_allowed', 405);
    }

} catch (PDOException $e) {
    error_log("Database error in meetings.php: " . $e->getMessage());
    api_fail('database_error', 500, ['detail' => 'Erreur de base de données']);
} catch (Throwable $e) {
    error_log("Unexpected error in meetings.php: " . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
