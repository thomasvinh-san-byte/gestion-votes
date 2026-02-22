<?php

declare(strict_types=1);

namespace AgVote\Repository\Traits;

/**
 * Motion finder methods — single-record lookups by various criteria.
 *
 * Used by: MotionRepository (via trait inclusion).
 * All methods delegate to AbstractRepository::selectOne().
 */
trait MotionFinderTrait {
    public function findByIdForTenant(string $motionId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT * FROM motions WHERE tenant_id = :tid AND id = :id',
            [':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function findBySlugForTenant(string $slug, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT * FROM motions WHERE slug = :slug AND tenant_id = :tid',
            [':slug' => $slug, ':tid' => $tenantId],
        );
    }

    public function findByIdOrSlugForTenant(string $identifier, string $tenantId): ?array {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return $this->findByIdForTenant($identifier, $tenantId);
        }
        return $this->findBySlugForTenant($identifier, $tenantId);
    }

    public function findWithMeetingInfo(string $motionId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT
                m.id AS motion_id, m.meeting_id, m.title, m.position,
                m.opened_at, m.closed_at,
                m.vote_policy_id, m.quorum_policy_id,
                mt.status AS meeting_status,
                mt.validated_at AS meeting_validated_at,
                mt.quorum_policy_id AS meeting_quorum_policy_id,
                mt.vote_policy_id AS meeting_vote_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.tenant_id = :tid AND m.id = :id',
            [':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function findByIdForTenantForUpdate(string $motionId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT m.id, m.meeting_id, m.agenda_id, m.title, m.description,
                    m.opened_at, m.closed_at, m.secret, m.vote_policy_id, m.quorum_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.tenant_id = :tid AND m.id = :id
             FOR UPDATE',
            [':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function findWithMeetingStatus(string $motionId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT mo.id, mo.meeting_id, mo.opened_at, mo.closed_at
             FROM motions mo
             JOIN meetings mt ON mt.id = mo.meeting_id
             WHERE mt.tenant_id = :tid AND mo.id = :id',
            [':tid' => $tenantId, ':id' => $motionId],
        );
    }

    public function findAgendaWithMeeting(string $agendaId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT a.id, a.meeting_id, m.tenant_id
             FROM agendas a
             JOIN meetings m ON m.id = a.meeting_id
             WHERE a.id = :aid AND m.tenant_id = :tid',
            [':aid' => $agendaId, ':tid' => $tenantId],
        );
    }

    public function findCurrentOpen(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id, agenda_id, title, description, body, secret, position,
                    vote_policy_id, quorum_policy_id, opened_at, closed_at
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NOT NULL AND closed_at IS NULL
             ORDER BY opened_at DESC LIMIT 1',
            [':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    public function findOpenForUpdate(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NOT NULL AND closed_at IS NULL
             LIMIT 1 FOR UPDATE',
            [':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    public function findWithQuorumContext(string $motionId, string $tenantId = ''): ?array {
        $sql = 'SELECT mo.id AS motion_id, mo.title AS motion_title, mo.meeting_id,
                    mo.quorum_policy_id AS motion_quorum_policy_id,
                    mo.opened_at AS motion_opened_at,
                    mt.tenant_id, mt.quorum_policy_id AS meeting_quorum_policy_id,
                    COALESCE(mt.convocation_no, 1) AS convocation_no
             FROM motions mo
             JOIN meetings mt ON mt.id = mo.meeting_id
             WHERE mo.id = :id';
        $params = [':id' => $motionId];
        if ($tenantId !== '') {
            $sql .= ' AND mt.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        return $this->selectOne($sql, $params);
    }

    public function findWithVoteContext(string $motionId, string $tenantId = ''): ?array {
        $sql = 'SELECT m.id AS motion_id, m.title AS motion_title,
                    m.vote_policy_id, m.quorum_policy_id,
                    m.secret,
                    mt.id AS meeting_id, mt.tenant_id,
                    mt.quorum_policy_id AS meeting_quorum_policy_id,
                    mt.vote_policy_id AS meeting_vote_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.id = :id';
        $params = [':id' => $motionId];
        if ($tenantId !== '') {
            $sql .= ' AND mt.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        return $this->selectOne($sql, $params);
    }

    public function findWithOfficialContext(string $motionId, string $tenantId = ''): ?array {
        $sql = 'SELECT m.id, m.title, m.meeting_id,
                    m.vote_policy_id, m.quorum_policy_id,
                    m.secret, m.closed_at,
                    m.manual_total, m.manual_for, m.manual_against, m.manual_abstain,
                    mt.tenant_id,
                    mt.quorum_policy_id AS meeting_quorum_policy_id,
                    mt.vote_policy_id AS meeting_vote_policy_id
             FROM motions m
             JOIN meetings mt ON mt.id = m.meeting_id
             WHERE m.id = :id';
        $params = [':id' => $motionId];
        if ($tenantId !== '') {
            $sql .= ' AND mt.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        return $this->selectOne($sql, $params);
    }

    public function findWithBallotContext(string $motionId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT
              m.id          AS motion_id,
              m.opened_at   AS motion_opened_at,
              m.closed_at   AS motion_closed_at,
              mt.id         AS meeting_id,
              mt.status     AS meeting_status,
              mt.validated_at AS meeting_validated_at,
              mt.tenant_id  AS tenant_id
            FROM motions m
            JOIN meetings mt ON mt.id = m.meeting_id
            WHERE m.id = :mid AND m.tenant_id = :tid',
            [':mid' => $motionId, ':tid' => $tenantId],
        );
    }

    public function findByIdAndMeeting(string $motionId, string $meetingId): ?array {
        return $this->selectOne(
            'SELECT id, title FROM motions WHERE id = :id AND meeting_id = :mid',
            [':id' => $motionId, ':mid' => $meetingId],
        );
    }

    public function findForMeetingWithState(string $tenantId, string $motionId, string $meetingId): ?array {
        return $this->selectOne(
            'SELECT id, opened_at, closed_at
             FROM motions
             WHERE tenant_id = :tid AND id = :id AND meeting_id = :mid',
            [':tid' => $tenantId, ':id' => $motionId, ':mid' => $meetingId],
        );
    }

    public function findByMeetingWithDates(string $tenantId, string $meetingId, string $motionId): ?array {
        return $this->selectOne(
            'SELECT id, title, opened_at, closed_at
             FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid AND id = :id',
            [':tid' => $tenantId, ':mid' => $meetingId, ':id' => $motionId],
        );
    }

    public function findWithMeetingTenant(string $motionId, string $tenantId = ''): ?array {
        $sql = 'SELECT mo.id AS motion_id, mo.title AS motion_title, mo.meeting_id, m.tenant_id
             FROM motions mo
             JOIN meetings m ON m.id = mo.meeting_id
             WHERE mo.id = :id';
        $params = [':id' => $motionId];
        if ($tenantId !== '') {
            $sql .= ' AND m.tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        return $this->selectOne($sql, $params);
    }

    public function findByIdAndMeetingForUpdate(string $tenantId, string $meetingId, string $motionId): ?array {
        return $this->selectOne(
            'SELECT id FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid AND id = :id
             FOR UPDATE',
            [':tid' => $tenantId, ':mid' => $meetingId, ':id' => $motionId],
        );
    }

    public function findByIdAndMeetingWithDates(string $motionId, string $meetingId): ?array {
        return $this->selectOne(
            'SELECT id, meeting_id, opened_at, closed_at FROM motions WHERE id = :id AND meeting_id = :mid',
            [':id' => $motionId, ':mid' => $meetingId],
        );
    }

    public function findOpenForProjector(string $meetingId): ?array {
        return $this->selectOne(
            'SELECT id, title, description, body, secret, position, opened_at
             FROM motions
             WHERE meeting_id = :meeting_id
               AND opened_at IS NOT NULL
               AND closed_at IS NULL
             ORDER BY opened_at DESC
             LIMIT 1',
            [':meeting_id' => $meetingId],
        );
    }

    public function findLastClosedForProjector(string $meetingId): ?array {
        return $this->selectOne(
            'SELECT id, title, description, body, secret, position, closed_at
             FROM motions
             WHERE meeting_id = :meeting_id
               AND closed_at IS NOT NULL
             ORDER BY closed_at DESC
             LIMIT 1',
            [':meeting_id' => $meetingId],
        );
    }

    public function findNextNotOpened(string $meetingId): ?array {
        return $this->selectOne(
            'SELECT id, title FROM motions
             WHERE meeting_id = :mid AND opened_at IS NULL
             ORDER BY position ASC NULLS LAST, created_at ASC LIMIT 1',
            [':mid' => $meetingId],
        );
    }

    public function findNextNotOpenedForUpdate(string $tenantId, string $meetingId): ?array {
        return $this->selectOne(
            'SELECT id FROM motions
             WHERE tenant_id = :tid AND meeting_id = :mid
               AND opened_at IS NULL AND closed_at IS NULL
             ORDER BY COALESCE(position, 0) ASC
             LIMIT 1 FOR UPDATE',
            [':tid' => $tenantId, ':mid' => $meetingId],
        );
    }

    public function isOwnedByUser(string $motionId, string $userId): bool {
        return (bool) $this->scalar(
            'SELECT 1 FROM motions mo
             JOIN meetings m ON m.id = mo.meeting_id
             JOIN users u ON u.tenant_id = m.tenant_id
             WHERE mo.id = :id AND u.id = :uid',
            [':id' => $motionId, ':uid' => $userId],
        );
    }
}
