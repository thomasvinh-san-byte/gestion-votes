<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\QuorumEngine;
use AgVote\Repository\MeetingRepository;

/**
 * Consolidates quorum_card.php, quorum_status.php, meeting_quorum_settings.php.
 */
final class QuorumController extends AbstractController
{
    public function card(): void
    {
        $meetingId = api_query('meeting_id');
        $motionId  = api_query('motion_id');

        if ($meetingId !== '' && !api_is_uuid($meetingId)) {
            http_response_code(422);
            exit('Invalid meeting_id');
        }
        if ($motionId !== '' && !api_is_uuid($motionId)) {
            http_response_code(422);
            exit('Invalid motion_id');
        }

        header('Content-Type: text/html; charset=utf-8');

        try {
            $tenantId = api_current_tenant_id();

            if ($motionId !== '') {
                $r = QuorumEngine::computeForMotion($motionId);
                $title = $r['applies_to']['motion_title'] ?? 'Motion';
                $scope = 'Motion';
            } elseif ($meetingId !== '') {
                $r = QuorumEngine::computeForMeeting($meetingId, $tenantId);
                $title = null;
                $scope = 'Séance';
            } else {
                echo '<section class="card"><div class="muted">Quorum: meeting_id ou motion_id requis.</div></section>';
                exit;
            }

            $applied = $r['applied'] ?? false;
            $met = $r['met'] ?? null;
            $just = (string)($r['justification'] ?? '');

            if (!$applied) {
                echo '<section class="card"><div class="row between"><div><div class="k">Quorum</div><div class="muted tiny">Aucune politique appliquée.</div></div><span class="badge muted">—</span></div></section>';
                exit;
            }

            $badgeClass = 'muted';
            $badgeText  = '—';
            if ($met === true) { $badgeClass = 'success'; $badgeText = 'atteint'; }
            if ($met === false){ $badgeClass = 'danger';  $badgeText = 'non atteint'; }

            $safeJust = htmlspecialchars($just, ENT_QUOTES, 'UTF-8');
            $safeScope = htmlspecialchars($scope, ENT_QUOTES, 'UTF-8');
            $safeTitle = $title ? htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8') : '';

            echo '<section class="card">';
            echo '  <div class="row between">';
            echo '    <div>';
            echo '      <div class="k">Quorum <span class="muted tiny">(' . $safeScope . ')</span></div>';
            if ($safeTitle !== '') {
                echo '      <div class="muted tiny"><strong>' . $safeTitle . '</strong></div>';
            }
            echo '      <div class="muted tiny">' . $safeJust . '</div>';
            echo '    </div>';
            echo '    <span class="badge ' . $badgeClass . '">' . $badgeText . '</span>';
            echo '  </div>';
            echo '</section>';

        } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) {
            error_log("quorum_card error: " . $e->getMessage());
            $safe = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            echo '<section class="card"><div class="row between"><div><div class="k">Quorum</div><div class="muted tiny">' . $safe . '</div></div><span class="badge danger">erreur</span></div></section>';
        }
    }

    public function status(): void
    {
        api_request('GET');

        $meetingId = api_query('meeting_id');
        $motionId  = api_query('motion_id');

        if ($meetingId !== '' && !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 422);
        }
        if ($motionId !== '' && !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }

        $tenantId = api_current_tenant_id();

        if ($motionId !== '') {
            $res = QuorumEngine::computeForMotion($motionId);
        } elseif ($meetingId !== '') {
            $res = QuorumEngine::computeForMeeting($meetingId, $tenantId);
        } else {
            api_fail('missing_params', 400, ['detail' => 'meeting_id ou motion_id requis']);
        }

        $primary = $res['details']['primary'] ?? [];
        $res['ratio'] = $primary['ratio'] ?? 0;
        $res['threshold'] = $primary['threshold'] ?? 0.5;
        $res['present'] = $res['numerator']['members'] ?? 0;
        $res['total_eligible'] = $res['eligible']['members'] ?? 0;
        $res['required'] = (int)ceil(($primary['threshold'] ?? 0.5) * max(1, $res['eligible']['members'] ?? 0));
        $res['mode'] = $primary['basis'] ?? 'simple';

        api_ok($res);
    }

    public function meetingSettings(): void
    {
        $method = api_method();
        $repo = new MeetingRepository();

        if ($method === 'GET') {
            $q = api_request('GET');
            $meetingId = api_require_uuid($q, 'meeting_id');

            $row = $repo->findQuorumSettings($meetingId, api_current_tenant_id());
            if (!$row) {
                api_fail('meeting_not_found', 404);
            }

            api_ok([
                'meeting_id' => $row['meeting_id'],
                'title' => $row['title'],
                'quorum_policy_id' => $row['quorum_policy_id'],
                'convocation_no' => (int)$row['convocation_no'],
            ]);
        }

        if ($method === 'POST') {
            $in = api_request('POST');
            $meetingId = api_require_uuid($in, 'meeting_id');

            api_guard_meeting_not_validated($meetingId);

            $policyId = trim((string)($in['quorum_policy_id'] ?? ''));
            if ($policyId !== '' && !api_is_uuid($policyId)) {
                api_fail('invalid_quorum_policy_id', 400, ['expected' => 'uuid or empty']);
            }

            $convocationNo = (int)($in['convocation_no'] ?? 1);
            if (!in_array($convocationNo, [1, 2], true)) {
                api_fail('invalid_convocation_no', 400, ['expected' => '1 or 2']);
            }

            if (!$repo->existsForTenant($meetingId, api_current_tenant_id())) {
                api_fail('meeting_not_found', 404);
            }

            if ($policyId !== '') {
                if (!$repo->quorumPolicyExists($policyId, api_current_tenant_id())) {
                    api_fail('quorum_policy_not_found', 404);
                }
            }

            $repo->updateQuorumPolicy($meetingId, api_current_tenant_id(), $policyId === '' ? null : $policyId, $convocationNo);

            audit_log('meeting_quorum_updated', 'meeting', $meetingId, [
                'quorum_policy_id' => ($policyId === '' ? null : $policyId),
                'convocation_no' => $convocationNo,
            ]);

            api_ok(['saved' => true]);
        }

        api_fail('method_not_allowed', 405);
    }
}
