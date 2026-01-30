<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les procedures d'urgence (emergency_procedures + meeting_emergency_checks).
 */
class EmergencyProcedureRepository extends AbstractRepository
{
    /**
     * Liste les procedures d'urgence par audience.
     */
    public function listByAudience(string $audience): array
    {
        return $this->selectAll(
            "SELECT code, title, steps_json
             FROM emergency_procedures
             WHERE audience = ?
             ORDER BY code ASC",
            [$audience]
        );
    }

    /**
     * Liste les procedures d'urgence par audience (avec colonne audience).
     */
    public function listByAudienceWithField(string $audience): array
    {
        return $this->selectAll(
            "SELECT code, title, audience, steps_json
             FROM emergency_procedures
             WHERE audience = ?
             ORDER BY code ASC",
            [$audience]
        );
    }

    /**
     * Liste les checks d'urgence pour une seance.
     */
    public function listChecksForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT procedure_code, item_index, checked
             FROM meeting_emergency_checks
             WHERE meeting_id = ?",
            [$meetingId]
        );
    }
}
