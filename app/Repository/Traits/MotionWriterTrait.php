<?php

declare(strict_types=1);

namespace AgVote\Repository\Traits;

use Throwable;

/**
 * Motion write methods — create, update, delete, state transitions.
 *
 * Used by: MotionRepository (via trait inclusion).
 * All methods delegate to AbstractRepository::execute().
 */
trait MotionWriterTrait {
    public function create(
        string $id,
        string $tenantId,
        string $meetingId,
        ?string $agendaId,
        string $title,
        string $description,
        bool $secret,
        ?string $votePolicyId,
        ?string $quorumPolicyId,
    ): void {
        $this->execute(
            "INSERT INTO motions (id, tenant_id, meeting_id, agenda_id, title, description, secret, vote_policy_id, quorum_policy_id, created_at)
             VALUES (:id, :tid, :mid, NULLIF(:aid,'')::uuid, :title, :desc, :secret, NULLIF(:vpid,'')::uuid, NULLIF(:qpid,'')::uuid, now())",
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':mid' => $meetingId,
                ':aid' => $agendaId ?? '',
                ':title' => $title,
                ':desc' => $description,
                ':secret' => $secret ? 't' : 'f',
                ':vpid' => $votePolicyId ?? '',
                ':qpid' => $quorumPolicyId ?? '',
            ],
        );
    }

    public function update(
        string $motionId,
        string $tenantId,
        string $title,
        string $description,
        bool $secret,
        ?string $votePolicyId,
        ?string $quorumPolicyId,
    ): void {
        $this->execute(
            "UPDATE motions
             SET title = :title, description = :desc, secret = :secret,
                 vote_policy_id = NULLIF(:vpid,'')::uuid, quorum_policy_id = NULLIF(:qpid,'')::uuid
             WHERE tenant_id = :tid AND id = :id",
            [
                ':title' => $title,
                ':desc' => $description,
                ':secret' => $secret ? 't' : 'f',
                ':vpid' => $votePolicyId ?? '',
                ':qpid' => $quorumPolicyId ?? '',
                ':tid' => $tenantId,
                ':id' => $motionId,
            ],
        );
    }

    public function delete(string $motionId, string $tenantId): void {
        $this->execute(
            'DELETE FROM motions WHERE tenant_id = :tid AND id = :id',
            [':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function markOpened(string $motionId, string $tenantId): int {
        return $this->execute(
            'UPDATE motions SET opened_at = COALESCE(opened_at, now()), closed_at = NULL
             WHERE tenant_id = :tid AND id = :id AND closed_at IS NULL',
            [':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function markClosed(string $motionId, string $tenantId): void {
        $this->execute(
            'UPDATE motions SET closed_at = now()
             WHERE tenant_id = :tid AND id = :id AND closed_at IS NULL',
            [':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function markOpenedInMeeting(string $tenantId, string $motionId, string $meetingId): void {
        $this->execute(
            'UPDATE motions
             SET opened_at = COALESCE(opened_at, now()), closed_at = NULL
             WHERE tenant_id = :tid AND id = :id AND meeting_id = :mid AND closed_at IS NULL',
            [':tid' => $tenantId, ':id' => $motionId, ':mid' => $meetingId],
        );
    }

    public function updatePosition(string $motionId, string $tenantId, int $position): void {
        $this->execute(
            'UPDATE motions SET position = :pos WHERE tenant_id = :tid AND id = :id',
            [':pos' => $position, ':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function reorderAll(string $meetingId, string $tenantId, array $motionIds): void {
        foreach ($motionIds as $position => $motionId) {
            $this->execute(
                'UPDATE motions SET position = :pos
                 WHERE tenant_id = :tid AND meeting_id = :mid AND id = :id',
                [':pos' => $position + 1, ':tid' => $tenantId, ':mid' => $meetingId, ':id' => $motionId],
            );
        }
    }

    public function updateManualTally(string $motionId, int $total, int $for, int $against, int $abstain, string $tenantId): void {
        $this->execute(
            'UPDATE motions SET manual_total = :t, manual_for = :f, manual_against = :a, manual_abstain = :ab WHERE id = :id AND tenant_id = :tid',
            [':t' => $total, ':f' => $for, ':a' => $against, ':ab' => $abstain, ':id' => $motionId, ':tid' => $tenantId],
        );
    }

    public function updateOfficialResults(
        string $motionId,
        string $source,
        float $for,
        float $against,
        float $abstain,
        float $total,
        string $decision,
        string $reason,
        string $tenantId,
    ): void {
        $this->execute(
            'UPDATE motions SET
               official_source = :src, official_for = :f, official_against = :a,
               official_abstain = :ab, official_total = :t,
               decision = :d, decision_reason = :r, decided_at = NOW()
             WHERE id = :id AND tenant_id = :tid',
            [
                ':src' => $source, ':f' => $for, ':a' => $against,
                ':ab' => $abstain, ':t' => $total,
                ':d' => $decision, ':r' => $reason, ':id' => $motionId,
                ':tid' => $tenantId,
            ],
        );
    }

    public function resetStatesForMeeting(string $meetingId, string $tenantId): void {
        $this->execute(
            'UPDATE motions
             SET opened_at = NULL, closed_at = NULL,
                 manual_total = NULL, manual_for = NULL, manual_against = NULL, manual_abstain = NULL,
                 updated_at = now()
             WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    public function ensureOfficialColumns(): void {
        try {
            $this->execute(
                'ALTER TABLE motions
                  ADD COLUMN IF NOT EXISTS official_source text,
                  ADD COLUMN IF NOT EXISTS official_for double precision,
                  ADD COLUMN IF NOT EXISTS official_against double precision,
                  ADD COLUMN IF NOT EXISTS official_abstain double precision,
                  ADD COLUMN IF NOT EXISTS official_total double precision,
                  ADD COLUMN IF NOT EXISTS decision text,
                  ADD COLUMN IF NOT EXISTS decision_reason text,
                  ADD COLUMN IF NOT EXISTS decided_at timestamptz',
            );
        } catch (Throwable $e) { /* best-effort */
        }
    }
}
