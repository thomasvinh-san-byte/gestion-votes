<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for ballots.
 */
class BallotRepository extends AbstractRepository
{
    /**
     * Lists ballots for a motion (for operator display).
     */
    public function listForMotion(string $motionId): array
    {
        return $this->selectAll(
            "SELECT b.member_id, COALESCE(b.value::text, b.choice) AS value, b.weight, b.cast_at, COALESCE(b.source, 'tablet') AS source
             FROM ballots b
             WHERE b.motion_id = :mid
             ORDER BY b.cast_at ASC",
            [':mid' => $motionId]
        );
    }

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
     * Tally en temps reel pour WebSocket broadcast.
     */
    public function getTallyForMotion(string $tenantId, string $meetingId, string $motionId): array
    {
        $row = $this->selectOne(
            "SELECT
                COUNT(*)::int AS total_ballots,
                COUNT(*) FILTER (WHERE COALESCE(value::text, choice) = 'for')::int AS count_for,
                COUNT(*) FILTER (WHERE COALESCE(value::text, choice) = 'against')::int AS count_against,
                COUNT(*) FILTER (WHERE COALESCE(value::text, choice) = 'abstain')::int AS count_abstain,
                COUNT(*) FILTER (WHERE COALESCE(value::text, choice) = 'nsp')::int AS count_nsp,
                COALESCE(SUM(weight) FILTER (WHERE COALESCE(value::text, choice) = 'for'), 0)::float8 AS weight_for,
                COALESCE(SUM(weight) FILTER (WHERE COALESCE(value::text, choice) = 'against'), 0)::float8 AS weight_against,
                COALESCE(SUM(weight) FILTER (WHERE COALESCE(value::text, choice) = 'abstain'), 0)::float8 AS weight_abstain,
                COALESCE(SUM(weight), 0)::float8 AS weight_total
             FROM ballots
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :moid",
            [':tid' => $tenantId, ':mid' => $meetingId, ':moid' => $motionId]
        );
        return $row ?: [
            'total_ballots' => 0,
            'count_for' => 0,
            'count_against' => 0,
            'count_abstain' => 0,
            'count_nsp' => 0,
            'weight_for' => 0.0,
            'weight_against' => 0.0,
            'weight_abstain' => 0.0,
            'weight_total' => 0.0,
        ];
    }

