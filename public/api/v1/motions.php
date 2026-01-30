<?php
// public/api/v1/motions.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

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

    $votePolicyId = trim((string)($in['vote_policy_id'] ?? ''));   // optional override
    $quorumPolicyId = trim((string)($in['quorum_policy_id'] ?? '')); // optional override
    if ($votePolicyId !== '' && !api_is_uuid($votePolicyId)) api_fail('invalid_vote_policy_id', 422);
    if ($quorumPolicyId !== '' && !api_is_uuid($quorumPolicyId)) api_fail('invalid_quorum_policy_id', 422);

    // Resolve meeting via agenda
    $agenda = db_select_one(
        "SELECT a.id, a.meeting_id, m.tenant_id
         FROM agendas a
         JOIN meetings m ON m.id = a.meeting_id
         WHERE a.id = :aid AND m.tenant_id = :tid",
        [':aid' => $agendaId, ':tid' => api_current_tenant_id()]
    );
    if (!$agenda) api_fail('agenda_not_found', 404);

    // Validate policies belong to tenant if provided
    if ($votePolicyId !== '') {
        $vp = db_select_one("SELECT id FROM vote_policies WHERE tenant_id=:tid AND id=:id", [':tid'=>api_current_tenant_id(), ':id'=>$votePolicyId]);
        if (!$vp) api_fail('vote_policy_not_found', 404);
    }
    if ($quorumPolicyId !== '') {
        $qp = db_select_one("SELECT id FROM quorum_policies WHERE tenant_id=:tid AND id=:id", [':tid'=>api_current_tenant_id(), ':id'=>$quorumPolicyId]);
        if (!$qp) api_fail('quorum_policy_not_found', 404);
    }

    global $pdo;
    $pdo->beginTransaction();

    if ($motionId === '') {
        $motionId = db_scalar("SELECT gen_random_uuid()") ?: null;
        if (!$motionId) {
            $pdo->rollBack();
            api_fail('uuid_failed', 500);
        }

        db_execute(
            "INSERT INTO motions (id, tenant_id, meeting_id, agenda_id, title, description, secret, vote_policy_id, quorum_policy_id, created_at)
             VALUES (:id, :tid, :mid, :aid, :title, :desc, :secret, NULLIF(:vpid,''), NULLIF(:qpid,''), now())",
            [
                ':id' => $motionId,
                ':tid' => api_current_tenant_id(),
                ':mid' => $agenda['meeting_id'],
                ':aid' => $agendaId,
                ':title' => $title,
                ':desc' => $description,
                ':secret' => $secret ? 't' : 'f',
                ':vpid' => $votePolicyId,
                ':qpid' => $quorumPolicyId,
            ]
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
    $motion = db_select_one(
        "SELECT id, meeting_id, agenda_id, opened_at, closed_at
         FROM motions
         WHERE tenant_id=:tid AND id=:id",
        [':tid'=>api_current_tenant_id(), ':id'=>$motionId]
    );
    if (!$motion) {
        $pdo->rollBack();
        api_fail('motion_not_found', 404);
    }
    // Hard guardrail: prevent changing agenda_id across meeting
    if ((string)$motion['agenda_id'] !== $agendaId) {
        $pdo->rollBack();
        api_fail('agenda_mismatch', 409, ['detail' => 'La motion appartient à un autre agenda.']);
    }
    // Garde-fous backend :
    // - une motion ACTIVE (ouverte et non clôturée) ne doit pas être modifiée pendant le vote.
    // - une motion clôturée ne doit pas être modifiée.
    if (!empty($motion['opened_at']) && empty($motion['closed_at'])) {
        $pdo->rollBack();
        api_fail('motion_active_locked', 409, ['detail' => 'Motion active : édition interdite pendant le vote.']);
    }
    if (!empty($motion['closed_at'])) {
        $pdo->rollBack();
        api_fail('motion_closed_locked', 409, ['detail' => 'Motion clôturée : édition interdite.']);
    }

    db_execute(
        "UPDATE motions
         SET title=:title,
             description=:desc,
             secret=:secret,
             vote_policy_id=NULLIF(:vpid,''),
             quorum_policy_id=NULLIF(:qpid,'')
         WHERE tenant_id=:tid AND id=:id",
        [
            ':title'=>$title,
            ':desc'=>$description,
            ':secret'=>$secret ? 't' : 'f',
            ':vpid'=>$votePolicyId,
            ':qpid'=>$quorumPolicyId,
            ':tid'=>api_current_tenant_id(),
            ':id'=>$motionId
        ]
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
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('motions.php error: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}
