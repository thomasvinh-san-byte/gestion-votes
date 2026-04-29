<?php

declare(strict_types=1);

namespace AgVote\SSE;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Repository\MeetingRepository;

/**
 * Authentication and tenant-isolation gate for SSE consumers.
 *
 * Why this exists (F05 hardening):
 *   The SSE endpoint (`public/api/v1/events.php`) is a procedural script.
 *   Putting the access-control logic in a dedicated, dependency-injectable
 *   class lets us:
 *     1. Unit-test the gate decisions without HTTP/session bootstrap noise.
 *     2. Reuse the same gate in any future SSE endpoint (heartbeat, etc.).
 *
 * The gate enforces three properties in order:
 *   1. Auth is enabled OR caller has bypassed it via APP_AUTH_ENABLED=0
 *      (dev/demo only — production must keep auth ON).
 *   2. The session is active and not idle past the timeout.
 *   3. The meeting_id query parameter belongs to the session's tenant.
 *
 * On any failure, the gate returns a structured Decision the caller can
 * translate into the appropriate HTTP response. The caller stays in charge
 * of `header()` / `exit` — this class only decides.
 */
final class SseAuthGate {
    /** @var int Session idle timeout — matches AuthMiddleware::SESSION_TIMEOUT. */
    public const SESSION_TIMEOUT = 1800;

    public const RESULT_ALLOWED              = 'allowed';
    public const RESULT_AUTH_REQUIRED        = 'authentication_required';
    public const RESULT_SESSION_EXPIRED      = 'session_expired';
    public const RESULT_SESSION_MISSING_TENANT = 'session_missing_tenant';
    public const RESULT_INVALID_MEETING_ID   = 'invalid_meeting_id';
    public const RESULT_MEETING_NOT_FOUND    = 'meeting_not_found';

    private ?MeetingRepository $meetingRepo;

    public function __construct(?MeetingRepository $meetingRepo = null) {
        $this->meetingRepo = $meetingRepo;
    }

    /**
     * Evaluate access for an SSE request.
     *
     * @param array<string, mixed> $session     Effective $_SESSION snapshot.
     * @param string|null          $meetingId   Raw meeting_id query param (or null).
     * @param int                  $now         Current UNIX timestamp.
     * @param bool                 $authEnabled Whether auth is enforced.
     *
     * @return array{
     *   result: string,
     *   status: int,
     *   tenant_id: string|null,
     *   meeting: array<string, mixed>|null,
     *   refreshed_last_activity: int|null
     * }
     */
    public function evaluate(array $session, ?string $meetingId, int $now, bool $authEnabled = true): array {
        $tenantId = null;
        $refreshed = null;

        if ($authEnabled) {
            if (empty($session['auth_user'])) {
                return self::deny(self::RESULT_AUTH_REQUIRED, 401);
            }

            $lastActivity = (int) ($session['auth_last_activity'] ?? 0);
            if ($lastActivity > 0 && ($now - $lastActivity) > self::SESSION_TIMEOUT) {
                return self::deny(self::RESULT_SESSION_EXPIRED, 401);
            }

            $tenantId = (string) ($session['auth_user']['tenant_id'] ?? '');
            if ($tenantId === '') {
                return self::deny(self::RESULT_SESSION_MISSING_TENANT, 401);
            }

            $refreshed = $now;
        }

        // meeting_id is a strict UUID — guards against cache-key injection too.
        if ($meetingId === null || $meetingId === '' || !self::isUuid($meetingId)) {
            return self::deny(self::RESULT_INVALID_MEETING_ID, 400);
        }

        $meeting = null;
        if ($tenantId !== null) {
            $meeting = $this->meetingRepo()->findByIdForTenant($meetingId, $tenantId);
            if ($meeting === null) {
                // 404 (not 403) so a tenant-A user can't enumerate tenant-B meeting IDs
                // by observing different status codes.
                return self::deny(self::RESULT_MEETING_NOT_FOUND, 404);
            }
        }

        return [
            'result'                  => self::RESULT_ALLOWED,
            'status'                  => 200,
            'tenant_id'               => $tenantId,
            'meeting'                 => $meeting,
            'refreshed_last_activity' => $refreshed,
        ];
    }

    /**
     * @return array{result: string, status: int, tenant_id: null, meeting: null, refreshed_last_activity: null}
     */
    private static function deny(string $reason, int $status): array {
        return [
            'result'                  => $reason,
            'status'                  => $status,
            'tenant_id'               => null,
            'meeting'                 => null,
            'refreshed_last_activity' => null,
        ];
    }

    private static function isUuid(string $s): bool {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s);
    }

    private function meetingRepo(): MeetingRepository {
        return $this->meetingRepo ??= RepositoryFactory::getInstance()->meeting();
    }
}
