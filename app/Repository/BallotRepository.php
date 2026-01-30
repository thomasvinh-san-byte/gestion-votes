<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les bulletins de vote (ballots).
 */
class BallotRepository extends AbstractRepository
{
    /**
     * Tally par valeur (for/against/abstain/nsp) pour une motion.
     */
    public function tallyByMotion(string $motionId): array
    {
        return $this->selectAll(
            "SELECT value, COUNT(*) AS count, COALESCE(SUM(weight), 0) AS weight
             FROM ballots WHERE motion_id = :mid GROUP BY value",
            [':mid' => $motionId]
        );
    }

    /**
     * Tally pondere agrege (for/against/abstain/total) pour OfficialResultsService.
     */
    public function weightedTally(string $motionId): array
    {
        $row = $this->selectOne(
            "SELECT
               COALESCE(SUM(CASE WHEN COALESCE(value::text, choice) = 'for' THEN COALESCE(weight, effective_power, 0) ELSE 0 END), 0) AS w_for,
               COALESCE(SUM(CASE WHEN COALESCE(value::text, choice) = 'against' THEN COALESCE(weight, effective_power, 0) ELSE 0 END), 0) AS w_against,
               COALESCE(SUM(CASE WHEN COALESCE(value::text, choice) = 'abstain' THEN COALESCE(weight, effective_power, 0) ELSE 0 END), 0) AS w_abstain,
               COALESCE(SUM(COALESCE(weight, effective_power, 0)), 0) AS w_total
             FROM ballots WHERE motion_id = :mid",
            [':mid' => $motionId]
        );
        return $row ?: ['w_for' => 0, 'w_against' => 0, 'w_abstain' => 0, 'w_total' => 0];
    }

    /**
     * Existe-t-il au moins un bulletin pour cette motion?
     */
    public function existsForMotion(string $motionId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM ballots WHERE motion_id = :mid LIMIT 1",
            [':mid' => $motionId]
        );
    }
}
