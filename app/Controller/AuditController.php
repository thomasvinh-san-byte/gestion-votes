<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AuditEventRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Consolidates 5 audit/timeline endpoints.
 *
 * Shared pattern: meeting_id validation, audit event formatting, payload parsing.
 */
final class AuditController extends AbstractController {
    public function timeline(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $limit = min(200, max(1, api_query_int('limit', 50)));
        $offset = max(0, api_query_int('offset', 0));

        $tenantId = api_current_tenant_id();
        $meetingRepo = new MeetingRepository();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $auditRepo = new AuditEventRepository();
        $events = $auditRepo->listForMeetingLog($tenantId, $meetingId, $limit, $offset);
        $total = $auditRepo->countForMeetingLog($tenantId, $meetingId);

        $actionLabels = [
            'meeting_created' => 'Séance créée',
            'meeting_updated' => 'Séance modifiée',
            'meeting_validated' => 'Séance validée',
            'meeting_archived' => 'Séance archivée',
            'motion_created' => 'Résolution créée',
            'motion_opened' => 'Vote ouvert',
            'motion_closed' => 'Vote clôturé',
            'ballot_cast' => 'Vote enregistré',
            'manual_vote' => 'Vote manuel',
            'attendance_updated' => 'Présence modifiée',
            'attendances_bulk_update' => 'Présences modifiées en masse',
            'proxy_created' => 'Procuration créée',
            'proxy_deleted' => 'Procuration supprimée',
            'speech_requested' => 'Demande de parole',
            'speech_granted' => 'Parole accordée',
            'speech_ended' => 'Fin de parole',
            'incident_reported' => 'Incident signalé',
            'quorum_reached' => 'Quorum atteint',
            'quorum_lost' => 'Quorum perdu',
        ];

        $formatted = [];
        foreach ($events as $e) {
            $payload = self::parsePayload($e['payload'] ?? null);
            $actionLabel = $actionLabels[$e['action']] ?? ucfirst(str_replace('_', ' ', $e['action']));

            $message = $payload['message'] ?? $payload['detail'] ?? '';
            if (empty($message) && isset($payload['member_name'])) {
                $message = $payload['member_name'];
            }
            if (empty($message) && isset($payload['title'])) {
                $message = $payload['title'];
            }

            $actor = $e['actor_role'] ?? 'système';
            if (!empty($payload['actor_name'])) {
                $actor = $payload['actor_name'];
            }

            $formatted[] = [
                'id' => $e['id'],
                'timestamp' => $e['created_at'],
                'action' => $e['action'],
                'action_label' => $actionLabel,
                'resource_type' => $e['resource_type'],
                'resource_id' => $e['resource_id'],
                'actor' => $actor,
                'actor_role' => $e['actor_role'],
                'message' => $message,
                'ip_address' => $e['ip_address'],
                'payload' => $payload,
            ];
        }

        api_ok([
            'meeting_id' => $meetingId,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'items' => $formatted,
        ]);
    }

