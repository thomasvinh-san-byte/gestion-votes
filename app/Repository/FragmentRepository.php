<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository dedie aux fragments HTMX (drawer, live oob).
 *
 * Centralise les requetes SQL utilisees dans les fragments de rendu HTML.
 * Ces methodes sont optimisees pour le polling leger et l'affichage temps reel.
 */
class FragmentRepository extends AbstractRepository
{
    // =========================================================================
    // DRAWER READINESS
    // =========================================================================

    /**
     * Infos basiques d'une seance pour readiness.
     */
    public function findMeetingBasics(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, status, validated_at FROM meetings WHERE id = :id",
            [':id' => $meetingId]
        );
    }

    /**
     * Compte les membres d'une seance (via tenant de la seance).
     */
    public function countMembersForMeeting(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM members m
             JOIN meetings mt ON mt.tenant_id = m.tenant_id
             WHERE mt.id = :mid AND m.is_active = true",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Compte les resolutions d'une seance.
     */
    public function countMotionsForMeeting(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Compte les presences pointees d'une seance.
     */
    public function countAttendancesForMeeting(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    // =========================================================================
    // DRAWER MENU
    // =========================================================================

    /**
     * Infos seance pour menu.
     */
    public function findMeetingForMenu(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, status FROM meetings WHERE id = :id",
            [':id' => $meetingId]
        );
    }

    /**
     * Liste les motions d'une seance avec position et etat.
     */
    public function listMotionsForMenu(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT id, title, COALESCE(position, sort_order, 0) AS pos, opened_at, closed_at
             FROM motions
             WHERE meeting_id = :mid
             ORDER BY COALESCE(position, sort_order, 0) ASC",
            [':mid' => $meetingId]
        );
    }

    // =========================================================================
    // DRAWER INFOS
    // =========================================================================

    /**
     * Infos seance pour drawer infos.
     */
    public function findMeetingForInfos(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, status, validated_at FROM meetings WHERE id = :id",
            [':id' => $meetingId]
        );
    }

    /**
     * Trouve la motion ouverte pour une seance.
     */
    public function findOpenMotion(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT id, title
             FROM motions
             WHERE meeting_id = :mid
               AND closed_at IS NULL AND opened_at IS NOT NULL
             ORDER BY opened_at DESC
             LIMIT 1",
            [':mid' => $meetingId]
        );
    }

    /**
     * Compte les presences eligibles (present/remote).
     */
    public function countExpectedVoters(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM attendances
             WHERE meeting_id = :mid
               AND mode IN ('present','remote')",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Compte les bulletins pour une motion.
     */
    public function countBallotsForMotion(string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots WHERE motion_id = :mid",
            [':mid' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte les tokens actifs non utilises pour une motion.
     */
    public function countActiveUnusedTokens(string $meetingId, string $motionId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM vote_tokens
             WHERE meeting_id = :mid AND motion_id = :mo
               AND used_at IS NULL AND expires_at > NOW()",
            [':mid' => $meetingId, ':mo' => $motionId]
        ) ?? 0);
    }

    // =========================================================================
    // OPERATOR LIVE OOB
    // =========================================================================

    /**
     * Infos seance pour operator live avec tenant.
     */
    public function findMeetingForLive(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, validated_at
             FROM meetings
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve la motion ouverte pour live (avec tenant).
     */
    public function findOpenMotionForLive(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, title
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NOT NULL AND closed_at IS NULL
             ORDER BY opened_at DESC
             LIMIT 1",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Compte les presences (present/remote/proxy) pour une seance.
     */
    public function countPresentForLive(string $meetingId, string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM attendances
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND mode IN ('present','remote','proxy')",
            [':tid' => $tenantId, ':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Compte les tokens actifs non utilises (avec tenant).
     */
    public function countActiveTokensForLive(string $meetingId, string $motionId, string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM vote_tokens
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :mo
               AND used_at IS NULL AND expires_at > NOW()",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId]
        ) ?? 0);
    }

    /**
     * Compte les ballots pour une motion (avec tenant).
     */
    public function countBallotsForLive(string $meetingId, string $motionId, string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM ballots
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :mo",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId]
        ) ?? 0);
    }

    // =========================================================================
    // PRESIDENT LIVE OOB
    // =========================================================================

    /**
     * Trouve la motion ouverte avec contexte.
     */
    public function findOpenMotionWithContext(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, opened_at, closed_at
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NOT NULL AND closed_at IS NULL
             ORDER BY opened_at DESC
             LIMIT 1",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Compte les membres eligibles au vote.
     */
    public function countEligibleMembers(string $meetingId, string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM members
             WHERE tenant_id = :tid
               AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Compte les presents (present/remote).
     */
    public function countPresentForQuorum(string $meetingId, string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
             FROM attendances
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND mode IN ('present','remote')",
            [':tid' => $tenantId, ':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Infos seance avec quorum policy.
     */
    public function findMeetingWithQuorum(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT quorum_policy_id, validated_at
             FROM meetings
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve le seuil de quorum.
     */
    public function findQuorumThreshold(string $policyId, string $tenantId): ?float
    {
        $val = $this->scalar(
            "SELECT threshold
             FROM quorum_policies
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $policyId]
        );
        return $val !== null ? (float)$val : null;
    }

    /**
     * Compte les ballots groupes par valeur pour une motion.
     */
    public function countBallotsByValue(string $meetingId, string $motionId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT value, COUNT(*) AS c
             FROM ballots
             WHERE tenant_id = :tid AND meeting_id = :mid AND motion_id = :mo
             GROUP BY value",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mo' => $motionId]
        );
    }

    // =========================================================================
    // DRAWER ANOMALIES
    // =========================================================================

    /**
     * Infos seance pour anomalies.
     */
    public function findMeetingForAnomalies(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, validated_at
             FROM meetings
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Liste les motions ouvertes (non fermees).
     */
    public function listOpenMotions(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, title, opened_at
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NOT NULL AND closed_at IS NULL
             ORDER BY opened_at DESC",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste les votants eligibles (present/remote/proxy).
     */
    public function listEligibleVoters(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT m.id AS member_id, m.full_name
             FROM members m
             JOIN attendances a ON a.member_id = m.id AND a.meeting_id = :mid AND a.tenant_id = m.tenant_id
             WHERE m.tenant_id = :tid AND m.is_active = true
               AND a.mode IN ('present','remote','proxy')
             ORDER BY m.full_name ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Liste tous les membres actifs (fallback si pas de presences).
     */
    public function listAllActiveMembers(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id AS member_id, full_name
             FROM members
             WHERE tenant_id = :tid AND is_active = true
             ORDER BY full_name ASC",
            [':tid' => $tenantId]
        );
    }

    /**
     * Liste les motions avec position et etat.
     */
    public function listMotionsForAnomalies(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, title, opened_at, closed_at, position
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
             ORDER BY position ASC NULLS LAST, created_at ASC",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Liste les ballots d'une motion avec infos votant.
     */
    public function listBallotsForMotion(string $motionId): array
    {
        return $this->selectAll(
            "SELECT b.member_id, mb.full_name AS voter_name, b.value::text AS value, b.source, b.cast_at
             FROM ballots b
             LEFT JOIN members mb ON mb.id = b.member_id
             WHERE b.motion_id = :mid
             ORDER BY b.cast_at ASC",
            [':mid' => $motionId]
        );
    }

    /**
     * Stats tokens pour une motion.
     */
    public function getTokenStats(string $motionId): array
    {
        $row = $this->selectOne(
            "SELECT
                COALESCE(COUNT(*), 0) AS total,
                COALESCE(SUM(CASE WHEN used_at IS NULL AND (expires_at IS NULL OR expires_at > NOW()) THEN 1 ELSE 0 END), 0) AS active_unused,
                COALESCE(SUM(CASE WHEN used_at IS NULL AND expires_at IS NOT NULL AND expires_at <= NOW() THEN 1 ELSE 0 END), 0) AS expired_unused
             FROM vote_tokens
             WHERE motion_id = :mid",
            [':mid' => $motionId]
        );
        return $row ?: ['total' => 0, 'active_unused' => 0, 'expired_unused' => 0];
    }

    /**
     * Liste les tokens expires non utilises pour une motion.
     */
    public function listExpiredUnusedTokens(string $motionId, int $limit = 20): array
    {
        return $this->selectAll(
            "SELECT member_id, LEFT(token_hash, 12) AS token_hash_prefix, expires_at
             FROM vote_tokens
             WHERE motion_id = :mid
               AND used_at IS NULL
               AND expires_at IS NOT NULL
               AND expires_at <= NOW()
             ORDER BY expires_at DESC
             LIMIT " . max(1, $limit),
            [':mid' => $motionId]
        );
    }

    /**
     * Synthese procurations par mandataire.
     */
    public function listProxySummary(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT pr.receiver_member_id AS grantee_member_id, r.full_name AS grantee_name, COUNT(*) AS proxies_received
             FROM proxies pr
             LEFT JOIN members r ON r.id = pr.receiver_member_id
             WHERE pr.meeting_id = :mid
             GROUP BY pr.receiver_member_id, r.full_name
             ORDER BY proxies_received DESC, r.full_name ASC",
            [':mid' => $meetingId]
        );
    }
}
