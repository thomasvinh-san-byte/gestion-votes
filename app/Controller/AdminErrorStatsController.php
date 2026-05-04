<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * /admin/error-stats — server-side error capture dashboard.
 *
 * Reads error_events (LOG-V25-02) and exposes top codes / timeline / drill-down
 * for the admin observability page.
 *
 * Query params:
 *   hours (int, default 168 = 7d): rolling window
 *   limit (int, default 10): top-N for code ranking
 *   tenant (uuid, optional): drill-down by tenant (admins can cross tenants;
 *                            non-admin callers are scoped to their own tenant
 *                            by RoleMiddleware before reaching this controller)
 *   global (1, optional): when set by an admin, skip the tenant filter to see
 *                         cross-tenant aggregates
 */
final class AdminErrorStatsController extends AbstractController {
    public function stats(): void {
        api_request('GET');

        $hours = max(1, min(720, api_query_int('hours', 168)));
        $limit = max(1, min(100, api_query_int('limit', 10)));
        $isGlobal = api_query('global') === '1';
        $explicitTenant = api_query('tenant');

        $tenantFilter = null;
        if ($isGlobal) {
            $tenantFilter = null; // cross-tenant view (admin-only — RoleMiddleware enforces)
        } elseif ($explicitTenant !== '' && api_is_uuid((string) $explicitTenant)) {
            $tenantFilter = (string) $explicitTenant;
        } else {
            // Default: scope to caller's tenant
            $tenantFilter = api_current_tenant_id() ?: null;
        }

        $repo = $this->repo()->errorEvent();
        $clicksRepo = $this->repo()->nextStepClick();
        $topCodes = $repo->topCodesSince($hours, $limit, $tenantFilter);
        $clicks = $clicksRepo->clicksByCodeSince($hours, $tenantFilter);
        $clickIndex = [];
        foreach ($clicks as $c) {
            $clickIndex[$c['error_code']] = $c['clicks'];
        }
        // Decorate top codes with click count + CTR
        $topCodes = array_map(static function (array $row) use ($clickIndex) {
            $clicks = (int) ($clickIndex[$row['error_code']] ?? 0);
            $count = (int) $row['count'];
            $row['next_step_clicks'] = $clicks;
            $row['next_step_ctr'] = $count > 0 ? round($clicks / $count, 3) : 0.0;
            return $row;
        }, $topCodes);

        api_ok([
            'window_hours' => $hours,
            'tenant_filter' => $tenantFilter,
            'total' => $repo->totalSince($hours, $tenantFilter),
            'top_codes' => $topCodes,
            'timeline' => $repo->timelineSince($hours, $tenantFilter),
        ]);
    }
}