    /**
     * Compte les bulletins par choix (for/against/abstain) pour une motion.
     */
    public function countChoicesByMotion(string $motionId): array
    {
        $row = $this->selectOne(
            "SELECT
               SUM(CASE WHEN COALESCE(value::text, choice)='for' THEN 1 ELSE 0 END) AS c_for,
               SUM(CASE WHEN COALESCE(value::text, choice)='against' THEN 1 ELSE 0 END) AS c_against,
               SUM(CASE WHEN COALESCE(value::text, choice)='abstain' THEN 1 ELSE 0 END) AS c_abstain
             FROM ballots WHERE motion_id = :mid",
            [':mid' => $motionId]
        );
        return $row ?: ['c_for' => 0, 'c_against' => 0, 'c_abstain' => 0];
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
     * Insere un ballot depuis un token de vote (tablette/QR code).
     */
    public function insertFromToken(
        string $tenantId,
        string $meetingId,
        string $motionId,
        string $memberId,
        string $value,
        float $weight = 1.0,
        string $source = 'tablet'
    ): void {
        $this->execute(
            "INSERT INTO ballots (tenant_id, meeting_id, motion_id, member_id, value, weight, cast_at, source)
             VALUES (:tid, :mid, :moid, :uid, :value, :weight, NOW(), :source)",
            [
                ':tid' => $tenantId,
                ':mid' => $meetingId,
                ':moid' => $motionId,
                ':uid' => $memberId,
                ':value' => $value,
                ':weight' => $weight,
                ':source' => $source,
            ]
        );
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
                ':proxy' => $isProxyVote ? 't' : 'f', ':proxy_src' => $proxySourceMemberId,
            ]
        );
    }

    /**
     * Trouve un bulletin par motion et membre (apres cast).
     */
    public function findByMotionAndMember(string $motionId, string $memberId): ?array
    {
        return $this->selectOne(
            "SELECT motion_id, member_id, value, weight, cast_at, is_proxy_vote, proxy_source_member_id, COALESCE(source, 'tablet') AS source
             FROM ballots
             WHERE motion_id = :mid AND member_id = :mem",
            [':mid' => $motionId, ':mem' => $memberId]
        );
    }

    /**
     * Liste detaillee des bulletins pour une motion (pour rapport PV Annexe D).
     */
    public function listDetailedForMotion(string $motionId): array
    {
        return $this->selectAll(
            "SELECT
               COALESCE(b.value::text, b.choice) AS choice,
               COALESCE(b.weight, b.effective_power, 0) AS effective_power,
               b.is_proxy_vote,
               b.member_id AS giver_member_id,
               b.proxy_source_member_id AS receiver_member_id,
               mg.full_name AS giver_name,
               mr.full_name AS receiver_name
             FROM ballots b
             LEFT JOIN members mg ON mg.id = b.member_id
             LEFT JOIN members mr ON mr.id = b.proxy_source_member_id
             WHERE b.motion_id = :mid
             ORDER BY COALESCE(mg.full_name, ''), COALESCE(mr.full_name, '')",
            [':mid' => $motionId]
        );
    }

    /**
     * Bulletins crees apres la cloture de leur motion (pour trust_checks).
     */
    public function listVotesAfterClose(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT b.id, m.title
             FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid
               AND m.closed_at IS NOT NULL
               AND b.cast_at > m.closed_at",
            [':mid' => $meetingId]
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

    /**
     * Compte les bulletins directs eligibles pour une motion (votant present/remote, actif, non checked_out).
     */
    public function countEligibleDirect(string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT count(*)
             FROM ballots b
             JOIN members mem ON mem.id = b.member_id
             JOIN attendances a ON a.meeting_id = :mid AND a.member_id = b.member_id
             WHERE b.motion_id = :moid
               AND COALESCE(b.is_proxy_vote, false) = false
               AND mem.is_active = true
               AND a.checked_out_at IS NULL
               AND a.mode IN ('present','remote')",
            [':mid' => $meetingId, ':moid' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte les bulletins proxy eligibles pour une motion.
     */
    public function countEligibleProxy(string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT count(*)
             FROM ballots b
             JOIN members mandant ON mandant.id = b.member_id
             JOIN attendances a ON a.meeting_id = :mid AND a.member_id = b.proxy_source_member_id
             JOIN proxies p ON p.meeting_id = :mid2
                          AND p.giver_member_id = b.member_id
                          AND p.receiver_member_id = b.proxy_source_member_id
                          AND p.revoked_at IS NULL
             WHERE b.motion_id = :moid
               AND COALESCE(b.is_proxy_vote, false) = true
               AND b.proxy_source_member_id IS NOT NULL
               AND mandant.is_active = true
               AND a.checked_out_at IS NULL
               AND a.mode IN ('present','remote')",
            [':mid' => $meetingId, ':mid2' => $meetingId, ':moid' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte le total de bulletins pour une motion (sans filtre tenant).
     */
    public function countByMotionId(string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT count(*) FROM ballots WHERE motion_id = :mid",
            [':mid' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte les bulletins directs non eligibles pour une motion.
     */
    public function countInvalidDirect(string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT count(*)
             FROM ballots b
             JOIN members mem ON mem.id = b.member_id
             LEFT JOIN attendances a ON a.meeting_id = :mid AND a.member_id = b.member_id
             WHERE b.motion_id = :moid
               AND COALESCE(b.is_proxy_vote, false) = false
               AND mem.is_active = true
               AND (a.member_id IS NULL OR a.checked_out_at IS NOT NULL OR a.mode NOT IN ('present','remote'))",
            [':mid' => $meetingId, ':moid' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte les bulletins proxy non eligibles pour une motion.
     */
    public function countInvalidProxy(string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT count(*)
             FROM ballots b
             JOIN members mandant ON mandant.id = b.member_id
             LEFT JOIN attendances a ON a.meeting_id = :mid AND a.member_id = b.proxy_source_member_id
             LEFT JOIN proxies p ON p.meeting_id = :mid2
                                AND p.giver_member_id = b.member_id
                                AND p.receiver_member_id = b.proxy_source_member_id
                                AND p.revoked_at IS NULL
             WHERE b.motion_id = :moid
               AND COALESCE(b.is_proxy_vote, false) = true
               AND mandant.is_active = true
               AND (
                    b.proxy_source_member_id IS NULL
                    OR a.member_id IS NULL
                    OR a.checked_out_at IS NOT NULL
                    OR a.mode NOT IN ('present','remote')
                    OR p.id IS NULL
               )",
            [':mid' => $meetingId, ':mid2' => $meetingId, ':moid' => $motionId]
        ) ?? 0);
    }

    // =========================================================================
    // PAPER BALLOTS
    // =========================================================================

    /**
     * Genere un UUID via PostgreSQL (pour code bulletin papier).
     */
    public function newUuid(): string
    {
        return $this->generateUuid();
    }

    /**
     * Cree un bulletin papier.
     */
    public function createPaperBallot(string $meetingId, string $motionId, string $code, string $codeHash): void
    {
        $this->execute(
            "INSERT INTO paper_ballots(meeting_id, motion_id, code, code_hash) VALUES (:m, :mo, :c, :h)",
            [':m' => $meetingId, ':mo' => $motionId, ':c' => $code, ':h' => $codeHash]
        );
    }

    /**
     * Trouve un bulletin papier non utilise par son hash de code (avec tenant_id via meeting).
     */
    public function findUnusedPaperBallotByHash(string $codeHash): ?array
    {
        return $this->selectOne(
            "SELECT pb.*, m.tenant_id
             FROM paper_ballots pb
             JOIN meetings m ON m.id = pb.meeting_id
             WHERE pb.code_hash = :hash AND pb.used_at IS NULL",
            [':hash' => $codeHash]
        );
    }

    /**
     * Marque un bulletin papier comme utilise.
     */
    public function markPaperBallotUsed(string $id): void
    {
        $this->execute(
            "UPDATE paper_ballots SET used_at = NOW(), used_by_operator = true WHERE id = :id",
            [':id' => $id]
        );
    }

    /**
     * Export audit CSV: ballots avec token, attendance, manual_action.
     */
    public function listAuditExportForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT
                b.id AS ballot_id,
                mo.id AS motion_id,
                mo.title AS motion_title,
                b.member_id,
                mb.full_name AS voter_name,
                COALESCE(a.mode::text, 'absent') AS attendance_mode,
                b.value::text AS value,
                b.weight,
                b.is_proxy_vote,
                b.proxy_source_member_id,
                b.cast_at,
                COALESCE(b.source, 'tablet') AS source,
                vt.id AS token_id,
                LEFT(vt.token_hash, 12) AS token_hash_prefix,
                vt.expires_at AS token_expires_at,
                vt.used_at AS token_used_at,
                ma.justification AS manual_justification
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             LEFT JOIN members mb ON mb.id = b.member_id
             LEFT JOIN attendances a ON a.meeting_id = mo.meeting_id AND a.member_id = b.member_id
             LEFT JOIN LATERAL (
                SELECT id, token_hash, expires_at, used_at
                FROM vote_tokens
                WHERE motion_id = b.motion_id AND member_id = b.member_id
                ORDER BY used_at DESC NULLS LAST, created_at DESC
                LIMIT 1
             ) vt ON true
             LEFT JOIN LATERAL (
                SELECT justification
                FROM manual_actions
                WHERE meeting_id = mo.meeting_id AND action_type = 'manual_vote'
                  AND motion_id = b.motion_id AND member_id = b.member_id
                ORDER BY created_at DESC
                LIMIT 1
             ) ma ON true
             WHERE mo.meeting_id = ?
             ORDER BY mo.position ASC NULLS LAST, mo.created_at ASC, b.cast_at ASC",
            [$meetingId]
        );
    }

    /**
     * Export votes CSV: ballots nominatifs pour une seance.
     */
    public function listVotesExportForMeeting(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT
                mo.title AS motion_title,
                mo.position AS motion_position,
                mb.full_name AS voter_name,
                b.value::text AS value,
                b.weight,
                b.is_proxy_vote,
                ps.full_name AS proxy_source_name,
                b.cast_at,
                COALESCE(b.source, 'electronic') AS source
             FROM motions mo
             JOIN meetings mt ON mt.id = mo.meeting_id AND mt.id = ?
             LEFT JOIN ballots b ON b.motion_id = mo.id
             LEFT JOIN members mb ON mb.id = b.member_id
             LEFT JOIN members ps ON ps.id = b.proxy_source_member_id
             WHERE mo.meeting_id = ?
             ORDER BY mo.position ASC NULLS LAST, mo.created_at ASC, mb.full_name ASC NULLS LAST",
            [$meetingId, $meetingId]
        );
    }

    /**
     * Liste les bulletins d'une motion avec source (pour anomalies).
     */
    public function listForMotionWithSource(string $tenantId, string $meetingId, string $motionId): array
    {
        return $this->selectAll(
            "SELECT b.member_id, b.value::text AS value, b.cast_at, COALESCE(b.source,'tablet') AS source
             FROM ballots b
             WHERE b.tenant_id = :tid AND b.meeting_id = :mid AND b.motion_id = :mo
             ORDER BY b.cast_at ASC",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId]
        );
    }

    /**
     * Supprime tous les bulletins d'une seance (reset demo).
     */
    public function deleteByMeeting(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "DELETE FROM ballots WHERE meeting_id = :mid AND tenant_id = :tid",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Liste les votes sans presence enregistree (anomalie trust).
     */
    public function listVotesWithoutAttendance(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT DISTINCT b.member_id, m.full_name, mo.title AS motion_title
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             JOIN members m ON m.id = b.member_id
             LEFT JOIN attendances a ON a.meeting_id = :mid1 AND a.member_id = b.member_id AND a.tenant_id = :tid
             WHERE mo.meeting_id = :mid2
               AND (a.id IS NULL OR a.mode NOT IN ('present', 'remote'))
             ORDER BY m.full_name",
            [':mid1' => $meetingId, ':tid' => $tenantId, ':mid2' => $meetingId]
        );
    }

    /**
     * Liste les doubles votes (meme membre, meme motion, count > 1).
     */
    public function listDuplicateVotes(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT b.member_id, m.full_name, mo.title AS motion_title, COUNT(*) AS vote_count
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             JOIN members m ON m.id = b.member_id
             WHERE mo.meeting_id = :mid
             GROUP BY b.member_id, b.motion_id, m.full_name, mo.title
             HAVING COUNT(*) > 1",
            [':mid' => $meetingId]
        );
    }

    /**
     * Liste les incoherences de ponderation (ballot.weight != member.voting_power).
     */
    public function listWeightMismatches(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT b.member_id, m.full_name, m.voting_power AS expected_weight,
                    b.weight AS actual_weight, mo.title AS motion_title
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             JOIN members m ON m.id = b.member_id
             WHERE mo.meeting_id = :mid
               AND b.weight IS NOT NULL
               AND m.voting_power IS NOT NULL
               AND ABS(b.weight - m.voting_power) > 0.01",
            [':mid' => $meetingId]
        );
    }

    /**
     * Liste les votes manuels non justifies.
     */
    public function listUnjustifiedManualVotes(string $meetingId): array
    {
        // Manual votes without a corresponding manual_actions.notes entry
        return $this->selectAll(
            "SELECT b.id, m.full_name, mo.title AS motion_title
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             JOIN members m ON m.id = b.member_id
             LEFT JOIN manual_actions ma ON ma.motion_id = b.motion_id
               AND ma.member_id = b.member_id AND ma.action_type = 'manual_vote'
             WHERE mo.meeting_id = :mid
               AND b.source = 'manual'
               AND (ma.notes IS NULL OR ma.notes = '')",
            [':mid' => $meetingId]
        );
    }
}
