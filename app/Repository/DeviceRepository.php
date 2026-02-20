<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les devices (heartbeats, blocks, commands).
 */
class DeviceRepository extends AbstractRepository
{
    // =========================================================================
    // HEARTBEATS
    // =========================================================================

    /**
     * Liste les heartbeats avec block status (pour devices_list).
     */
    public function listHeartbeats(string $tenantId, string $meetingId = ''): array
    {
        return $this->selectAll(
            "SELECT
                hb.device_id::text AS device_id,
                hb.meeting_id::text AS meeting_id,
                hb.role, hb.ip, hb.user_agent,
                hb.battery_pct, hb.is_charging, hb.last_seen_at,
                COALESCE(db.is_blocked, false) AS is_blocked,
                db.reason AS block_reason
             FROM device_heartbeats hb
             LEFT JOIN LATERAL (
                SELECT is_blocked, reason
                FROM device_blocks
                WHERE tenant_id = hb.tenant_id
                  AND device_id = hb.device_id
                  AND (meeting_id IS NULL OR meeting_id = hb.meeting_id)
                ORDER BY updated_at DESC LIMIT 1
             ) db ON TRUE
             WHERE hb.tenant_id = ?::uuid
               AND (NULLIF(?,'') IS NULL OR hb.meeting_id = NULLIF(?,'')::uuid)
             ORDER BY hb.last_seen_at DESC LIMIT 500",
            [$tenantId, $meetingId, $meetingId]
        );
    }

    /**
     * Upsert heartbeat.
     */
    public function upsertHeartbeat(
        string $deviceId,
        string $tenantId,
        string $meetingId,
        string $role,
        string $ip,
        string $userAgent,
        ?int $batteryPct,
        ?bool $isCharging
    ): void {
        $this->execute(
            "INSERT INTO device_heartbeats (
                device_id, tenant_id, meeting_id, role, ip, user_agent, battery_pct, is_charging, last_seen_at
             ) VALUES (
                ?::uuid, ?::uuid, NULLIF(?,'')::uuid, NULLIF(?,'')::text, NULLIF(?,'')::text, NULLIF(?,'')::text,
                ?, ?, now()
             )
             ON CONFLICT (device_id)
             DO UPDATE SET
                meeting_id   = EXCLUDED.meeting_id,
                role         = EXCLUDED.role,
                ip           = EXCLUDED.ip,
                user_agent   = EXCLUDED.user_agent,
                battery_pct  = EXCLUDED.battery_pct,
                is_charging  = EXCLUDED.is_charging,
                last_seen_at = now()",
            [$deviceId, $tenantId, $meetingId, $role, $ip, $userAgent, $batteryPct, $isCharging]
        );
    }

    /**
     * Trouve le heartbeat d'un device (pour audit context).
     */
    public function findHeartbeat(string $tenantId, string $deviceId): ?array
    {
        return $this->selectOne(
            "SELECT role, ip, user_agent, battery_pct, is_charging, last_seen_at
             FROM device_heartbeats
             WHERE tenant_id = ?::uuid AND device_id = ?::uuid LIMIT 1",
            [$tenantId, $deviceId]
        );
    }

    // =========================================================================
    // BLOCKS
    // =========================================================================

    /**
     * Trouve le statut de blocage d'un device.
     */
    public function findBlockStatus(string $tenantId, string $deviceId, string $meetingId = ''): ?array
    {
        return $this->selectOne(
            "SELECT is_blocked, reason
             FROM device_blocks
             WHERE tenant_id = ?::uuid
               AND device_id = ?::uuid
               AND (meeting_id IS NULL OR meeting_id = NULLIF(?,'')::uuid)
             ORDER BY updated_at DESC LIMIT 1",
            [$tenantId, $deviceId, $meetingId]
        );
    }

    /**
     * Bloque un device.
     */
    public function blockDevice(string $tenantId, string $meetingId, string $deviceId, string $reason): void
    {
        $this->execute(
            "INSERT INTO device_blocks (tenant_id, meeting_id, device_id, is_blocked, reason, blocked_at, updated_at)
             VALUES (?::uuid, NULLIF(?,'')::uuid, ?::uuid, true, NULLIF(?,'')::text, now(), now())
             ON CONFLICT (COALESCE(meeting_id, '00000000-0000-0000-0000-000000000000'::uuid), device_id)
             DO UPDATE SET is_blocked = true, reason = EXCLUDED.reason, updated_at = now()",
            [$tenantId, $meetingId, $deviceId, $reason]
        );
    }

    /**
     * Debloque un device.
     */
    public function unblockDevice(string $tenantId, string $meetingId, string $deviceId): void
    {
        $this->execute(
            "INSERT INTO device_blocks (tenant_id, meeting_id, device_id, is_blocked, reason, blocked_at, updated_at)
             VALUES (?::uuid, NULLIF(?,'')::uuid, ?::uuid, false, NULL, now(), now())
             ON CONFLICT (COALESCE(meeting_id, '00000000-0000-0000-0000-000000000000'::uuid), device_id)
             DO UPDATE SET is_blocked = false, reason = NULL, updated_at = now()",
            [$tenantId, $meetingId, $deviceId]
        );
    }

    // =========================================================================
    // COMMANDS
    // =========================================================================

    /**
     * Trouve la commande kick en attente pour un device.
     */
    public function findPendingKick(string $tenantId, string $deviceId): ?array
    {
        return $this->selectOne(
            "SELECT id, payload
             FROM device_commands
             WHERE tenant_id = ?::uuid AND device_id = ?::uuid
               AND command = 'kick' AND consumed_at IS NULL
             ORDER BY created_at DESC LIMIT 1",
            [$tenantId, $deviceId]
        );
    }

    /**
     * Marque une commande comme consommee.
     */
    public function consumeCommand(string $commandId, string $tenantId = ''): void
    {
        if ($tenantId !== '') {
            $this->execute(
                "UPDATE device_commands SET consumed_at = now() WHERE id = ?::uuid AND tenant_id = ?",
                [$commandId, $tenantId]
            );
        } else {
            $this->execute(
                "UPDATE device_commands SET consumed_at = now() WHERE id = ?::uuid",
                [$commandId]
            );
        }
    }

    /**
     * Insere une commande kick.
     */
    public function insertKickCommand(string $tenantId, string $meetingId, string $deviceId, string $message): void
    {
        $this->execute(
            "INSERT INTO device_commands (tenant_id, meeting_id, device_id, command, payload)
             VALUES (?::uuid, NULLIF(?,'')::uuid, ?::uuid, 'kick', ?::jsonb)",
            [$tenantId, $meetingId, $deviceId, json_encode(['message' => $message])]
        );
    }
}
