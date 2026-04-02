<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\ProcurationPdfService;

/**
 * Sert le PDF de procuration (pouvoir de representation) en telechargement direct.
 *
 * GET /api/v1/procuration_pdf?proxy_id=UUID&meeting_id=UUID
 * Roles autorises : operator, admin, president
 */
final class ProcurationPdfController extends AbstractController
{
    /**
     * Telecharge le PDF de procuration pour un proxy enregistre.
     *
     * Validation:
     *  - proxy_id et meeting_id requis, format UUID valide
     *  - La seance doit exister et appartenir au tenant courant
     *  - Le proxy doit exister pour cette seance et ce tenant
     *
     * Emets Content-Disposition: attachment pour declenchement download navigateur.
     */
    public function download(): void
    {
        // 1. Lire les parametres
        $proxyId   = api_query('proxy_id');
        $meetingId = api_query('meeting_id');

        // 2. Valider proxy_id
        if ($proxyId === '') {
            api_fail('missing_proxy_id', 400);
        }
        if (!api_is_uuid($proxyId)) {
            api_fail('invalid_proxy_id', 400);
        }

        // 3. Valider meeting_id
        if ($meetingId === '') {
            api_fail('missing_meeting_id', 400);
        }
        if (!api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        $tenantId = api_current_tenant_id();

        // 4. Charger la seance
        $meeting = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenantId);
        if ($meeting === null || $meeting['tenant_id'] !== $tenantId) {
            api_fail('meeting_not_found', 404);
        }

        // 5. Charger la procuration
        $proxy = $this->repo()->proxy()->findWithNames($proxyId, $meetingId, $tenantId);
        if ($proxy === null) {
            api_fail('proxy_not_found', 404);
        }

        // 6. Nom de l'organisation
        $orgName = (string) ($this->repo()->settings()->get($tenantId, 'org_name') ?? '');

        // 7. Generer le PDF
        $pdf = (new ProcurationPdfService())->generatePdf($proxy, $meeting, $orgName);

        // 8. Construire le nom de fichier
        $safeGiver = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) ($proxy['giver_name'] ?? 'mandant'));
        $filename  = 'PROCURATION_' . $safeGiver . '_' . date('Ymd') . '.pdf';

        // 9. Audit
        audit_log('procuration.pdf_download', 'proxy', $proxyId, [
            'giver_name'    => $proxy['giver_name'] ?? '',
            'receiver_name' => $proxy['receiver_name'] ?? '',
        ], $meetingId);

        // 10. Emettre les headers et le corps PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        header('X-Content-Type-Options: nosniff');
        echo $pdf;
    }
}
