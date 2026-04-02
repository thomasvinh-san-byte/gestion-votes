<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\DatabaseProvider;
use PDO;

/**
 * RgpdExportService — RGPD Article 20 data portability.
 *
 * Builds a JSON-serialisable export array for a given user, containing:
 *  - profile:     their member record (id, full_name, email, role, voting_power, created_at)
 *  - votes:       all ballots cast, joined with motion and meeting titles
 *  - attendances: all attendance records, joined with meeting title
 *  - exported_at: ISO 8601 timestamp of when the export was generated
 *
 * No HTTP concerns — pure data assembly.
 * Constructor accepts nullable PDO for testability; falls back to DatabaseProvider::pdo().
 */
final class RgpdExportService
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DatabaseProvider::pdo();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Build and return the export array for a given user.
     *
     * Scoping is double-checked on tenant_id to prevent cross-tenant leaks:
     * the members lookup requires both user_id = :uid AND tenant_id = :tid.
     *
     * If the user has no linked member record, returns a minimal export with
     * null profile and empty arrays for votes and attendances.
     *
     * @param string $userId   UUID of the authenticated user (from session)
     * @param string $tenantId UUID of the tenant (from session)
     * @return array{profile: array<string,mixed>|null, votes: list<array<string,mixed>>, attendances: list<array<string,mixed>>, exported_at: string}
     */
    public function exportForUser(string $userId, string $tenantId): array
    {
        // 1. Look up the member record for this user in this tenant
        $stmt = $this->pdo->prepare(
            'SELECT id, full_name, email, role, voting_power, created_at
             FROM members
             WHERE user_id = :uid
               AND tenant_id = :tid
               AND deleted_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':tid' => $tenantId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        // 2. No member → return minimal empty export
        if ($member === false || $member === null) {
            return [
                'profile'     => null,
                'votes'       => [],
                'attendances' => [],
                'exported_at' => date('c'),
            ];
        }

        $memberId = (string) $member['id'];

        // 3. Load all votes for this member
        $votesStmt = $this->pdo->prepare(
            'SELECT mo.title AS motion_title,
                    me.title AS meeting_title,
                    me.scheduled_at AS meeting_date,
                    b.value::text AS value,
                    b.weight,
                    b.cast_at
             FROM ballots b
             JOIN motions mo ON mo.id = b.motion_id
             JOIN meetings me ON me.id = b.meeting_id
             WHERE b.member_id = :mid
               AND b.tenant_id = :tid
             ORDER BY b.cast_at DESC'
        );
        $votesStmt->execute([':mid' => $memberId, ':tid' => $tenantId]);
        $votes = $votesStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Load all attendances for this member
        $attendancesStmt = $this->pdo->prepare(
            'SELECT me.title AS meeting_title,
                    me.scheduled_at AS meeting_date,
                    a.mode,
                    a.checked_in_at
             FROM attendances a
             JOIN meetings me ON me.id = a.meeting_id
             WHERE a.member_id = :mid
               AND a.tenant_id = :tid
             ORDER BY a.checked_in_at DESC'
        );
        $attendancesStmt->execute([':mid' => $memberId, ':tid' => $tenantId]);
        $attendances = $attendancesStmt->fetchAll(PDO::FETCH_ASSOC);

        // 5. Return assembled export — profile excludes password_hash by design (not selected)
        return [
            'profile'     => $member,
            'votes'       => $votes !== false ? $votes : [],
            'attendances' => $attendances !== false ? $attendances : [],
            'exported_at' => date('c'),
        ];
    }
}
