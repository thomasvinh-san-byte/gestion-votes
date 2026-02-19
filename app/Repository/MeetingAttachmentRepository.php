<?php
declare(strict_types=1);

namespace AgVote\Repository;

class MeetingAttachmentRepository extends AbstractRepository
{
    public function listForMeeting(string $meetingId, string $tenantId): array
    {
        return $this->selectAll(
            "SELECT id, meeting_id, original_name, stored_name, mime_type, file_size, uploaded_by, created_at
             FROM meeting_attachments
             WHERE meeting_id = :mid AND tenant_id = :tid
             ORDER BY created_at ASC",
            [':mid' => $meetingId, ':tid' => $tenantId]
        );
    }

    public function create(
        string $id,
        string $tenantId,
        string $meetingId,
        string $originalName,
        string $storedName,
        string $mimeType,
        int $fileSize,
        ?string $uploadedBy
    ): void {
        $this->execute(
            "INSERT INTO meeting_attachments (id, tenant_id, meeting_id, original_name, stored_name, mime_type, file_size, uploaded_by)
             VALUES (:id, :tid, :mid, :oname, :sname, :mime, :fsize, :uid)",
            [
                ':id' => $id,
                ':tid' => $tenantId,
                ':mid' => $meetingId,
                ':oname' => $originalName,
                ':sname' => $storedName,
                ':mime' => $mimeType,
                ':fsize' => $fileSize,
                ':uid' => $uploadedBy,
            ]
        );
    }

    public function findById(string $id, string $tenantId): ?array
    {
        return $this->selectOne(
            "SELECT * FROM meeting_attachments WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );
    }

    public function delete(string $id, string $tenantId): int
    {
        return $this->execute(
            "DELETE FROM meeting_attachments WHERE id = :id AND tenant_id = :tid",
            [':id' => $id, ':tid' => $tenantId]
        );
    }
}
