<?php
// public/api/v1/motions.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Repository\MotionRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Core\Validation\InputValidator;

api_require_role('operator');

try {
    $in = api_request('POST');

    // Validate input with schema
    $v = InputValidator::schema()
        ->uuid('agenda_id')->required()
        ->uuid('motion_id')->optional()
        ->string('title')->required()->minLength(1)->maxLength(500)
        ->string('description')->optional()->maxLength(10000)
        ->boolean('secret')->default(false)
        ->uuid('vote_policy_id')->optional()
        ->uuid('quorum_policy_id')->optional()
        ->validate($in);
    $v->failIfInvalid();

    $agendaId       = $v->get('agenda_id');
    $motionId       = $v->get('motion_id', '');
    $title          = $v->get('title');
    $description    = $v->get('description', '');
    $secret         = $v->get('secret', false);
    $votePolicyId   = $v->get('vote_policy_id', '');
    $quorumPolicyId = $v->get('quorum_policy_id', '');

    $motionRepo = new MotionRepository();
    $meetingRepo = new MeetingRepository();

    // Resolve meeting via agenda
    $agenda = $motionRepo->findAgendaWithMeeting($agendaId, api_current_tenant_id());
    if (!$agenda) api_fail('agenda_not_found', 404);

    api_guard_meeting_not_validated((string)$agenda['meeting_id']);

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

    // transaction via db()
    db()->beginTransaction();

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

        db()->commit();
        api_ok(['motion_id' => (string)$motionId, 'created' => true]);
    }

    // Update: ensure motion exists + not deleted
    $motion = $motionRepo->findByIdForTenant($motionId, api_current_tenant_id());
    if (!$motion) {
        db()->rollBack();
        api_fail('motion_not_found', 404);
    }
    // Hard guardrail: prevent changing agenda_id across meeting
    if ((string)$motion['agenda_id'] !== $agendaId) {
        db()->rollBack();
        api_fail('agenda_mismatch', 409, ['detail' => 'La motion appartient à un autre agenda.']);
    }
    if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
        db()->rollBack();
        api_fail('motion_active_locked', 409, ['detail' => 'Motion active : édition interdite pendant le vote.']);
    }
    if (!empty($motion['closed_at'])) {
        db()->rollBack();
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

    db()->commit();
    api_ok(['motion_id' => (string)$motionId, 'created' => false]);

} catch (Throwable $e) {
    // transaction via db()
    if (db()->inTransaction()) db()->rollBack();
    error_log('motions.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
