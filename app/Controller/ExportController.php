<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
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
final class ExportController extends AbstractController
{
    private function requireMeetingId(): string
    {
        $id = trim((string)($_GET['meeting_id'] ?? ''));
        if ($id === '' || !api_is_uuid($id)) {
            api_fail('missing_meeting_id', 400);
        }
        return $id;
    }

    private function requireValidatedMeeting(string $meetingId): array
    {
        $mt = (new MeetingRepository())->findByIdForTenant($meetingId, api_current_tenant_id());
        if (!$mt) {
            api_fail('meeting_not_found', 404);
        }
        if (empty($mt['validated_at'])) {
            api_fail('meeting_not_validated', 409);
        }
        return $mt;
    }

    private function auditExport(string $type, string $meetingId, string $format = 'csv'): void
    {
        audit_log('export.' . $type, 'meeting', $meetingId, [
            'format' => $format,
            'exported_by' => api_current_user_id(),
        ], $meetingId);
    }

    // ------------------------------------------------------------------
    // Attendance
    // ------------------------------------------------------------------

    public function attendanceCsv(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('attendance', $mt['id'], 'csv');

        $rows = (new AttendanceRepository())->listExportForMeeting($mt['id']);
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

    public function attendanceXlsx(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('attendance', $mt['id'], 'xlsx');

        $rows = (new AttendanceRepository())->listExportForMeeting($mt['id']);
        $export = new ExportService();

        $formattedRows = array_map([$export, 'formatAttendanceRow'], $rows);
        $filename = $export->generateFilename('presences', $mt['title'] ?? '', 'xlsx');
        $export->initXlsxOutput($filename);
        $spreadsheet = $export->createSpreadsheet($export->getAttendanceHeaders(), $formattedRows, 'Émargement');
        $export->outputSpreadsheet($spreadsheet);
    }

    // ------------------------------------------------------------------
    // Votes
    // ------------------------------------------------------------------

    public function votesCsv(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('votes', $mt['id'], 'csv');

        $rows = (new BallotRepository())->listVotesExportForMeeting($mt['id']);
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

    public function votesXlsx(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('votes', $mt['id'], 'xlsx');

        $rows = (new BallotRepository())->listVotesExportForMeeting($mt['id']);
        $export = new ExportService();

        $formattedRows = [];
        foreach ($rows as $r) {
            if (!empty($r['voter_name'])) {
                $formattedRows[] = $export->formatVoteRow($r);
            }
        }

        $filename = $export->generateFilename('votes', $mt['title'] ?? '', 'xlsx');
        $export->initXlsxOutput($filename);
        $spreadsheet = $export->createSpreadsheet($export->getVotesHeaders(), $formattedRows, 'Votes');
        $export->outputSpreadsheet($spreadsheet);
    }

    // ------------------------------------------------------------------
    // Members
    // ------------------------------------------------------------------

    public function membersCsv(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('members', $mt['id'], 'csv');

        $rows = (new MemberRepository())->listExportForMeeting($mt['id'], api_current_tenant_id());
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

    public function motionResultsCsv(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('motion_results', $mt['id'], 'csv');

        $rows = (new MotionRepository())->listResultsExportForMeeting($mt['id']);
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

    public function resultsXlsx(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('motion_results', $mt['id'], 'xlsx');

        $rows = (new MotionRepository())->listResultsExportForMeeting($mt['id']);
        $export = new ExportService();

        $formattedRows = array_map([$export, 'formatMotionResultRow'], $rows);
        $filename = $export->generateFilename('resultats', $mt['title'] ?? '', 'xlsx');
        $export->initXlsxOutput($filename);
        $spreadsheet = $export->createSpreadsheet($export->getMotionResultsHeaders(), $formattedRows, 'Résultats');
        $export->outputSpreadsheet($spreadsheet);
    }

    // ------------------------------------------------------------------
    // Full multi-sheet export
    // ------------------------------------------------------------------

    public function fullXlsx(): void
    {
        $meetingId = $this->requireMeetingId();
        $mt = $this->requireValidatedMeeting($meetingId);
        $this->auditExport('full', $meetingId, 'xlsx');
        $includeVotes = (bool)($_GET['include_votes'] ?? true);

        $attendanceRows = (new AttendanceRepository())->listExportForMeeting($meetingId);
        $motionRows = (new MotionRepository())->listResultsExportForMeeting($meetingId);
        $voteRows = $includeVotes ? (new BallotRepository())->listVotesExportForMeeting($meetingId) : [];

        $export = new ExportService();
        $filename = $export->generateFilename('complet', $mt['title'] ?? '', 'xlsx');
        $export->initXlsxOutput($filename);
        $spreadsheet = $export->createFullExportSpreadsheet($mt, $attendanceRows, $motionRows, $voteRows);
        $export->outputSpreadsheet($spreadsheet);
    }

    // ------------------------------------------------------------------
    // Ballots audit
    // ------------------------------------------------------------------

    public function ballotsAuditCsv(): void
    {
        $mt = $this->requireValidatedMeeting($this->requireMeetingId());
        $this->auditExport('ballots_audit', $mt['id'], 'csv');

        $rows = (new BallotRepository())->listAuditExportForMeeting($mt['id']);
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
