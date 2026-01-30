<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les actions manuelles (audit trail append-only).
 */
class ManualActionRepository extends AbstractRepository
{
    /**
     * Insere une action manuelle dans la table manual_actions.
     */
    public function create(
        string $tenantId,
        string $meetingId,
        string $motionId,
        string $memberId,
        string $actionType,
        string $valueJson,
        string $justification
    ): void {
        $this->execute(
            "INSERT INTO manual_actions (tenant_id, meeting_id, motion_id, member_id, action_type, value, justification, operator_user_id)
             VALUES (:tid, :mid, :moid, :uid, :action, :value::jsonb, :justif, NULL)",
            [
                ':tid' => $tenantId, ':mid' => $meetingId,
                ':moid' => $motionId, ':uid' => $memberId,
                ':action' => $actionType, ':value' => $valueJson,
                ':justif' => $justification,
            ]
        );
    }

    /**
     * Insere une action de type paper_ballot (member_id NULL).
     */
    public function createPaperBallotAction(
        string $tenantId,
        string $meetingId,
        string $motionId,
        string $voteValue,
        string $justification
    ): void {
        $this->execute(
            "INSERT INTO manual_actions(tenant_id, meeting_id, motion_id, member_id, action_type, value, justification, operator_user_id, signature_hash, created_at)
             VALUES (:t, :m, :mo, NULL, 'paper_ballot', jsonb_build_object('vote_value', :v), :j, NULL, NULL, NOW())",
            [
                ':t' => $tenantId,
                ':m' => $meetingId,
                ':mo' => $motionId,
                ':v' => $voteValue,
                ':j' => $justification,
            ]
        );
    }

    /**
     * Liste les actions manuelles d'une seance (pour rapport).
     */
    public function listForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT action_type, value, justification, created_at
             FROM manual_actions
             WHERE meeting_id = :mid
             ORDER BY created_at ASC",
            [':mid' => $meetingId]
        );
    }
}
