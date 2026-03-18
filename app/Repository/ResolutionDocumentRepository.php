<?php

declare(strict_types=1);

namespace AgVote\Repository;

class ResolutionDocumentRepository extends AbstractRepository {
    public function listForMotion(string $motionId, string $tenantId): array {
        return $this->selectAll(
            'SELECT id, motion_id, meeting_id, original_name, stored_name, mime_type, file_size, display_order, uploaded_by, created_at
             FROM resolution_documents
             WHERE motion_id = :mid AND tenant_id = :tid
             ORDER BY display_order ASC, created_at ASC',
            [':mid' => $motionId, ':tid' => $tenantId],
        );
    }

    public function create(
        string $id,
        string $tenantId,
        string $meetingId,
        string $motionId,
        string $originalName,
        string $storedName,
        string $mimeType,
        int $fileSize,
        ?string $uploadedBy,
    ): void {
        $this->execute(
            'INSERT INTO resolution_documents (id, tenant_id, meeting_id, motion_id, original_name, stored_name, mime_type, file_size, uploaded_by)
             VALUES (:id, :tid, :mid, :moid, :oname, :sname, :mime, :fsize, :uid)',
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':mid' => $meetingId,
                ':moid' => $motionId,
                ':oname' => $originalName,
                ':sname' => $storedName,
                ':mime' => $mimeType,
                ':fsize' => $fileSize,
                ':uid' => $uploadedBy,
            ],
        );
    }

    public function findById(string $id, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT * FROM resolution_documents WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId],
        );
    }

    public function delete(string $id, string $tenantId): int {
        return $this->execute(
            'DELETE FROM resolution_documents WHERE id = :id AND tenant_id = :tid',
            [':id' => $id, ':tid' => $tenantId],
        );
    }

    public function countForMotion(string $motionId, string $tenantId): int {
        return (int) $this->scalar(
            'SELECT COUNT(*) FROM resolution_documents WHERE motion_id = :mid AND tenant_id = :tid',
            [':mid' => $motionId, ':tid' => $tenantId],
        );
    }

    public function listForMeeting(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT id, motion_id, original_name, file_size, display_order
             FROM resolution_documents
             WHERE meeting_id = :mid AND tenant_id = :tid
             ORDER BY motion_id, display_order ASC',
            [':mid' => $meetingId, ':tid' => $tenantId],
        );
    }
}
