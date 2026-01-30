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
}
