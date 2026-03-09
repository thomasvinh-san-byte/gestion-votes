<?php

declare(strict_types=1);

namespace AgVote\Repository;

use Throwable;

/**
 * Infrastructure & system monitoring queries.
 *
 * Extracted from UserRepository — these are platform-level concerns
 * (DB health, metrics, alerts) not related to user data access.
 */
class SystemRepository extends AbstractRepository {
    /**
     * Ping the database and return latency in ms (or null on error).
     */
    public function dbPing(): ?float {
        $t0 = microtime(true);
        try {
            $this->scalar('SELECT 1');
            return (microtime(true) - $t0) * 1000.0;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Return the number of active database connections (or null on error).
     */
    public function dbActiveConnections(): ?int {
        try {
            $val = $this->scalar('SELECT COUNT(*) FROM pg_stat_activity WHERE datname = current_database()');
            return $val !== null ? (int) $val : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Count audit events for a tenant.
     */
    public function countAuditEvents(string $tenantId): ?int {
        try {
            return (int) ($this->scalar(
                'SELECT COUNT(*) FROM audit_events WHERE tenant_id = :t',
                [':t' => $tenantId],
            ) ?? 0);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Count auth failures in the last 15 minutes.
     */
    public function countAuthFailures15m(): ?int {
        try {
            return (int) ($this->scalar(
                "SELECT COUNT(*) FROM auth_failures WHERE created_at > NOW() - INTERVAL '15 minutes'",
            ) ?? 0);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Insert a system metrics row.
     */
    public function insertSystemMetric(array $data): void {
        $this->execute(
            'INSERT INTO system_metrics(server_time, db_latency_ms, db_active_connections, disk_free_bytes, disk_total_bytes, count_meetings, count_motions, count_vote_tokens, count_audit_events, auth_failures_15m)
             VALUES (:st,:lat,:ac,:free,:tot,:cm,:cmo,:ct,:ca,:af)',
            [
                ':st' => $data['server_time'],
                ':lat' => $data['db_latency_ms'],
                ':ac' => $data['db_active_connections'],
                ':free' => $data['disk_free_bytes'],
                ':tot' => $data['disk_total_bytes'],
                ':cm' => $data['count_meetings'],
                ':cmo' => $data['count_motions'],
                ':ct' => $data['count_vote_tokens'],
                ':ca' => $data['count_audit_events'],
                ':af' => $data['auth_failures_15m'],
            ],
        );
    }

    /**
     * Check if a recent alert exists (within 10 min) for a given code.
     */
    public function findRecentAlert(string $code): bool {
        return (bool) $this->scalar(
            "SELECT 1 FROM system_alerts WHERE code = :c AND created_at > NOW() - INTERVAL '10 minutes' LIMIT 1",
            [':c' => $code],
        );
    }

    /**
     * Insert a system alert.
     */
    public function insertSystemAlert(string $code, string $severity, string $message, ?string $detailsJson): void {
        $this->execute(
            'INSERT INTO system_alerts(code, severity, message, details_json, created_at) VALUES (:c,:s,:m,:d,NOW())',
            [':c' => $code, ':s' => $severity, ':m' => $message, ':d' => $detailsJson],
        );
    }

    /**
     * List recent system alerts.
     */
    public function listRecentAlerts(int $limit = 20): array {
        try {
            return $this->selectAll(
                'SELECT id, created_at, code, severity, message, details_json FROM system_alerts ORDER BY created_at DESC LIMIT :lim',
                [':lim' => max(1, $limit)],
            );
        } catch (Throwable $e) {
            return [];
        }
    }
}
