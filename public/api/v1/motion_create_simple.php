<?php
// public/api/v1/motion_create_simple.php
// Simplified motion creation - auto-handles agenda
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\AgendaRepository;
use AgVote\Repository\MeetingRepository;

api_require_role('operator');

try {
    $in = api_request('POST');

    $meetingId = trim((string)($in['meeting_id'] ?? ''));
    if ($meetingId === '' || !api_is_uuid($meetingId)) {
        api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id est obligatoire (uuid).']);
    }

    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') {
        api_fail('missing_title', 422, ['detail' => 'title est obligatoire.']);
    }
    if (mb_strlen($title) > 200) {
        api_fail('title_too_long', 422, ['detail' => 'title doit faire ≤ 200 caractères.']);
    }

    $description = trim((string)($in['description'] ?? ''));
    if (mb_strlen($description) > 10000) {
        api_fail('description_too_long', 422, ['detail' => 'description trop longue (max 10000).']);
    }

    $secret = (bool)($in['secret'] ?? false);
    $tenantId = api_current_tenant_id();

    // Verify meeting exists and belongs to tenant
    $meetingRepo = new MeetingRepository();
    if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) {
        api_fail('meeting_not_found', 404, ['detail' => 'Séance non trouvée.']);
    }

    api_guard_meeting_not_validated($meetingId);

    // Wrap agenda + motion creation in a single transaction
    $result = api_transaction(function () use ($meetingId, $tenantId, $title, $description, $secret) {
        // Get or create default agenda for this meeting
        $agendaRepo = new AgendaRepository();
        $agendas = $agendaRepo->listForMeetingCompact($meetingId);

        $agendaId = null;
        if (empty($agendas)) {
            // Create default agenda
            $agendaId = $agendaRepo->generateUuid();
            $agendaRepo->create($agendaId, $tenantId, $meetingId, 1, 'Ordre du jour');
            audit_log('agenda_created', 'agenda', $agendaId, [
                'meeting_id' => $meetingId,
                'title' => 'Ordre du jour',
                'auto_created' => true
            ]);
        } else {
            // Use first/main agenda
            $agendaId = $agendas[0]['agenda_id'];
        }

        // Create motion
        $motionRepo = new MotionRepository();
        $motionId = $motionRepo->generateUuid();

        $motionRepo->create(
            $motionId,
            $tenantId,
            $meetingId,
            $agendaId,
            $title,
            $description,
            $secret,
            null, // vote_policy_id
            null  // quorum_policy_id
        );

        audit_log('motion_created', 'motion', $motionId, [
            'meeting_id' => $meetingId,
            'agenda_id' => $agendaId,
            'title' => $title,
            'secret' => $secret,
            'created_via' => 'simple_endpoint'
        ]);

        return ['motion_id' => $motionId, 'agenda_id' => $agendaId];
    });

    api_ok([
        'motion_id' => $result['motion_id'],
        'agenda_id' => $result['agenda_id'],
        'created' => true
    ]);

} catch (Throwable $e) {
    error_log('motion_create_simple.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
