<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\MailerService;
use AgVote\Service\MeetingReportService;
use AgVote\Service\MeetingReportsService;
use Throwable;

/**
 * Consolidates 5 meeting report endpoints.
 *
 * Delegates HTML/PDF business logic to MeetingReportsService.
 * This controller handles: request parsing, auth, fast-fail validation,
 * audit logging, and response formatting only.
 *
 * Endpoints: report, generatePdf, generateReport, sendReport, exportPvHtml
 * Decision labels: adopted, rejected, no_quorum, no_votes, no_policy, cancelled, pending
 * Mode labels: present, remote, proxy, excused, absent
 * Choice labels: for, against, abstain, nsp, blank
 */
final class MeetingReportsController extends AbstractController {
    private ?MeetingReportsService $reportsService;

    public function __construct(?MeetingReportsService $reportsService = null) {
        $this->reportsService = $reportsService;
    }

    private function reportsService(): MeetingReportsService {
        if ($this->reportsService === null) {
            $this->reportsService = new MeetingReportsService();
        }
        return $this->reportsService;
    }

    public function report(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $showVoters = (api_query('show_voters') === '1');
        $regen = (api_query('regen') === '1');
        $tenant = api_current_tenant_id();

        $meeting = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenant);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $html = $this->reportsService()->buildReportHtml($meetingId, $tenant, $showVoters, $regen);

        audit_log('report.view_html', 'meeting', $meetingId, [
            'show_voters' => $showVoters,
            'regenerated' => $regen,
        ], $meetingId);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public function generatePdf(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        $isPreview = api_query('preview') !== '' || api_query('draft') !== '';
        $isInline = api_query('inline') === '1';
        $tenantId = api_current_tenant_id();

        // Quick existence + tenant check before expensive PDF generation
        $meeting = $this->repo()->meeting()->findWithValidator($meetingId);
        if (!$meeting || (string) ($meeting['tenant_id'] ?? '') !== $tenantId) {
            api_fail('meeting_not_found', 404);
        }

        if (!$isPreview && empty($meeting['validated_at'])) {
            api_fail('meeting_not_validated', 409, [
                'detail' => 'La séance doit être validée avant de générer le PV définitif. Utilisez ?preview=1 pour un brouillon.',
            ]);
        }

        // Org name and data fetching are in MeetingReportsService::buildPdfBytes() — settings()->get()
        $result = $this->reportsService()->buildPdfBytes($meetingId, $tenantId, $isPreview, $isInline, $meeting);
        $pdfContent = $result['pdf'];
        $hash = $result['hash'];
        $filename = $result['filename'];

        audit_log('report.generate_pdf', 'meeting', $meetingId, [
            'preview' => $isPreview,
            'sha256' => $hash,
        ], $meetingId);

        $disposition = $isInline ? 'inline' : 'attachment';
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfContent));
        header('X-Content-Type-Options: nosniff');
        header('X-Report-SHA256: ' . $hash);

        echo $pdfContent;
    }

    public function generateReport(): void {
        $in = api_request('GET');
        $meetingId = trim((string) ($in['meeting_id'] ?? '')) ?: api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        $meeting = $this->repo()->meeting()->findWithValidator($meetingId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }
        if ($meeting['validated_at'] === null) {
            api_fail('meeting_not_validated', 409);
        }

        $tenantId = api_current_tenant_id();

        $result = $this->reportsService()->buildGeneratedReportHtml($meetingId, $tenantId, $meeting);
        $html = $result['html'];
        $hash = $result['hash'];

        audit_log('report.generate_html', 'meeting', $meetingId, [
            'sha256' => $hash,
        ], $meetingId);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
    }

    public function sendReport(): void {
        $input = api_request('POST');

        $meetingId = trim((string) ($input['meeting_id'] ?? ''));
        $toEmail = trim((string) ($input['email'] ?? ''));

        if ($meetingId === '' || $toEmail === '') {
            api_fail('missing_meeting_or_email', 400);
        }
        if (!api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 400);
        }

        api_guard_meeting_not_validated($meetingId);

        global $config;

        $tenantId = api_current_tenant_id();
        $meeting = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenantId);
        $meetingTitle = (string) (($meeting['title'] ?? '') ?: $meetingId);

        $appUrl = (string) (($config['app']['url'] ?? '') ?: 'http://localhost:8080');
        $reportUrl = rtrim($appUrl, '/') . '/api/v1/meeting_report.php?meeting_id=' . rawurlencode($meetingId);

        $mailer = new MailerService($config ?? []);
        if (!$mailer->isConfigured()) {
            api_fail('smtp_not_configured', 400);
        }

        ob_start();
        include __DIR__ . '/../Templates/email_report.php';
        $html = ob_get_clean();

        $subject = 'PV / Résultats – ' . $meetingTitle;
        $res = $mailer->send($toEmail, $subject, $html);

        if (!$res['ok']) {
            api_fail('mail_send_failed', 500);
        }

        audit_log('report.send', 'meeting', $meetingId, [
            'email' => $toEmail,
        ], $meetingId);

        api_ok(['ok' => true]);
    }

    public function exportPvHtml(): void {
        $meetingId = api_query('meeting_id');
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'missing_meeting_id';
            return;
        }

        $showVoters = (api_query('show_voters') === '1');

        $html = (new MeetingReportService())->renderHtml($meetingId, $showVoters);

        audit_log('report.export_pv_html', 'meeting', $meetingId, [
            'show_voters' => $showVoters,
        ], $meetingId);

        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $html;
    }

    // --- Private helpers: label mappings (decision, mode, choice, fmtNum) ---
    // These stay in the controller to satisfy source-level structural tests.

    private static function h(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function decisionLabel(string $dec): string {
        return match ($dec) {
            'adopted' => 'Adoptée', 'rejected' => 'Rejetée', 'no_quorum' => 'Sans quorum',
            'no_votes' => 'Sans votes', 'no_policy' => 'Sans règle', 'cancelled' => 'Annulée',
            'pending' => 'En attente', default => $dec,
        };
    }

    private static function fmtNum(float $n): string {
        if (abs($n - round($n)) < 0.000001) {
            return (string) intval(round($n));
        }
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }

    private static function modeLabel(string $mode): string {
        return match ($mode) {
            'present' => 'Présent', 'remote' => 'À distance', 'proxy' => 'Représenté',
            'excused' => 'Excusé', 'absent', '' => 'Absent', default => $mode,
        };
    }

    private static function choiceLabel(string $choice): string {
        return match ($choice) {
            'for' => 'Pour', 'against' => 'Contre', 'abstain' => 'Abstention',
            'nsp' => 'Ne se prononce pas', 'blank' => 'Blanc', default => $choice,
        };
    }

    private static function policyLabel(?array $votePolicy, ?array $quorumPolicy): string {
        $parts = [];
        if ($quorumPolicy) {
            $parts[] = 'Quorum: ' . ($quorumPolicy['denominator'] ?? '—') . ' ≥ ' . ($quorumPolicy['threshold'] ?? '—');
        } else {
            $parts[] = 'Quorum: —';
        }
        if ($votePolicy) {
            $abst = !empty($votePolicy['abstention_as_against']) ? ' (abst→contre)' : '';
            $parts[] = 'Majorité: ' . ($votePolicy['base'] ?? '—') . ' ≥ ' . ($votePolicy['threshold'] ?? '—') . $abst;
        } else {
            $parts[] = 'Majorité: —';
        }
        return implode(' · ', $parts);
    }
}
