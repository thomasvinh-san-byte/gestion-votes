<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\OperatorWorkflowService;
use AgVote\SSE\EventBroadcaster;
use RuntimeException;

/**
 * Consolidates 3 operator endpoints.
 *
 * Thin HTTP adapter — business logic delegated to OperatorWorkflowService.
 */
final class OperatorController extends AbstractController {
    public function workflowState(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $minOpen = api_query_int('min_open', 900);
        $minParticipation = (float) (api_query('min_participation', '0.5'));
        $tenantId = api_current_tenant_id();

        try {
            $service = new OperatorWorkflowService($this->repo());
            $result = $service->getWorkflowState($meetingId, $tenantId, $minOpen, $minParticipation);
            api_ok($result);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = $code === 'meeting_not_found' ? 404 : 400;
            api_fail($code, $status);
        }
    }

    public function openVote(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 422);
        }

        api_guard_meeting_not_validated($meetingId);

        $motionId = trim((string) ($input['motion_id'] ?? ''));
        if ($motionId !== '' && !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }

        $listTokens = (string) ($input['list'] ?? '') === '1';
        $expiresMinutes = (int) ($input['expires_minutes'] ?? 120);
        if ($expiresMinutes < 10) {
            $expiresMinutes = 10;
        }
        if ($expiresMinutes > 24 * 60) {
            $expiresMinutes = 24 * 60;
        }

        $secret = (string) (defined('APP_SECRET') ? APP_SECRET : config('app_secret', ''));
        $tenantId = api_current_tenant_id();

        $txResult = api_transaction(function () use ($meetingId, $motionId, $tenantId, $secret, $listTokens, $expiresMinutes) {
            try {
                $service = new OperatorWorkflowService($this->repo());
                return $service->openVote($meetingId, $motionId, $tenantId, api_current_user_id(), $secret, $listTokens, $expiresMinutes);
            } catch (RuntimeException $e) {
                $code = $e->getMessage();
                $statusMap = ['meeting_not_found' => 404, 'meeting_validated_locked' => 409, 'no_motion_to_open' => 409, 'another_motion_active' => 409, 'motion_not_found' => 404];
                api_fail($code, $statusMap[$code] ?? 400);
            }
        });

        $resolvedMotionId = (string) ($txResult['motionId'] ?? $motionId);
        $previousStatus = (string) ($txResult['previousStatus'] ?? 'live');
        if ($previousStatus !== 'live') {
            EventBroadcaster::meetingStatusChanged($meetingId, $tenantId, 'live', $previousStatus);
        }

        $motionRepo = $this->repo()->motion();
        $motionRow = $motionRepo->findByIdForTenant($resolvedMotionId, $tenantId);
        try {
            EventBroadcaster::motionOpened($meetingId, $resolvedMotionId, [
                'title' => (string) ($motionRow['title'] ?? ''),
                'secret' => !empty($motionRow['secret']),
            ]);
        } catch (\Throwable) {
            // Non-critical: SSE broadcast failure does not affect the response
        }

        audit_log('vote_tokens_generated', 'motion', $resolvedMotionId, [
            'meeting_id' => $meetingId,
            'inserted' => $txResult['inserted'],
            'expires_minutes' => $expiresMinutes,
        ]);

        api_ok([
            'meeting_id' => $meetingId,
            'motion_id' => $resolvedMotionId,
            'generated' => $txResult['inserted'],
            'tokens' => $listTokens ? $txResult['tokensOut'] : null,
        ]);
    }

    public function anomalies(): void {
        api_request('GET');

        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 422);
        }

        $motionId = api_query('motion_id');
        if ($motionId !== '' && !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }

        try {
            $service = new OperatorWorkflowService($this->repo());
            $result = $service->getAnomalies($meetingId, api_current_tenant_id(), $motionId);
            api_ok($result);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $statusMap = ['meeting_not_found' => 404, 'motion_not_found' => 404];
            api_fail($code, $statusMap[$code] ?? 400);
        }
    }
}
