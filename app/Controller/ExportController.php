<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\ExportService;

/**
 * Consolidates 9 export endpoints.
 *
 * Every export follows the same shape:
 *   require role → validate meeting → fetch data → output file
 *
 * The two helpers below eliminate the validation boilerplate
 * that was copy-pasted (with inconsistencies) across all 9 files.
 */
final class ExportController extends AbstractController {
    private function requireMeetingId(): string {
        $id = api_query('meeting_id');
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_meeting_id', 400);
        }
        return $id;
    }

    private function requireValidatedMeeting(string $meetingId): array {
        $mt = $this->repo()->meeting()->findByIdForTenant($meetingId, api_current_tenant_id());
        if (!$mt) {
            api_fail('meeting_not_found', 404);
        }
        if (empty($mt['validated_at'])) {
            api_fail('meeting_not_validated', 409, [
                'detail' => 'Les exports ne sont disponibles qu\'après la validation de la séance.',
            ]);
        }
        return $mt;
    }

    private function auditExport(string $type, string $meetingId, string $format = 'csv'): void {
        audit_log('export.' . $type, 'meeting', $meetingId, [
            'format' => $format,
            'exported_by' => api_current_user_id(),
        ], $meetingId);
    }

    // ------------------------------------------------------------------
    // Attendance
    // ------------------------------------------------------------------

    public function attendanceCsv(): void {
        // Flush any output buffer to prevent headers-already-sent issues
        if (ob_get_level() > 0) { ob_end_clean(); }

        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('attendance', $mt['id'], 'csv');

        $rows = $this->repo()->attendance()->listExportForMeeting($mt['id'], api_current_tenant_id());
        $export = new ExportService();

        $filename = $export->generateFilename('presences', $mt['title'] ?? '');
        $export->initCsvOutput($filename);
        $out = $export->openCsvOutput();
        $export->writeCsvRow($out, $export->getAttendanceHeaders());
        foreach ($rows as $r) {
            $export->writeCsvRow($out, $export->formatAttendanceRow($r));
        }
        fclose($out);
    }

    public function attendanceXlsx(): void {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('attendance', $mt['id'], 'xlsx');

        $rows = $this->repo()->attendance()->yieldExportForMeeting($mt['id'], api_current_tenant_id());
        $export = new ExportService();
        $filename = $export->generateFilename('presences', $mt['title'] ?? '', 'xlsx');
        $export->streamXlsx($filename, $export->getAttendanceHeaders(), $rows, [$export, 'formatAttendanceRow'], 'Emargement');
    }

    // ------------------------------------------------------------------
    // Votes
    // ------------------------------------------------------------------

    public function votesCsv(): void {
        // Flush any output buffer to prevent headers-already-sent issues
        if (ob_get_level() > 0) { ob_end_clean(); }

        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('votes', $mt['id'], 'csv');

        $rows = $this->repo()->ballot()->listVotesExportForMeeting($mt['id'], api_current_tenant_id());
        $export = new ExportService();

        $filename = $export->generateFilename('votes', $mt['title'] ?? '');
        $export->initCsvOutput($filename);
        $out = $export->openCsvOutput();
        $export->writeCsvRow($out, $export->getVotesHeaders());
        foreach ($rows as $r) {
            if (!empty($r['voter_name'])) {
                $export->writeCsvRow($out, $export->formatVoteRow($r));
            }
        }
        fclose($out);
    }

    public function votesXlsx(): void {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('votes', $mt['id'], 'xlsx');

        $rows = $this->repo()->ballot()->yieldVotesExportForMeeting($mt['id'], api_current_tenant_id());
        $export = new ExportService();
        $filename = $export->generateFilename('votes', $mt['title'] ?? '', 'xlsx');
        $filteredRows = (function () use ($rows) {
            foreach ($rows as $row) {
                if (!empty($row['voter_name'])) {
                    yield $row;
                }
            }
        })();
        $export->streamXlsx($filename, $export->getVotesHeaders(), $filteredRows, [$export, 'formatVoteRow'], 'Votes');
    }

    // ------------------------------------------------------------------
    // Members
    // ------------------------------------------------------------------

    public function membersCsv(): void {
        // Flush any output buffer to prevent headers-already-sent issues
        if (ob_get_level() > 0) { ob_end_clean(); }

        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('members', $mt['id'], 'csv');

        $rows = $this->repo()->member()->listExportForMeeting($mt['id'], api_current_tenant_id());
        $export = new ExportService();

        $filename = $export->generateFilename('membres', $mt['title'] ?? '');
        $export->initCsvOutput($filename);
        $out = $export->openCsvOutput();
        $export->writeCsvRow($out, $export->getMembersHeaders());
        foreach ($rows as $r) {
            $export->writeCsvRow($out, $export->formatMemberRow($r));
        }
        fclose($out);
    }

    // ------------------------------------------------------------------
    // Motion results
    // ------------------------------------------------------------------

    public function motionResultsCsv(): void {
        // Flush any output buffer to prevent headers-already-sent issues
        if (ob_get_level() > 0) { ob_end_clean(); }

        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('motion_results', $mt['id'], 'csv');
        $tenantId = api_current_tenant_id();

        $rows = $this->repo()->motion()->listResultsExportForMeeting($mt['id'], $tenantId);
        $export = new ExportService();

        $filename = $export->generateFilename('resultats', $mt['title'] ?? '');
        $export->initCsvOutput($filename);
        $out = $export->openCsvOutput();
        $export->writeCsvRow($out, $export->getMotionResultsHeaders());
        foreach ($rows as $r) {
            $export->writeCsvRow($out, $export->formatMotionResultRow($r));
        }
        fclose($out);
    }

    public function resultsXlsx(): void {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('motion_results', $mt['id'], 'xlsx');
        $tenantId = api_current_tenant_id();

        $rows = $this->repo()->motion()->yieldResultsExportForMeeting($mt['id'], $tenantId);
        $export = new ExportService();
        $filename = $export->generateFilename('resultats', $mt['title'] ?? '', 'xlsx');
        $export->streamXlsx($filename, $export->getMotionResultsHeaders(), $rows, [$export, 'formatMotionResultRow'], 'Resultats');
    }

    // ------------------------------------------------------------------
    // Full multi-sheet export
    // ------------------------------------------------------------------

    public function fullXlsx(): void {
        $meetingId = $this->requireMeetingId();
        $mt = $this->requireValidatedMeeting($meetingId);
        $this->auditExport('full', $meetingId, 'xlsx');
        $includeVotes = filter_var(api_query('include_votes', '1'), FILTER_VALIDATE_BOOLEAN);

        $tenantId = api_current_tenant_id();
        $attendanceRows = $this->repo()->attendance()->yieldExportForMeeting($meetingId, $tenantId);
        $motionRows = $this->repo()->motion()->yieldResultsExportForMeeting($meetingId, $tenantId);
        $voteRows = $this->repo()->ballot()->yieldVotesExportForMeeting($meetingId, $tenantId);

        $export = new ExportService();
        $filename = $export->generateFilename('complet', $mt['title'] ?? '', 'xlsx');
        $export->streamFullXlsx($filename, $mt, $attendanceRows, $motionRows, $voteRows, $includeVotes);
    }

    // ------------------------------------------------------------------
    // Ballots audit
    // ------------------------------------------------------------------

    public function ballotsAuditCsv(): void {
        // Flush any output buffer to prevent headers-already-sent issues
        if (ob_get_level() > 0) { ob_end_clean(); }

        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('ballots_audit', $mt['id'], 'csv');

        $rows = $this->repo()->ballot()->listAuditExportForMeeting($mt['id'], api_current_tenant_id());
        $export = new ExportService();

        $filename = $export->generateFilename('audit', $mt['title'] ?? '');
        $export->initCsvOutput($filename);
        $out = $export->openCsvOutput();
        $export->writeCsvRow($out, $export->getAuditHeaders());
        foreach ($rows as $r) {
            $export->writeCsvRow($out, $export->formatAuditRow($r));
        }
        fclose($out);
    }
}
