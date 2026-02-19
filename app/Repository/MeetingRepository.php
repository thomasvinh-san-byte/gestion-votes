<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for meetings.
 *
 * All SQL queries for the meetings table are centralized here.
 * No business logic (transitions, validations) - only CRUD.
 */
class MeetingRepository extends AbstractRepository
{
    // =========================================================================
    // READ
    // =========================================================================

    public function findByIdForTenant(string $id, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM meetings WHERE id = :id AND tenant_id = :tenant_id",
            [':id' => $id, ':tenant_id' => $tenantId]
        );
    }

    public function existsForTenant(string $id, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM meetings WHERE id = :id AND tenant_id = :tenant_id",
            [':id' => $id, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Finds a meeting by its slug (URL obfuscation).
     */
    public function findBySlugForTenant(string $slug, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM meetings WHERE slug = :slug AND tenant_id = :tenant_id",
            [':slug' => $slug, ':tenant_id' => $tenantId]
        );
    }

    /**
     * Finds a meeting by ID or slug (dual support).
     * Automatically detects if the identifier is a UUID or slug.
     */
    public function findByIdOrSlugForTenant(string $identifier, string $tenantId): ?array
    {
        // Check if it's a UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return $this->findByIdForTenant($identifier, $tenantId);
        }
        // Otherwise, search by slug
        return $this->findBySlugForTenant($identifier, $tenantId);
    }

    /**
     * Lists all meetings for a tenant (for meetings.php GET).
     */
    public function listByTenant(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
                id, tenant_id, title, description,
                status::text AS status,
                scheduled_at, started_at, ended_at,
                location, quorum_policy_id, vote_policy_id,
                president_name, convocation_no,
                created_at, updated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Lists active meetings (excludes validated and archived).
     * Used for selection dropdowns.
     */
    public function listActiveByTenant(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT
                id, tenant_id, title, description,
                status::text AS status,
                scheduled_at, started_at, ended_at,
                location, quorum_policy_id, vote_policy_id,
                president_name, convocation_no,
                created_at, updated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
               AND status NOT IN ('validated', 'archived')
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Compact list (for meetings_index.php).
     */
    public function listByTenantCompact(string $tenantId, int $limit = 50): array
    {
        return $this->selectAll(
            "SELECT id AS meeting_id, id, title, status::text AS status,
                    created_at, started_at, ended_at, archived_at, validated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC
             LIMIT " . max(1, min($limit, 200)),
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Compact list of active meetings (excludes validated and archived).
     */
    public function listActiveByTenantCompact(string $tenantId, int $limit = 50): array
    {
        return $this->selectAll(
            "SELECT id AS meeting_id, id, title, status::text AS status,
                    created_at, started_at, ended_at, archived_at, validated_at
             FROM meetings
             WHERE tenant_id = :tenant_id
               AND status NOT IN ('validated', 'archived')
             ORDER BY COALESCE(started_at, scheduled_at, created_at) DESC
             LIMIT " . max(1, min($limit, 200)),
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Lists archived/closed meetings (for meetings_archive.php).
     */
    public function listArchived(string $tenantId, string $from = '', string $to = ''): array
    {
        $conditions = ["tenant_id = :tenant_id", "status IN ('closed','archived')"];
        $params = [':tenant_id' => $tenantId];

        if ($from !== '') {
            $conditions[] = 'created_at >= :from';
            $params[':from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $conditions[] = 'created_at <= :to';
            $params[':to'] = $to . ' 23:59:59';
        }

        $where = implode(' AND ', $conditions);
        return $this->selectAll(
            "SELECT id, title, status, created_at, validated_by, validated_at
             FROM meetings
             WHERE {$where}
             ORDER BY created_at DESC
             LIMIT 500",
            $params
        );
    }

    /**
     * Liste les seances pour le dashboard operateur.
     */
    public function listForDashboard(string $tenantId, int $limit = 50): array
    {
        return $this->selectAll(
            "SELECT id, title, status, scheduled_at, started_at, ended_at, archived_at, validated_at
             FROM meetings
             WHERE tenant_id = :tid
             ORDER BY
               CASE status WHEN 'live' THEN 0 WHEN 'draft' THEN 1 WHEN 'archived' THEN 3 ELSE 2 END,
               COALESCE(started_at, scheduled_at, created_at) DESC
             LIMIT " . max(1, min($limit, 500)),
            [':tid' => $tenantId]
        );
    }

    /**
     * Titre d'une seance par son ID.
     */
    public function findTitle(string $meetingId): ?string
    {
        $val = $this->scalar(
            "SELECT title FROM meetings WHERE id = :id",
            [':id' => $meetingId]
        );
        return $val !== null ? (string)$val : null;
    }

    /**
     * Compte les procurations actives (non revoquees) pour une seance.
     */
    public function countActiveProxies(string $tenantId, string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM proxies WHERE tenant_id = :tid AND meeting_id = :mid AND revoked_at IS NULL",
            [':tid' => $tenantId, ':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Trouve la seance courante (non archivee) pour un tenant.
     * Priorite : live > closed > draft.
     */
    public function findCurrentForTenant(string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT
                m.id AS meeting_id, m.title AS meeting_title,
                m.status AS meeting_status,
                m.started_at, m.ended_at, m.archived_at,
                m.president_member_id, m.president_name, m.president_source,
                m.ready_to_sign, m.validated_at,
                u.display_name AS validated_by
             FROM meetings m
             LEFT JOIN users u ON u.id = m.validated_by_user_id
             WHERE m.tenant_id = :tenant_id AND m.status <> 'archived'
             ORDER BY
                CASE m.status
                    WHEN 'live' THEN 1 WHEN 'paused' THEN 2
                    WHEN 'closed' THEN 3 WHEN 'draft' THEN 4
                    ELSE 5
                END,
                m.created_at DESC
             LIMIT 1",
            [':tenant_id' => $tenantId]
        );
    }

    /**
     * Trouve une seance avec champs specifiques (pour meeting_status_for_meeting).
     */
    public function findStatusFields(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id AS meeting_id, title AS meeting_title, status AS meeting_status,
                    started_at, ended_at, archived_at, validated_at,
                    president_name, ready_to_sign
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve les reglages de vote d'une seance.
     */
    public function findVoteSettings(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id AS meeting_id, title, vote_policy_id
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve les reglages de quorum d'une seance.
     */
    public function findQuorumSettings(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id AS meeting_id, title, quorum_policy_id,
                    COALESCE(convocation_no, 1) AS convocation_no
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Trouve les regles tardives d'une seance.
     */
    public function findLateRules(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, late_rule_quorum, late_rule_vote
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Resume de seance pour meeting_summary.
     */
    public function findSummaryFields(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, title, status, president_name, validated_at
             FROM meetings WHERE tenant_id = :tenant_id AND id = :id",
            [':tenant_id' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Liste les seances archivees avec info rapport (pour archives_list).
     */
    public function listArchivedWithReports(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT mt.id, mt.title, mt.archived_at, mt.validated_at, mt.president_name,
                    COALESCE(mr.sha256, NULL) AS report_sha256,
                    COALESCE(mr.generated_at, NULL) AS report_generated_at,
                    (mr.meeting_id IS NOT NULL) AS has_report
             FROM meetings mt
             LEFT JOIN meeting_reports mr ON mr.meeting_id = mt.id
             WHERE mt.tenant_id = :tid AND mt.status = 'archived'
             ORDER BY mt.archived_at DESC NULLS LAST, mt.validated_at DESC NULLS LAST",
            [':tid' => $tenantId]
        );
    }

    /**
     * Compte toutes les seances d'un tenant.
     */
    public function countForTenant(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM meetings WHERE tenant_id = :tid",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Liste les procurations d'une seance pour rapport (avec noms mandant/mandataire).
     */
    public function listProxiesForReport(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT g.full_name AS giver_name, r.full_name AS receiver_name, p.created_at, p.revoked_at
             FROM proxies p
             JOIN members g ON g.id = p.giver_member_id
             JOIN members r ON r.id = p.receiver_member_id
             WHERE p.meeting_id = :mid
             ORDER BY g.full_name ASC",
            [':mid' => $meetingId]
        );
    }

    /**
     * Detecte les cycles de procuration pour une seance.
     */
    public function findProxyCycles(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT p1.giver_member_id, p1.receiver_member_id
             FROM proxies p1
             JOIN proxies p2 ON p1.receiver_member_id = p2.giver_member_id AND p1.giver_member_id = p2.receiver_member_id
             WHERE p1.meeting_id = :mid",
            [':mid' => $meetingId]
        );
    }

    /**
     * Compte les seances live d'un tenant.
     */
    public function countLive(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM meetings WHERE tenant_id = :tid AND status = 'live'",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Liste les seances live d'un tenant (pour le selecteur projecteur).
     */
    public function listLiveForTenant(string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, title, started_at, status
             FROM meetings
             WHERE tenant_id = :tid AND status IN ('live', 'paused')
             ORDER BY started_at DESC NULLS LAST, created_at DESC",
            [':tid' => $tenantId]
        );
    }

    // =========================================================================
    // ECRITURE
    // =========================================================================

    /**
     * Cree une seance.
     */
    public function create(
        string $id,
        string $tenantId,
        string $title,
        ?string $description,
        ?string $scheduledAt,
        ?string $location,
        string $meetingType = 'ag_ordinaire'
    ): void {
        $this->execute(
            "INSERT INTO meetings (id, tenant_id, title, description, meeting_type, status, scheduled_at, location, created_at, updated_at)
             VALUES (:id, :tenant_id, :title, :description, :meeting_type, 'draft', :scheduled_at, :location, NOW(), NOW())",
            [
                ':id' => $id,
                ':tenant_id' => $tenantId,
                ':title' => $title,
                ':description' => $description ?: null,
                ':meeting_type' => $meetingType,
                ':scheduled_at' => $scheduledAt ?: null,
                ':location' => $location ?: null,
            ]
        );
    }

    private const UPDATABLE_FIELDS = [
        'title', 'description', 'meeting_type', 'status', 'scheduled_at', 'started_at', 'ended_at',
        'location', 'quorum_policy_id', 'vote_policy_id', 'convocation_no',
        'president_name', 'president_member_id', 'president_source',
        'current_motion_id', 'late_rule_quorum', 'late_rule_vote',
        'ready_to_sign', 'validated_at', 'validated_by_user_id', 'archived_at',
    ];

    /**
     * Met a jour dynamiquement des champs d'une seance.
     *
     * @param array<string,mixed> $fields Cle=colonne SQL, valeur=nouvelle valeur
     */
    public function updateFields(string $meetingId, string $tenantId, array $fields): int
    {
        if (empty($fields)) return 0;

        $sets = [];
        $params = [':tid' => $tenantId, ':id' => $meetingId];
        foreach ($fields as $col => $val) {
            if (!in_array($col, self::UPDATABLE_FIELDS, true)) {
                throw new \InvalidArgumentException("Colonne non autorisée : {$col}");
            }
            $param = ':f_' . $col;
            $sets[] = "\"{$col}\" = {$param}";
            $params[$param] = $val;
        }
        $sets[] = "updated_at = now()";

        $sql = "UPDATE meetings SET " . implode(', ', $sets) . " WHERE tenant_id = :tid AND id = :id";
        return $this->execute($sql, $params);
    }

    /**
     * Met a jour la politique de vote d'une seance.
     */
    public function updateVotePolicy(string $meetingId, string $tenantId, ?string $policyId): void
    {
        $this->execute(
            "UPDATE meetings SET vote_policy_id = :pid, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :mid",
            [':pid' => $policyId, ':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Met a jour la politique de quorum + numero de convocation.
     */
    public function updateQuorumPolicy(string $meetingId, string $tenantId, ?string $policyId, int $convocationNo): void
    {
        $this->execute(
            "UPDATE meetings SET quorum_policy_id = :pid, convocation_no = :c, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :mid",
            [':pid' => $policyId, ':c' => $convocationNo, ':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Met a jour les regles tardives.
     */
    public function updateLateRules(string $meetingId, string $tenantId, bool $lateRuleQuorum, bool $lateRuleVote): void
    {
        $this->execute(
            "UPDATE meetings SET late_rule_quorum = :q, late_rule_vote = :v, updated_at = NOW()
             WHERE tenant_id = :tid AND id = :id",
            [':q' => $lateRuleQuorum, ':v' => $lateRuleVote, ':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Marque une seance comme validee.
     */
    public function markValidated(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "UPDATE meetings SET status = 'validated', validated_at = NOW()
             WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    /**
     * Met a jour le current_motion_id d'une seance.
     */
    public function updateCurrentMotion(string $meetingId, string $tenantId, ?string $motionId): void
    {
        $this->execute(
            "UPDATE meetings SET current_motion_id = :mo, updated_at = now()
             WHERE tenant_id = :tid AND id = :mid",
            [':mo' => $motionId, ':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Upsert d'un check d'urgence (meeting_emergency_checks).
     */
    public function upsertEmergencyCheck(
        string $meetingId,
        string $procedureCode,
        int $itemIndex,
        bool $checked,
        ?string $checkedBy
    ): void {
        $this->execute(
            "INSERT INTO meeting_emergency_checks(meeting_id, procedure_code, item_index, checked, checked_at, checked_by)
             VALUES (:m,:p,:i,:c, CASE WHEN :c2 THEN NOW() ELSE NULL END, :by)
             ON CONFLICT (meeting_id, procedure_code, item_index)
             DO UPDATE SET checked = EXCLUDED.checked, checked_at = EXCLUDED.checked_at, checked_by = EXCLUDED.checked_by",
            [
                ':m' => $meetingId,
                ':p' => $procedureCode,
                ':i' => $itemIndex,
                ':c' => $checked,
                ':c2' => $checked,
                ':by' => $checkedBy,
            ]
        );
    }

    /**
     * Lock meeting row pour eviter les conflits concurrents (FOR UPDATE).
     */
    public function lockForUpdate(string $meetingId, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT id, validated_at, status FROM meetings
             WHERE tenant_id = :tid AND id = :id FOR UPDATE",
            [':tid' => $tenantId, ':id' => $meetingId]
        );
    }

    // =========================================================================
    // STATISTIQUES / AGGREGATIONS
    // =========================================================================

    /**
     * Compteurs de motions pour une seance (pour meeting_status).
     */
    public function countMotionStats(string $meetingId): array
    {
        $row = $this->selectOne(
            "SELECT
              COUNT(*) AS total_motions,
              SUM(CASE WHEN mo.closed_at IS NULL THEN 1 ELSE 0 END) AS open_motions,
              SUM(CASE WHEN mo.closed_at IS NOT NULL THEN 1 ELSE 0 END) AS closed_motions,
              SUM(CASE WHEN mo.closed_at IS NOT NULL
                        AND (mo.manual_total IS NULL OR mo.manual_total <= 0)
                   THEN 1 ELSE 0 END) AS closed_without_tally
             FROM motions mo WHERE mo.meeting_id = :meeting_id",
            [':meeting_id' => $meetingId]
        );
        return $row ?: ['total_motions' => 0, 'open_motions' => 0, 'closed_motions' => 0, 'closed_without_tally' => 0];
    }

    /**
     * Nombre total de membres actifs pour un tenant.
     */
    public function countActiveMembers(string $tenantId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM members WHERE tenant_id = :tid AND is_active = true",
            [':tid' => $tenantId]
        ) ?? 0);
    }

    /**
     * Compteurs de presences pour une seance (meeting_summary).
     */
    public function countPresent(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances
             WHERE meeting_id = :mid AND mode IN ('present', 'remote')",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countProxy(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM attendances WHERE meeting_id = :mid AND mode = 'proxy'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    /**
     * Compteurs motions pour meeting_summary.
     */
    public function countMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countClosedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND closed_at IS NOT NULL",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countOpenMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions
             WHERE meeting_id = :mid AND opened_at IS NOT NULL AND closed_at IS NULL",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countAdoptedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND decision = 'adopted'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countRejectedMotions(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM motions WHERE meeting_id = :mid AND decision = 'rejected'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countBallots(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function sumBallotWeight(string $meetingId): float
    {
        return (float)($this->scalar(
            "SELECT COALESCE(SUM(b.weight), 0) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countProxies(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM proxies WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countIncidents(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM audit_events
             WHERE resource_type = 'meeting' AND resource_id = :mid
               AND action LIKE '%incident%'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    public function countManualVotes(string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*) FROM ballots b
             JOIN motions m ON m.id = b.motion_id
             WHERE m.meeting_id = :mid AND b.source = 'manual'",
            [':mid' => $meetingId]
        ) ?? 0);
    }

    // =========================================================================
    // REPORTS / AUDIT
    // =========================================================================

    /**
     * Liste les transitions d'etat des seances (table meeting_state_transitions).
     */
    public function listStateTransitions(): array
    {
        return $this->selectAll(
            "SELECT from_status, to_status, required_role, description FROM meeting_state_transitions ORDER BY from_status, to_status"
        );
    }

    /**
     * Trouve une seance avec le nom du validateur.
     */
    public function findWithValidator(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT m.*, u.display_name AS validated_by
             FROM meetings m
             LEFT JOIN users u ON u.id = m.validated_by_user_id
             WHERE m.id = :id",
            [':id' => $meetingId]
        );
    }

    /**
     * Upsert du sha256 du rapport dans meeting_reports.
     */
    public function upsertReportHash(string $meetingId, string $sha256): void
    {
        $this->execute(
            "INSERT INTO meeting_reports(meeting_id, sha256, generated_at)
             VALUES (:m, :h, NOW())
             ON CONFLICT (meeting_id) DO UPDATE SET sha256 = EXCLUDED.sha256, generated_at = NOW()",
            [':m' => $meetingId, ':h' => $sha256]
        );
    }

    /**
     * Stocke le PV HTML dans meeting_reports.
     */
    public function storePVHtml(string $meetingId, string $html): void
    {
        $this->execute(
            "INSERT INTO meeting_reports(meeting_id, html, created_at, updated_at)
             VALUES (:mid, :html, NOW(), NOW())
             ON CONFLICT (meeting_id) DO UPDATE SET html = EXCLUDED.html, updated_at = NOW()",
            [':mid' => $meetingId, ':html' => $html]
        );
    }

    /**
     * Trouve le PV HTML snapshot.
     */
    public function findPVSnapshot(string $meetingId): ?array
    {
        return $this->selectOne(
            "SELECT html FROM meeting_reports WHERE meeting_id = :mid",
            [':mid' => $meetingId]
        );
    }

    /**
     * Audit events pour export CSV (toutes colonnes, tri chronologique).
     */
    public function listAuditEventsForExport(string $tenantId, string $meetingId): array
    {
        return $this->selectAll(
            "SELECT created_at, actor_role, actor_user_id, action, resource_type, resource_id, payload, prev_hash, this_hash
             FROM audit_events
             WHERE tenant_id = :tid AND meeting_id = :mid
             ORDER BY created_at ASC",
            [':tid' => $tenantId, ':mid' => $meetingId]
        );
    }

    /**
     * Audit events pour une seance.
     */
    public function listAuditEvents(string $meetingId, string $tenantId, int $limit = 200, string $order = 'DESC'): array
    {
        $order = ($order === 'ASC') ? 'ASC' : 'DESC';
        return $this->selectAll(
            "SELECT id, action, resource_type, resource_id, payload, created_at
             FROM audit_events
             WHERE tenant_id = :tid
               AND ((resource_type = 'meeting' AND resource_id = :mid)
                    OR (resource_type = 'motion' AND resource_id IN (
                        SELECT id FROM motions WHERE meeting_id = :mid2)))
             ORDER BY created_at {$order}
             LIMIT " . max(1, $limit),
            [':tid' => $tenantId, ':mid' => $meetingId, ':mid2' => $meetingId]
        );
    }

    /**
     * Audit events filtres pour une seance (pour operator_audit_events).
     */
    public function listAuditEventsFiltered(
        string $tenantId,
        string $meetingId,
        int $limit = 200,
        string $resourceType = '',
        string $action = '',
        string $q = ''
    ): array {
        $where = "WHERE tenant_id = ? AND (
            (resource_type = 'meeting' AND resource_id = ?)
            OR
            (resource_type = 'motion' AND resource_id IN (SELECT id FROM motions WHERE meeting_id = ?))
        )";
        $params = [$tenantId, $meetingId, $meetingId];

        if ($resourceType !== '') {
            $where .= " AND resource_type = ?";
            $params[] = $resourceType;
        }
        if ($action !== '') {
            $where .= " AND action ILIKE ?";
            $params[] = "%" . $action . "%";
        }
        if ($q !== '') {
            $where .= " AND (action ILIKE ? OR resource_id ILIKE ? OR payload::text ILIKE ?)";
            $params[] = "%" . $q . "%";
            $params[] = "%" . $q . "%";
            $params[] = "%" . $q . "%";
        }

        return $this->selectAll(
            "SELECT id, action, resource_type, resource_id, payload, created_at
             FROM audit_events
             {$where}
             ORDER BY created_at DESC
             LIMIT " . max(1, min($limit, 500)),
            $params
        );
    }

    /**
     * Audit events pagines pour le journal d'audit (timeline).
     * Inclut meeting_id direct, resource_type meeting/motion/attendance.
     */
    public function listAuditEventsForLog(
        string $tenantId,
        string $meetingId,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->selectAll(
            "SELECT
                ae.id,
                ae.action,
                ae.resource_type,
                ae.resource_id,
                ae.actor_user_id,
                ae.actor_role,
                ae.payload,
                ae.ip_address,
                ae.created_at
            FROM audit_events ae
            WHERE ae.tenant_id = ?
              AND (
                ae.meeting_id = ?
                OR (ae.resource_type = 'meeting' AND ae.resource_id = ?)
                OR (ae.resource_type = 'motion' AND ae.resource_id IN (
                    SELECT id FROM motions WHERE meeting_id = ?
                ))
                OR (ae.resource_type = 'attendance' AND ae.resource_id IN (
                    SELECT id FROM attendances WHERE meeting_id = ?
                ))
              )
            ORDER BY ae.created_at DESC
            LIMIT ? OFFSET ?",
            [$tenantId, $meetingId, $meetingId, $meetingId, $meetingId, $limit, $offset]
        );
    }

    /**
     * Compte le total d'audit events pour le journal d'audit (pagination).
     */
    public function countAuditEventsForLog(string $tenantId, string $meetingId): int
    {
        return (int)($this->scalar(
            "SELECT COUNT(*)
            FROM audit_events ae
            WHERE ae.tenant_id = ?
              AND (
                ae.meeting_id = ?
                OR (ae.resource_type = 'meeting' AND ae.resource_id = ?)
                OR (ae.resource_type = 'motion' AND ae.resource_id IN (
                    SELECT id FROM motions WHERE meeting_id = ?
                ))
              )",
            [$tenantId, $meetingId, $meetingId, $meetingId]
        ) ?? 0);
    }

    /**
     * Verifie qu'une politique de vote existe pour un tenant.
     */
    public function votePolicyExists(string $policyId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM vote_policies WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $policyId]
        );
    }

    /**
     * Verifie qu'une politique de quorum existe pour un tenant.
     */
    public function quorumPolicyExists(string $policyId, string $tenantId): bool
    {
        return (bool)$this->scalar(
            "SELECT 1 FROM quorum_policies WHERE tenant_id = :tid AND id = :id",
            [':tid' => $tenantId, ':id' => $policyId]
        );
    }

    /**
     * Liste les giver_member_id distincts des procurations actives d'une seance.
     */
    public function listDistinctProxyGivers(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT DISTINCT giver_member_id FROM proxies WHERE meeting_id = :mid AND revoked_at IS NULL",
            [':mid' => $meetingId]
        );
    }

    /**
     * Liste les mandataires depassant le plafond de procurations.
     */
    public function listProxyCeilingViolations(string $tenantId, string $meetingId, int $maxPerReceiver): array
    {
        return $this->selectAll(
            "SELECT receiver_member_id, COUNT(*) AS c
             FROM proxies
             WHERE tenant_id = :tid AND meeting_id = :mid AND revoked_at IS NULL
             GROUP BY receiver_member_id
             HAVING COUNT(*) > :mx
             ORDER BY c DESC",
            [':tid' => $tenantId, ':mid' => $meetingId, ':mx' => $maxPerReceiver]
        );
    }

    /**
     * Compte les audit_events d'un tenant.
     */
    public function countAuditEventsForTenant(string $tenantId): ?int
    {
        try {
            return (int)($this->scalar(
                "SELECT COUNT(*) FROM audit_events WHERE tenant_id = :tid",
                [':tid' => $tenantId]
            ) ?? 0);
        } catch (\Throwable $e) { return null; }
    }

    /**
     * Compte les echecs d'authentification recents (15 min).
     */
    public function countRecentAuthFailures(): ?int
    {
        try {
            return (int)($this->scalar(
                "SELECT COUNT(*) FROM auth_failures WHERE created_at > NOW() - INTERVAL '15 minutes'"
            ) ?? 0);
        } catch (\Throwable $e) { return null; }
    }

    /**
     * Reinitialise les champs live d'une seance (reset demo).
     */
    public function resetForDemo(string $meetingId, string $tenantId): void
    {
        $this->execute(
            "UPDATE meetings
             SET current_motion_id = NULL, status = 'live', updated_at = now()
             WHERE id = :mid AND tenant_id = :tid",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    /**
     * Supprime les audit_events d'une seance (reset demo, best-effort).
     */
    public function deleteAuditEventsByMeeting(string $meetingId, string $tenantId): void
    {
        try {
            $this->execute(
                "DELETE FROM audit_events WHERE meeting_id = :mid AND tenant_id = :tid",
                [':mid' => $meetingId, ':tid' => $tenantId]
            );
        } catch (\Throwable $e) { /* table may not exist */ }
    }

    /**
     * Ping DB (SELECT 1).
     */
    public function ping(): bool
    {
        try {
            $this->scalar("SELECT 1");
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Nombre de connexions actives PostgreSQL.
     */
    public function activeConnections(): ?int
    {
        try {
            return (int)$this->scalar("SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database()");
        } catch (\Throwable $e) { return null; }
    }

    /**
     * Verifie si une alerte systeme recente existe.
     */
    public function findRecentAlert(string $code): bool
    {
        try {
            return (bool)$this->scalar(
                "SELECT 1 FROM system_alerts WHERE code = :c AND created_at > NOW() - INTERVAL '10 minutes' LIMIT 1",
                [':c' => $code]
            );
        } catch (\Throwable $e) { return false; }
    }

    /**
     * Cree une alerte systeme.
     */
    public function createSystemAlert(string $code, string $severity, string $message, ?string $detailsJson): void
    {
        try {
            $this->execute(
                "INSERT INTO system_alerts(code, severity, message, details_json, created_at) VALUES (:c,:s,:m,:d,NOW())",
                [':c' => $code, ':s' => $severity, ':m' => $message, ':d' => $detailsJson]
            );
        } catch (\Throwable $e) { /* best-effort */ }
    }

    /**
     * Liste les alertes systeme recentes.
     */
    public function listRecentAlerts(int $limit = 20): array
    {
        try {
            return $this->selectAll(
                "SELECT id, created_at, code, severity, message, details_json FROM system_alerts ORDER BY created_at DESC LIMIT " . max(1, $limit)
            );
        } catch (\Throwable $e) { return []; }
    }

    /**
     * Upsert complet du rapport (HTML + SHA256 + generated_at).
     */
    public function upsertReportFull(string $meetingId, string $html, string $sha256): void
    {
        $this->execute(
            "INSERT INTO meeting_reports (meeting_id, html, sha256, generated_at)
             VALUES (:mid, :html, :hash, NOW())
             ON CONFLICT (meeting_id)
             DO UPDATE SET html = EXCLUDED.html, sha256 = EXCLUDED.sha256, generated_at = NOW(), updated_at = NOW()",
            [':mid' => $meetingId, ':html' => $html, ':hash' => $sha256]
        );
    }

    /**
     * Liste les procurations orphelines (mandataire absent).
     */
    public function listOrphanProxies(string $meetingId): array
    {
        return $this->selectAll(
            "SELECT p.id, giver.full_name AS giver_name, receiver.full_name AS receiver_name
             FROM proxies p
             JOIN members giver ON giver.id = p.giver_member_id
             JOIN members receiver ON receiver.id = p.receiver_member_id
             LEFT JOIN attendances a ON a.meeting_id = :mid1 AND a.member_id = p.receiver_member_id
             WHERE p.meeting_id = :mid2
               AND (a.id IS NULL OR a.mode NOT IN ('present', 'remote'))",
            [':mid1' => $meetingId, ':mid2' => $meetingId]
        );
    }

    /**
     * Verifie si une seance a ete creee par un utilisateur.
     */
    public function isOwnedByUser(string $meetingId, string $userId): bool
    {
        // Check if user belongs to the same tenant as the meeting
        return (bool)$this->scalar(
            "SELECT 1 FROM meetings m
             JOIN users u ON u.tenant_id = m.tenant_id
             WHERE m.id = :id AND u.id = :uid",
            [':id' => $meetingId, ':uid' => $userId]
        );
    }
}