    public function export(): void {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');
        $format = strtolower(api_query('format', 'csv'));

        $meetingRepo = new MeetingRepository();
        $tenantId = api_current_tenant_id();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $slug = $meeting['slug'] ?? $meetingId;
        $auditRepo = new AuditEventRepository();
        $events = $auditRepo->listForMeetingExport($tenantId, $meetingId);

        if ($format === 'json') {
            $jsonEvents = [];
            foreach ($events as $e) {
                $payload = self::parsePayload($e['payload'] ?? null);

                $jsonEvents[] = [
                    'timestamp' => $e['created_at'],
                    'action' => $e['action'],
                    'actor_role' => $e['actor_role'],
                    'actor_user_id' => $e['actor_user_id'],
                    'resource_type' => $e['resource_type'],
                    'resource_id' => $e['resource_id'],
                    'ip_address' => $e['ip_address'] ?? null,
                    'payload' => $payload,
                    'prev_hash' => $e['prev_hash'] ?? null,
                    'this_hash' => $e['this_hash'] ?? null,
                ];
            }

            $chainValid = true;
            $chainErrors = [];
            for ($i = 1; $i < count($jsonEvents); $i++) {
                $prev = $jsonEvents[$i - 1]['this_hash'] ?? null;
                $curr = $jsonEvents[$i]['prev_hash'] ?? null;
                if ($prev !== null && $curr !== null && $prev !== $curr) {
                    $chainValid = false;
                    $chainErrors[] = [
                        'index' => $i,
                        'expected_prev' => $prev,
                        'actual_prev' => $curr,
                    ];
                }
            }

            $filename = "audit_{$slug}_" . date('Ymd_His') . '.json';

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            echo json_encode([
                'export_format' => 'ag-vote-audit-v1',
                'exported_at' => date('c'),
                'meeting_id' => $meetingId,
                'meeting_title' => $meeting['title'] ?? '',
                'meeting_status' => $meeting['status'] ?? '',
                'total_events' => count($jsonEvents),
                'chain_integrity' => [
                    'valid' => $chainValid,
                    'errors' => $chainErrors,
                ],
                'events' => $jsonEvents,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // CSV format (default)
        $filename = "audit_{$slug}_" . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['created_at', 'actor_role', 'actor_user_id', 'action', 'resource_type', 'resource_id', 'ip_address', 'payload']);
        foreach ($events as $e) {
            fputcsv($out, [
                $e['created_at'],
                $e['actor_role'],
                $e['actor_user_id'],
                $e['action'],
                $e['resource_type'],
                $e['resource_id'],
                $e['ip_address'] ?? '',
                is_string($e['payload']) ? $e['payload'] : json_encode($e['payload']),
            ]);
        }
        fclose($out);
        exit;
    }

    public function meetingAudit(): void {
        api_request('GET');

        $meetingId = api_query('meeting_id');
        if ($meetingId === '') {
            api_fail('missing_meeting_id', 422);
        }

        $meetingRepo = new MeetingRepository();

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $auditRepo = new AuditEventRepository();
        $rows = $auditRepo->listForMeeting($meetingId, api_current_tenant_id(), 200, 'ASC');

        api_ok([
            'meeting_id' => $meetingId,
            'items' => $rows,
        ]);
    }

    public function meetingEvents(): void {
        api_request('GET');

        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422);
        }

        $meetingRepo = new MeetingRepository();

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $auditRepo = new AuditEventRepository();
        $rows = $auditRepo->listForMeeting($meetingId, api_current_tenant_id());
        api_ok(['items' => self::formatEvents($rows)]);
    }

    public function operatorEvents(): void {
        api_request('GET');

        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422);
        }

        $limit = api_query_int('limit', 200);
        if ($limit <= 0) {
            $limit = 200;
        }
        if ($limit > 500) {
            $limit = 500;
        }

        $resourceType = api_query('resource_type');
        $action = api_query('action');
        $q = api_query('q');

        $meetingRepo = new MeetingRepository();

        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $auditRepo = new AuditEventRepository();
        $rows = $auditRepo->listForMeetingFiltered(
            api_current_tenant_id(),
            $meetingId,
            $limit,
            $resourceType,
            $action,
            $q,
        );

        api_ok(['items' => self::formatEvents($rows)]);
    }

    /**
     * Shared event formatting for meetingEvents and operatorEvents.
     */
    private static function formatEvents(array $rows): array {
        $events = [];
        foreach ($rows as $r) {
            $payload = self::parsePayload($r['payload'] ?? null);

            $message = (string) ($payload['message'] ?? '');
            if ($message === '' && isset($payload['detail'])) {
                $message = (string) $payload['detail'];
            }

            $events[] = [
                'id' => (string) $r['id'],
                'action' => (string) ($r['action'] ?? ''),
                'resource_type' => (string) ($r['resource_type'] ?? ''),
                'resource_id' => (string) ($r['resource_id'] ?? ''),
                'message' => $message,
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }
        return $events;
    }

    private static function parsePayload(mixed $payload): array {
        if (empty($payload)) {
            return [];
        }
        if (is_string($payload)) {
            return json_decode($payload, true) ?? [];
        }
        return (array) $payload;
    }
}
