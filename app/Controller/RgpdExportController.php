<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\RgpdExportService;

/**
 * RgpdExportController — RGPD Article 20 data portability export.
 *
 * GET /api/v1/rgpd_export
 *
 * Returns a JSON file download containing the authenticated user's personal
 * data: profile (member record), votes (all ballots), and attendances.
 *
 * Authentication is enforced via api_require_role() — any valid session role
 * is accepted. The export is scoped to the session's user_id and tenant_id,
 * so cross-tenant access is structurally impossible.
 */
final class RgpdExportController extends AbstractController
{
    public function download(): void
    {
        api_request('GET');
        // Any authenticated user may export their own data — no role restriction beyond login
        api_require_role(['admin', 'operator', 'viewer', 'auditor', 'member', 'president', 'trust']);

        $userId   = api_current_user_id();
        $tenantId = api_current_tenant_id();

        $data     = (new RgpdExportService())->exportForUser((string) $userId, $tenantId);
        $json     = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $filename = 'mes_donnees_agvote_' . date('Ymd') . '.json';

        audit_log('rgpd.data_export', 'user', $userId, ['tenant_id' => $tenantId]);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen((string) $json));
        header('X-Content-Type-Options: nosniff');
        echo $json;
    }
}
