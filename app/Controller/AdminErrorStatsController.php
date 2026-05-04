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
        api_ok([
            'window_hours' => $hours,
            'tenant_filter' => $tenantFilter,
            'total' => $repo->totalSince($hours, $tenantFilter),
            'top_codes' => $repo->topCodesSince($hours, $limit, $tenantFilter),
            'timeline' => $repo->timelineSince($hours, $tenantFilter),
        ]);
    }
}
