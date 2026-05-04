<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Data access for meeting reports (PV HTML, SHA256 hashes).
 *
 * Queries the meeting_reports table.
 * Extracted from MeetingRepository for single-responsibility.
 */
class MeetingReportRepository extends AbstractRepository {
    /**
     * Upsert the SHA256 hash of a generated report.
     */
    public function upsertHash(string $meetingId, string $sha256, string $tenantId): void {
        $this->execute(
            'INSERT INTO meeting_reports(meeting_id, tenant_id, sha256, html, generated_at)
             VALUES (:m, :tid, :h, \'\', NOW())
             ON CONFLICT (meeting_id) DO UPDATE SET sha256 = EXCLUDED.sha256, generated_at = NOW()',
            [':m' => $meetingId, ':tid' => $tenantId, ':h' => $sha256],
        );
    }

    /**
     * Store PV HTML content.
     */
    public function storeHtml(string $meetingId, string $html, string $tenantId): void {
        $this->execute(
            'INSERT INTO meeting_reports(meeting_id, tenant_id, html, created_at, updated_at)
             VALUES (:mid, :tid, :html, NOW(), NOW())
             ON CONFLICT (meeting_id) DO UPDATE SET html = EXCLUDED.html, updated_at = NOW()',
            [':mid' => $meetingId, ':tid' => $tenantId, ':html' => $html],
        );
    }

    /**
     * Find the PV HTML snapshot.
     */
    public function findSnapshot(string $meetingId, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT html FROM meeting_reports WHERE meeting_id = :mid AND tenant_id = :tid',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }

    /**
     * Full upsert of report (HTML + SHA256 + generated_at).
     */
    public function upsertFull(string $meetingId, string $html, string $sha256, string $tenantId): void {
        $this->execute(
            'INSERT INTO meeting_reports (meeting_id, tenant_id, html, sha256, generated_at)
             VALUES (:mid, :tid, :html, :hash, NOW())
             ON CONFLICT (meeting_id)
             DO UPDATE SET html = EXCLUDED.html, sha256 = EXCLUDED.sha256, generated_at = NOW(), updated_at = NOW()',
            [':mid' => $meetingId, ':tid' => $tenantId, ':html' => $html, ':hash' => $sha256],
        );
    }
}
