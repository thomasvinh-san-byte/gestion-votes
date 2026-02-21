<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour le wizard de seance (status rapide).
 *
 * Centralise les requetes legeres utilisees par wizard_status.php
 * pour le polling rapide de l'etat d'une seance.
 */
class WizardRepository extends AbstractRepository {
    /**
     * Recupere les infos basiques d'une seance.
     */
    public function getMeetingBasics(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id, title, status, vote_policy_id, quorum_policy_id, current_motion_id
             FROM meetings WHERE id = :id AND tenant_id = :tid',
            [':id' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Compte le nombre total de presences enregistrees pour une seance.
     */
    public function countAttendances(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Compte les presences eligibles (present, remote, proxy).
     */
    public function countPresentAttendances(string $meetingId, string $tenantId): int {
        return (int) ($this->scalar(
            "SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid AND tenant_id = :tid AND mode IN ('present','remote','proxy')",
            [':mid' => $meetingId, ':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Compte les membres actifs du tenant (fallback si pas de presences).
     */
    public function countActiveMembers(string $tenantId): int {
        return (int) ($this->scalar(
            'SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true',
            [':tid' => $tenantId],
        ) ?? 0);
    }

    /**
     * Compte les motions et les motions fermees d'une seance.
     */
    public function getMotionsCounts(string $meetingId, string $tenantId): array {
        $row = $this->selectOne(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed
             FROM motions WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
        return [
            'total' => (int) ($row['total'] ?? 0),
            'closed' => (int) ($row['closed'] ?? 0),
        ];
    }

    /**
     * Verifie si un president est assigne a la seance.
     */
    public function hasPresident(string $meetingId, string $tenantId): bool {
        return (bool) $this->scalar(
            "SELECT 1 FROM meeting_roles
             WHERE meeting_id = :mid AND tenant_id = :tid AND role = 'president' AND revoked_at IS NULL
             LIMIT 1",
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Recupere le seuil de quorum pour une politique.
     */
    public function getQuorumThreshold(string $policyId, string $tenantId): ?float {
        $result = $this->scalar(
            'SELECT threshold FROM quorum_policies WHERE id = :id AND tenant_id = :tid',
            [':id' => $policyId, ':tid' => $tenantId],
        );
        return $result !== false ? (float) $result : null;
    }
}
