<?php

declare(strict_types=1);

namespace AgVote\Repository;

use AgVote\Core\Logger;
use Throwable;

/**
 * Data access for next_step_clicks — UX metric for ErrorDictionary suggestions.
 *
 * Writes are best-effort (a failed metric insert must never affect the user
 * journey). Reads aggregate clicks per error_code for the admin dashboard.
 */
final class NextStepClicksRepository extends AbstractRepository {
    public function capture(
        string $errorCode,
        ?string $suggestion,
        ?string $tenantId,
        ?string $userId,
        ?string $route,
        ?string $requestId,
    ): void {
        try {
            $this->execute(
                'INSERT INTO next_step_clicks
                    (request_id, tenant_id, user_id, error_code, suggestion, route)
                    VALUES (:rid, :tid, :uid, :code, :sug, :route)',
                [
                    ':rid' => $requestId,
                    ':tid' => $tenantId,
                    ':uid' => $userId,
                    ':code' => $errorCode,
                    ':sug' => $suggestion,
                    ':route' => $route,
                ],
            );
        } catch (Throwable $e) {
            Logger::warning('next_step_clicks capture failed', [
                'exception' => $e->getMessage(),
                'error_code' => $errorCode,
            ]);
        }
    }

    /**
     * @return list<array{error_code: string, clicks: int}>
     */
    public function clicksByCodeSince(int $hours = 168, ?string $tenantId = null): array {
        $sql = 'SELECT error_code, COUNT(*) AS clicks
                FROM next_step_clicks
                WHERE occurred_at >= NOW() - (:hours || \' hours\')::interval';
        $params = [':hours' => (string) $hours];
        if ($tenantId !== null && $tenantId !== '') {
            $sql .= ' AND tenant_id = :tid';
            $params[':tid'] = $tenantId;
        }
        $sql .= ' GROUP BY error_code';
        $rows = $this->selectAll($sql, $params);
        return array_map(static fn(array $r) => [
            'error_code' => (string) $r['error_code'],
            'clicks' => (int) $r['clicks'],
        ], $rows);
    }
}
