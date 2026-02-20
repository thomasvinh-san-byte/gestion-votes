<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\EmergencyProcedureRepository;

/**
 * Consolidates emergency_check_toggle.php and emergency_procedures.php.
 */
final class EmergencyController extends AbstractController
{
    public function checkToggle(): void
    {
        api_require_role(['operator', 'admin']);
        $in = api_request('POST');

        $meetingId = api_require_uuid($in, 'meeting_id');
        api_guard_meeting_not_validated($meetingId);

        $procedure = trim((string)($in['procedure_code'] ?? ''));
        if ($procedure === '') api_fail('missing_procedure_code', 400);

        $idx = (int)($in['item_index'] ?? -1);
        if ($idx < 0) api_fail('invalid_item_index', 400);

        $checked = (int)($in['checked'] ?? 0) ? true : false;

        $repo = new MeetingRepository();
        $repo->upsertEmergencyCheck(
            $meetingId,
            $procedure,
            $idx,
            $checked,
            api_current_role()
        );

        audit_log('emergency_check_toggled', 'meeting', $meetingId, [
            'procedure_code' => $procedure,
            'item_index' => $idx,
            'checked' => $checked,
        ]);

        api_ok(['saved' => true]);
    }

    public function procedures(): void
    {
        api_require_role('operator');
        $q = api_request('GET');

        $aud = trim((string)($q['audience'] ?? 'operator'));
        $meetingId = trim((string)($q['meeting_id'] ?? ''));

        $repo = new EmergencyProcedureRepository();
        $rows = $repo->listByAudienceWithField($aud);

        $checks = [];
        if ($meetingId !== '' && api_is_uuid($meetingId)) {
            $checks = $repo->listChecksForMeeting($meetingId);
        }

        api_ok(['items' => $rows, 'checks' => $checks]);
    }
}
