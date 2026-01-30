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
     * Tally pondere pour le dashboard (for/against/abstain + ballots_count).
     */
    public function dashboardTally(string $tenantId, string $meetingId, string $motionId): array
    {
        $row = $this->selectOne(
            "SELECT
                COUNT(*)::int AS ballots_count,
                COALESCE(SUM(CASE WHEN COALESCE(value::text, choice)='for' THEN weight ELSE 0 END),0)::int AS weight_for,
                COALESCE(SUM(CASE WHEN COALESCE(value::text, choice)='against' THEN weight ELSE 0 END),0)::int AS weight_against,
                COALESCE(SUM(CASE WHEN COALESCE(value::text, choice)='abstain' THEN weight ELSE 0 END),0)::int AS weight_abstain
             FROM ballots
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :moid",
            [':tid' => $tenantId, ':mid' => $meetingId, ':moid' => $motionId]
        );
        return $row ?: ['ballots_count' => 0, 'weight_for' => 0, 'weight_against' => 0, 'weight_abstain' => 0];
    }

    /**
     * Nombre de bulletins pour une motion (pour validation readiness).
     */
    public function countForMotion(string $tenantId, string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :moid",
            [':tid' => $tenantId, ':mid' => $meetingId, ':moid' => $motionId]
        ) ?? 0);
    }

    /**
     * Insere un ballot manuel et retourne son ID.
     */
    public function insertManual(
        string $tenantId,
        string $meetingId,
        string $motionId,
        string $memberId,
        string $value,
        string $weight
    ): string {
        $row = $this->insertReturning(
            "INSERT INTO ballots (tenant_id, meeting_id, motion_id, member_id, value, weight, cast_at, is_proxy_vote, source)
             VALUES (:tid, :mid, :moid, :uid, :value, :weight, NOW(), false, 'manual')
             RETURNING id",
            [
                ':tid' => $tenantId, ':mid' => $meetingId,
                ':moid' => $motionId, ':uid' => $memberId,
                ':value' => $value, ':weight' => $weight,
            ]
        );
        return (string)($row['id'] ?? '');
    }

    /**
     * Upsert un bulletin de vote (cast ou re-vote).
     */
    public function castBallot(
        string $tenantId,
        string $motionId,
        string $memberId,
        string $value,
        float $weight,
        bool $isProxyVote,
        ?string $proxySourceMemberId
    ): void {
        $this->execute(
            "INSERT INTO ballots (
              id, tenant_id, motion_id, member_id, value, weight, cast_at, is_proxy_vote, proxy_source_member_id
            ) VALUES (
              gen_random_uuid(), :tid, :mid, :mem, :value, :weight, now(), :proxy, :proxy_src
            )
            ON CONFLICT (motion_id, member_id) DO UPDATE
            SET value = EXCLUDED.value,
                weight = EXCLUDED.weight,
                cast_at = now(),
                is_proxy_vote = EXCLUDED.is_proxy_vote,
                proxy_source_member_id = EXCLUDED.proxy_source_member_id",
            [
                ':tid' => $tenantId, ':mid' => $motionId, ':mem' => $memberId,
                ':value' => $value, ':weight' => $weight,
                ':proxy' => $isProxyVote, ':proxy_src' => $proxySourceMemberId,
            ]
        );
    }

    /**
     * Trouve un bulletin par motion et membre (apres cast).
     */
    public function findByMotionAndMember(string $motionId, string $memberId): ?array
    {
        return $this->selectOne(
            "SELECT motion_id, member_id, value, weight, cast_at, is_proxy_vote, proxy_source_member_id
             FROM ballots
             WHERE motion_id = :mid AND member_id = :mem",
            [':mid' => $motionId, ':mem' => $memberId]
        );
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
