<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository pour les programmations de rappels.
 */
class ReminderScheduleRepository extends AbstractRepository {
    /**
     * Liste les rappels programmes pour une seance.
     */
    public function listForMeeting(string $meetingId, string $tenantId): array {
        return $this->selectAll(
            'SELECT rs.id, rs.meeting_id, rs.template_id, rs.days_before, rs.send_time,
                    rs.is_active, rs.last_executed_at, rs.created_at,
                    et.name as template_name
             FROM reminder_schedules rs
             LEFT JOIN email_templates et ON et.id = rs.template_id
             WHERE rs.meeting_id = :meeting_id AND rs.tenant_id = :tenant_id
             ORDER BY rs.days_before DESC',
            [':meeting_id' => $meetingId, ':tenant_id' => $tenantId],
        );
    }

    /**
     * Cree ou met a jour un rappel programme.
     */
    public function upsert(
        string $tenantId,
        string $meetingId,
        int $daysBefore,
        ?string $templateId = null,
        string $sendTime = '09:00',
        bool $isActive = true,
    ): ?array {
        return $this->insertReturning(
            'INSERT INTO reminder_schedules (tenant_id, meeting_id, days_before, template_id, send_time, is_active)
             VALUES (:tenant_id, :meeting_id, :days_before, :template_id, :send_time, :is_active)
             ON CONFLICT (tenant_id, meeting_id, days_before)
             DO UPDATE SET template_id = EXCLUDED.template_id,
                           send_time = EXCLUDED.send_time,
                           is_active = EXCLUDED.is_active,
                           updated_at = now()
             RETURNING id, meeting_id, days_before, template_id, send_time, is_active, last_executed_at',
            [
                ':tenant_id' => $tenantId,
                ':meeting_id' => $meetingId,
                ':days_before' => $daysBefore,
                ':template_id' => $templateId,
                ':send_time' => $sendTime,
                ':is_active' => $isActive ? 'true' : 'false',
            ],
        );
    }

    /**
     * Supprime un rappel programme.
     */
    public function delete(string $id, string $tenantId): bool {
        $rows = $this->execute(
            'DELETE FROM reminder_schedules WHERE id = :id AND tenant_id = :tenant_id',
            [':id' => $id, ':tenant_id' => $tenantId],
        );
        return $rows > 0;
    }

    /**
     * Active/desactive un rappel.
     */
    public function setActive(string $id, string $tenantId, bool $isActive): void {
        $this->execute(
            'UPDATE reminder_schedules SET is_active = :is_active, updated_at = now()
             WHERE id = :id AND tenant_id = :tenant_id',
            [':id' => $id, ':tenant_id' => $tenantId, ':is_active' => $isActive ? 'true' : 'false'],
        );
    }

    /**
     * Trouve les rappels a executer maintenant.
     * Compare days_before avec la date de la seance.
     */
    public function findDueReminders(): array {
        return $this->selectAll(
            "SELECT rs.id, rs.tenant_id, rs.meeting_id, rs.template_id, rs.days_before, rs.send_time,
                    m.title as meeting_title, m.scheduled_at as meeting_date
             FROM reminder_schedules rs
             JOIN meetings m ON m.id = rs.meeting_id
             WHERE rs.is_active = true
               AND m.status IN ('scheduled', 'frozen')
               AND m.scheduled_at IS NOT NULL
               AND DATE(m.scheduled_at) - rs.days_before = CURRENT_DATE
               AND rs.send_time <= CURRENT_TIME
               AND (rs.last_executed_at IS NULL OR DATE(rs.last_executed_at) < CURRENT_DATE)
             ORDER BY rs.send_time ASC",
        );
    }

    /**
     * Marque un rappel comme execute.
     */
    public function markExecuted(string $id, string $tenantId = ''): void {
        if ($tenantId !== '') {
            $this->execute(
                'UPDATE reminder_schedules SET last_executed_at = now(), updated_at = now()
                 WHERE id = :id AND tenant_id = :tid',
                [':id' => $id, ':tid' => $tenantId],
            );
        } else {
            $this->execute(
                'UPDATE reminder_schedules SET last_executed_at = now(), updated_at = now()
                 WHERE id = :id',
                [':id' => $id],
            );
        }
    }

    /**
     * Configure les rappels par defaut pour une seance (J-7, J-3, J-1).
     */
    public function setupDefaults(string $tenantId, string $meetingId, ?string $templateId = null): void {
        $defaults = [7, 3, 1];
        foreach ($defaults as $days) {
            $this->upsert($tenantId, $meetingId, $days, $templateId, '09:00', true);
        }
    }
}
