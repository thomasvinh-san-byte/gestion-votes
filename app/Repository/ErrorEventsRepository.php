<?php

declare(strict_types=1);

namespace AgVote\Repository;

use AgVote\Core\Logger;
use Throwable;

/**
 * Data access for error_events — server-side capture of api_fail() responses.
 *
 * Source for /admin/error-stats. Writes are best-effort (capture must never
 * break the API response); reads power dashboard queries (top codes, timeline,
 * tenant drill-down).
 */
final class ErrorEventsRepository extends AbstractRepository {
    public function capture(
        string $errorCode,
        int $httpStatus,
        ?string $tenantId,
        ?string $userId,
        ?string $route,
        ?string $method,
        ?string $requestId,
        array $payload,
    ): void {
        try {
            $this->execute(
                'INSERT INTO error_events
                    (request_id, tenant_id, user_id, error_code, http_status, route, method, payload)
                    VALUES (:rid, :tid, :uid, :code, :status, :route, :method, :payload::jsonb)',
                [
                    ':rid' => $requestId,
                    ':tid' => $tenantId,
                    ':uid' => $userId,
                    ':code' => $errorCode,
                    ':status' => $httpStatus,
                    ':route' => $route,
                    ':method' => $method,
                    ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            );
        } catch (Throwable $e) {
            // Capture must never break api_fail. Log the failure and move on —
            // missing one row is acceptable, breaking the user's request is not.
            Logger::warning('error_events capture failed', [
                'exception' => $e->getMessage(),
                'error_code' => $errorCode,
            ]);
        }
    }

    /**
     * @return list<array{error_code: string, count: int, last_occurred_at: string}>
     */
    public function topCodesSince(int $hours = 168, int $limit = 10, ?string $tenantId = null): array {
        $sql = 'SELECT error_code, COUNT(*) AS count, MAX(occurred_at) AS last_occurred_at
                FROM error_events
                WHERE occurred_at >= NOW() - (:hours || \' hours\')::interval';
        $params = [':hours' => (string) $hours];
        if ($tenantId !== null && $tenantId !== '') {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' GROUP BY error_code ORDER BY count DESC, last_occurred_at DESC LIMIT :lim';
        $params[':lim'] = (string) $limit;
        $rows = $this->selectAll($sql, $params);
        return array_map(static fn(array $r) => [
            'error_code' => (string) $r['error_code'],
            'count' => (int) $r['count'],
            'last_occurred_at' => (string) $r['last_occurred_at'],
        ], $rows);
    }

    /**
     * @return list<array{bucket: string, count: int}>
     */
    public function timelineSince(int $hours = 168, ?string $tenantId = null): array {
        $sql = 'SELECT date_trunc(\'hour\', occurred_at) AS bucket, COUNT(*) AS count
                FROM error_events
                WHERE occurred_at >= NOW() - (:hours || \' hours\')::interval';
        $params = [':hours' => (string) $hours];
        if ($tenantId !== null && $tenantId !== '') {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' GROUP BY bucket ORDER BY bucket ASC';
        $rows = $this->selectAll($sql, $params);
        return array_map(static fn(array $r) => [
            'bucket' => (string) $r['bucket'],
            'count' => (int) $r['count'],
        ], $rows);
    }

    public function totalSince(int $hours = 168, ?string $tenantId = null): int {
        $sql = 'SELECT COUNT(*) FROM error_events WHERE occurred_at >= NOW() - (:hours || \' hours\')::interval';
        $params = [':hours' => (string) $hours];
        if ($tenantId !== null && $tenantId !== '') {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        return (int) $this->scalar($sql, $params);
    }
}
