<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\IdempotencyGuard;
use AgVote\Core\Validation\Schemas\ValidationSchemas;
use AgVote\Repository\AgendaRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Consolidates agendas.php and meeting_late_rules.php.
 */
final class AgendaController extends AbstractController {
    public function listForMeeting(): void {
        $data = api_request('GET');

        $meetingId = api_require_uuid($data, 'meeting_id');

        $meetingRepo = new MeetingRepository();
        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $agendaRepo = new AgendaRepository();
        $rows = $agendaRepo->listForMeeting($meetingId, api_current_tenant_id());
        api_ok(['items' => $rows]);
    }

    public function create(): void {
        $cached = IdempotencyGuard::check();
        if ($cached !== null) {
            api_ok($cached, 201);
        }

        $data = api_request('POST');

        $v = ValidationSchemas::agenda()->validate($data);
        $v->failIfInvalid();

        $meetingId = $v->get('meeting_id');
        $title = $v->get('title');

        $meetingRepo = new MeetingRepository();
        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $agendaRepo = new AgendaRepository();
        $id = $agendaRepo->generateUuid();
        $idx = $agendaRepo->nextIdx($meetingId);

        $agendaRepo->create($id, api_current_tenant_id(), $meetingId, $idx, $title);

        audit_log('agenda_created', 'agenda', $id, [
            'meeting_id' => $meetingId,
            'idx' => $idx,
            'title' => $title,
        ]);

        $result = ['agenda_id' => $id, 'idx' => $idx, 'title' => $title];
        IdempotencyGuard::store($result);
        api_ok($result, 201);
    }

    public function lateRules(): void {
        $method = api_method();
        $repo = new MeetingRepository();

        if ($method === 'GET') {
            $q = api_request('GET');
            $meetingId = api_require_uuid($q, 'meeting_id');

            $row = $repo->findLateRules($meetingId, api_current_tenant_id());
            if (!$row) {
                api_fail('meeting_not_found', 404);
            }

            api_ok([
                'meeting_id' => $row['id'],
                'late_rule_quorum' => (bool) $row['late_rule_quorum'],
                'late_rule_vote' => (bool) $row['late_rule_vote'],
            ]);
        }

        if ($method === 'POST') {
            $in = api_request('POST');
            $meetingId = api_require_uuid($in, 'meeting_id');

            api_guard_meeting_not_validated($meetingId);

            $lrq = (int) ($in['late_rule_quorum'] ?? 1) ? true : false;
            $lrv = (int) ($in['late_rule_vote'] ?? 1) ? true : false;

            $repo->updateLateRules($meetingId, api_current_tenant_id(), $lrq, $lrv);

            audit_log('meeting_late_rules_updated', 'meeting', $meetingId, [
                'late_rule_quorum' => $lrq,
                'late_rule_vote' => $lrv,
            ]);

            api_ok(['saved' => true]);
        }

        api_fail('method_not_allowed', 405);
    }

    public function listForMeetingPublic(): void {
        $q = api_request('GET');
        $meetingId = api_require_uuid($q, 'meeting_id');

        $meetingRepo = new MeetingRepository();
        if (!$meetingRepo->existsForTenant($meetingId, api_current_tenant_id())) {
            api_fail('meeting_not_found', 404);
        }

        $agendaRepo = new AgendaRepository();
        $rows = $agendaRepo->listForMeetingCompact($meetingId, api_current_tenant_id());

        api_ok([
            'meeting_id' => $meetingId,
            'items' => $rows,
        ]);
    }
}
