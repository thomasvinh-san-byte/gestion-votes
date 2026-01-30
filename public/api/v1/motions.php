<?php
// public/api/v1/motions.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\MeetingRepository;

api_require_role('operator');

try {
    $in = api_request('POST');

    // Create OR update
    $agendaId = trim((string)($in['agenda_id'] ?? ''));
    $motionId = trim((string)($in['motion_id'] ?? '')); // optional for update

    if ($agendaId === '' || !api_is_uuid($agendaId)) {
        api_fail('missing_agenda_id', 422, ['detail' => 'agenda_id est obligatoire (uuid).']);
    }
    if ($motionId !== '' && !api_is_uuid($motionId)) {
        api_fail('invalid_motion_id', 422, ['detail' => 'motion_id invalide.']);
    }

    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') api_fail('missing_title', 422, ['detail' => 'title est obligatoire.']);
    if (mb_strlen($title) > 80) api_fail('title_too_long', 422, ['detail' => 'title doit faire ≤ 80 caractères.']);

    $description = (string)($in['description'] ?? '');
    if (mb_strlen($description) > 5000) api_fail('description_too_long', 422, ['detail' => 'description trop longue.']);

    $secret = (bool)($in['secret'] ?? false);

    $votePolicyId = trim((string)($in['vote_policy_id'] ?? ''));
    $quorumPolicyId = trim((string)($in['quorum_policy_id'] ?? ''));
    if ($votePolicyId !== '' && !api_is_uuid($votePolicyId)) api_fail('invalid_vote_policy_id', 422);
    if ($quorumPolicyId !== '' && !api_is_uuid($quorumPolicyId)) api_fail('invalid_quorum_policy_id', 422);

    $motionRepo = new MotionRepository();
    $meetingRepo = new MeetingRepository();

    // Resolve meeting via agenda
    $agenda = $motionRepo->findAgendaWithMeeting($agendaId, api_current_tenant_id());
    if (!$agenda) api_fail('agenda_not_found', 404);

    // Validate policies belong to tenant if provided
    if ($votePolicyId !== '') {
        if (!$meetingRepo->votePolicyExists($votePolicyId, api_current_tenant_id())) {
            api_fail('vote_policy_not_found', 404);
        }
    }
    if ($quorumPolicyId !== '') {
        if (!$meetingRepo->quorumPolicyExists($quorumPolicyId, api_current_tenant_id())) {
            api_fail('quorum_policy_not_found', 404);
        }
    }

    $pdo = db();
    $pdo->beginTransaction();

    if ($motionId === '') {
        $motionId = $motionRepo->generateUuid();

        $motionRepo->create(
            $motionId,
            api_current_tenant_id(),
            $agenda['meeting_id'],
            $agendaId,
            $title,
            $description,
            $secret,
            $votePolicyId ?: null,
            $quorumPolicyId ?: null
        );

        audit_log('motion_created', 'motion', (string)$motionId, [
            'meeting_id' => (string)$agenda['meeting_id'],
            'agenda_id'  => $agendaId,
            'title'      => $title,
            'secret'     => $secret,
            'vote_policy_id'   => $votePolicyId ?: null,
            'quorum_policy_id' => $quorumPolicyId ?: null,
        ]);

        $pdo->commit();
        api_ok(['motion_id' => (string)$motionId, 'created' => true]);
    }

    // Update: ensure motion exists + not deleted
    $motion = $motionRepo->findByIdForTenant($motionId, api_current_tenant_id());
    if (!$motion) {
        $pdo->rollBack();
        api_fail('motion_not_found', 404);
    }
    // Hard guardrail: prevent changing agenda_id across meeting
    if ((string)$motion['agenda_id'] !== $agendaId) {
        $pdo->rollBack();
        api_fail('agenda_mismatch', 409, ['detail' => 'La motion appartient à un autre agenda.']);
    }
    if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
        $pdo->rollBack();
        api_fail('motion_active_locked', 409, ['detail' => 'Motion active : édition interdite pendant le vote.']);
    }
    if (!empty($motion['closed_at'])) {
        $pdo->rollBack();
        api_fail('motion_closed_locked', 409, ['detail' => 'Motion clôturée : édition interdite.']);
    }

    $motionRepo->update(
        $motionId,
        api_current_tenant_id(),
        $title,
        $description,
        $secret,
        $votePolicyId ?: null,
        $quorumPolicyId ?: null
    );

    audit_log('motion_updated', 'motion', (string)$motionId, [
        'meeting_id' => (string)$motion['meeting_id'],
        'agenda_id'  => $agendaId,
        'title'      => $title,
        'secret'     => $secret,
        'vote_policy_id'   => $votePolicyId ?: null,
        'quorum_policy_id' => $quorumPolicyId ?: null,
    ]);

    $pdo->commit();
    api_ok(['motion_id' => (string)$motionId, 'created' => false]);

} catch (Throwable $e) {
    $pdo = db();
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('motions.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
