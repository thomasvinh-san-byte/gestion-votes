<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MotionRepository;

/**
 * Dev-only endpoint that seeds a fully-shaped meeting (status + motions) so
 * Playwright `@integration` specs can run against realistic fixtures without
 * driving the full operator UI workflow.
 *
 * Triple-guard pattern (defence-in-depth):
 *   1. Conditional route registration in `app/routes.php` (outer gate)
 *   2. EnvGuardMiddleware on the route (request-level gate)
 *   3. `guardProduction()` here (inner gate, mirrors DevSeedController)
 *
 * Audit-logs every successful invocation so any leak attempt is traceable.
 *
 * Source: TEST-V24-01 / D-01..D-04 — Plan 03.1 (Phase 3 v2.4).
 */
final class TestSeedController extends AbstractController {
    public function __construct(
        private ?MeetingRepository $meetingRepo = null,
        private ?MotionRepository $motionRepo = null,
    ) {
        parent::__construct();
    }

    /**
     * POST /api/v1/test/seed-meeting
     *
     * Body (JSON): { tenantId: string, status?: string, motionsCount?: int }
     * Response: { ok: true, data: { meeting_id: string, motion_ids: string[] } }
     *
     * Returns 404 in production via EnvGuardMiddleware + guardProduction().
     */
    public function seedMeeting(): void {
        $this->guardProduction();

        $in = api_request('POST');
        $tenantId = trim((string) ($in['tenantId'] ?? ''));
        $status = trim((string) ($in['status'] ?? 'setup'));
        $motionsCount = max(0, min(50, (int) ($in['motionsCount'] ?? 0)));

        if ($tenantId === '') {
            api_fail('invalid_request', 422, ['detail' => 'tenantId requis.']);
        }

        $meetingRepo = $this->meetingRepo ?? $this->repo()->meeting();
        $motionRepo = $this->motionRepo ?? $this->repo()->motion();

        $meetingId = $meetingRepo->createForTest($tenantId, $status);

        $motionIds = [];
        for ($i = 0; $i < $motionsCount; $i++) {
            $motionIds[] = $motionRepo->createForTest(
                $tenantId,
                $meetingId,
                'Motion test #' . ($i + 1),
            );
        }

        audit_log(
            'test_seed_meeting',
            'meeting',
            $meetingId,
            [
                'tenant_id' => $tenantId,
                'status' => $status,
                'motions_count' => $motionsCount,
            ],
            $meetingId,
        );

        api_ok([
            'meeting_id' => $meetingId,
            'motion_ids' => $motionIds,
        ]);
    }

    /**
     * POST /api/v1/test/delete-meeting
     *
     * Body (JSON): { tenantId: string, meetingId: string }
     * Response: { ok: true, data: { deleted: true } }
     *
     * Supprime une séance peu importe son statut (bypass du guard 'draft' de
     * deleteDraft()) — destiné aux specs Playwright qui doivent reproduire la
     * race « ressource supprimée pendant l'affichage ». Triple-guarded :
     * route conditional + EnvGuardMiddleware + guardProduction().
     *
     * Source: RACE-V27-01 — Plan 03-01 (Phase 3 v2.7).
     */
    public function deleteMeeting(): void {
        $this->guardProduction();

        $in = api_request('POST');
        $tenantId = trim((string) ($in['tenantId'] ?? ''));
        $meetingId = trim((string) ($in['meetingId'] ?? ''));

        if ($tenantId === '' || $meetingId === '') {
            api_fail('invalid_request', 422, ['detail' => 'tenantId et meetingId requis.']);
        }

        $meetingRepo = $this->meetingRepo ?? $this->repo()->meeting();
        $deleted = $meetingRepo->deleteForTest($tenantId, $meetingId);

        audit_log(
            'test_delete_meeting',
            'meeting',
            $meetingId,
            [
                'tenant_id' => $tenantId,
                'deleted' => $deleted,
            ],
            $meetingId,
        );

        api_ok(['deleted' => $deleted]);
    }

    /**
     * Inner guard mirroring DevSeedController. Returns 403 if APP_ENV is
     * production/prod even when the route is somehow reached (belt-and-braces).
     *
     * Reads APP_ENV from $_ENV → getenv() → config('env') in that order so
     * unit tests can drive the guard via the standard env mechanisms while
     * production deployments keep using the configured `env` value.
     */
    private function guardProduction(): void {
        $env = strtolower((string) (
            $_ENV['APP_ENV']
            ?? getenv('APP_ENV')
            ?: config('env', 'dev')
            ?? 'dev'
        ));
        if (in_array($env, ['production', 'prod'], true)) {
            api_fail('endpoint_disabled', 403, [
                'detail' => 'Cet endpoint de développement est désactivé en production.',
            ]);
        }
    }
}
