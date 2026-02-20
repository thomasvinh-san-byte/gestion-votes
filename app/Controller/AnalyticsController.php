<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MemberRepository;
use AgVote\Repository\AnalyticsRepository;
use AgVote\Repository\AggregateReportRepository;
use AgVote\Service\ExportService;

class AnalyticsController extends AbstractController
{
    public function analytics(): void
    {
        api_require_role('operator');
        api_request('GET');

        $tenantId = api_current_tenant_id();
        $type = trim($_GET['type'] ?? 'overview');
        $period = trim($_GET['period'] ?? 'year');
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

        $memberRepo = new MemberRepository();
        $analyticsRepo = new AnalyticsRepository();

        $dateFrom = match($period) {
            'month' => date('Y-m-d', strtotime('-1 month')),
            'quarter' => date('Y-m-d', strtotime('-3 months')),
            'year' => date('Y-m-d', strtotime('-1 year')),
            'all' => '2000-01-01',
            default => date('Y-m-d', strtotime('-1 year')),
        };

        try {
            $data = match($type) {
                'overview' => self::getOverview($tenantId, $memberRepo, $analyticsRepo),
                'participation' => self::getParticipation($tenantId, $dateFrom, $memberRepo, $analyticsRepo, $limit),
                'motions' => self::getMotionsStats($tenantId, $dateFrom, $analyticsRepo, $limit),
                'vote_duration' => self::getVoteDuration($tenantId, $dateFrom, $analyticsRepo, $limit),
                'proxies' => self::getProxiesStats($tenantId, $dateFrom, $analyticsRepo, $limit),
                'anomalies' => self::getAnomalies($tenantId, $dateFrom, $analyticsRepo, $limit),
                'vote_timing' => self::getVoteTimingDistribution($tenantId, $dateFrom, $analyticsRepo),
                default => api_fail('invalid_type', 400),
            };

            api_ok($data);
        } catch (\Throwable $e) {
            error_log('Error in AnalyticsController::analytics: ' . $e->getMessage());
            api_fail('server_error', 500, ['detail' => $e->getMessage()]);
        }
    }

    public function reportsAggregate(): void
    {
        api_require_role(['operator', 'admin', 'auditor']);

        $repo = new AggregateReportRepository();
        $tenantId = api_current_tenant_id();

        // Liste des séances disponibles
        if (isset($_GET['list_meetings'])) {
            $fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : null;
            $toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : null;
            $meetings = $repo->listAvailableMeetings($tenantId, $fromDate, $toDate);
            api_ok(['meetings' => $meetings]);
        }

        $reportType = trim($_GET['report_type'] ?? 'summary');
        $format = strtolower(trim($_GET['format'] ?? 'json'));
        $fromDate = isset($_GET['from_date']) && $_GET['from_date'] !== '' ? trim($_GET['from_date']) : null;
        $toDate = isset($_GET['to_date']) && $_GET['to_date'] !== '' ? trim($_GET['to_date']) : null;
        $meetingIds = $_GET['meeting_ids'] ?? [];

        if (!empty($meetingIds) && is_array($meetingIds)) {
            $meetingIds = array_filter($meetingIds, fn($id) => api_is_uuid($id));
            if (empty($meetingIds)) $meetingIds = null;
        } else {
            $meetingIds = null;
        }

        $validTypes = ['participation', 'decisions', 'voting_power', 'proxies', 'quorum', 'summary'];
        if (!in_array($reportType, $validTypes, true)) {
            api_fail('invalid_report_type', 400, ['detail' => 'Type de rapport invalide.', 'valid_types' => $validTypes]);
        }

        $validFormats = ['json', 'csv', 'xlsx'];
        if (!in_array($format, $validFormats, true)) {
            api_fail('invalid_format', 400, ['detail' => 'Format invalide.', 'valid_formats' => $validFormats]);
        }

        $data = match ($reportType) {
            'participation' => $repo->getParticipationReport($tenantId, $meetingIds, $fromDate, $toDate),
            'decisions' => $repo->getDecisionsReport($tenantId, $meetingIds, $fromDate, $toDate),
            'voting_power' => $repo->getVotingPowerReport($tenantId, $meetingIds, $fromDate, $toDate),
            'proxies' => $repo->getProxiesReport($tenantId, $meetingIds, $fromDate, $toDate),
            'quorum' => $repo->getQuorumReport($tenantId, $meetingIds, $fromDate, $toDate),
            'summary' => $repo->getSummary($tenantId, $meetingIds, $fromDate, $toDate),
            default => [],
        };

        audit_log('report_aggregate_view', 'report', null, [
            'report_type' => $reportType,
            'format' => $format,
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'meeting_count' => $meetingIds ? count($meetingIds) : 'all',
        ]);

        if ($format === 'json') {
            api_ok([
                'report_type' => $reportType,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'meeting_count' => $meetingIds ? count($meetingIds) : null,
                'generated_at' => date('c'),
                'data' => $data,
            ]);
        }

        $exportService = new ExportService();
        $headers = self::getReportHeaders($reportType);
        $rows = self::formatReportRows($reportType, $data, $exportService);
        $filename = "rapport_{$reportType}_" . date('Y-m-d');

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            $output = fopen('php://output', 'w');
            $exportService->writeCsvBom($output);
            $exportService->writeCsvRow($output, $headers);
            foreach ($rows as $row) {
                $exportService->writeCsvRow($output, $row);
            }
            fclose($output);
            exit;
        }

        if ($format === 'xlsx') {
            $spreadsheet = $exportService->createSpreadsheet("Rapport {$reportType}");
            $sheet = $spreadsheet->getActiveSheet();
            foreach ($headers as $col => $header) {
                $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            }
            $rowNum = 2;
            foreach ($rows as $row) {
                foreach ($row as $col => $value) {
                    $sheet->setCellValueByColumnAndRow($col + 1, $rowNum, $value);
                }
                $rowNum++;
            }
            $exportService->autoSizeColumns($sheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        }
    }

    // =========================================================================
    // Analytics helper methods (from analytics.php inline functions)
    // =========================================================================

    private static function getOverview(string $tenantId, MemberRepository $memberRepo, AnalyticsRepository $analyticsRepo): array
    {
        $totalMeetings = $analyticsRepo->countMeetings($tenantId);
        $totalMembers = $memberRepo->countActiveNotDeleted($tenantId);
        $totalMotions = $analyticsRepo->countMotions($tenantId);
        $totalBallots = $analyticsRepo->countBallots($tenantId);

        $statusRows = $analyticsRepo->getMeetingsByStatus($tenantId);
        $meetingsByStatus = [];
        foreach ($statusRows as $row) {
            $meetingsByStatus[$row['status']] = (int)$row['count'];
        }

        $decisionRows = $analyticsRepo->getMotionDecisions($tenantId);
        $motionDecisions = [];
        foreach ($decisionRows as $row) {
            $motionDecisions[$row['decision']] = (int)$row['count'];
        }

        $avgParticipation = $analyticsRepo->getAverageParticipationRate($tenantId);

        return [
            'totals' => ['meetings' => $totalMeetings, 'members' => $totalMembers, 'motions' => $totalMotions, 'ballots' => $totalBallots],
            'meetings_by_status' => $meetingsByStatus,
            'motion_decisions' => $motionDecisions,
            'avg_participation_rate' => $avgParticipation,
        ];
    }

    private static function getParticipation(string $tenantId, string $dateFrom, MemberRepository $memberRepo, AnalyticsRepository $analyticsRepo, int $limit): array
    {
        $meetings = $analyticsRepo->getParticipationByMeeting($tenantId, $dateFrom, $limit);
        $eligibleCount = $memberRepo->countActiveNotDeleted($tenantId);

        $participation = [];
        foreach ($meetings as $m) {
            $rate = $eligibleCount > 0
                ? round(((int)$m['present_count'] + (int)$m['proxy_count']) / $eligibleCount * 100, 1)
                : 0;
            $participation[] = [
                'meeting_id' => $m['id'], 'title' => $m['title'], 'date' => $m['started_at'],
                'present' => (int)$m['present_count'], 'proxy' => (int)$m['proxy_count'],
                'total' => (int)$m['total_attendees'], 'eligible' => $eligibleCount, 'rate' => $rate,
            ];
        }

        return ['eligible_count' => $eligibleCount, 'meetings' => array_reverse($participation)];
    }

    private static function getMotionsStats(string $tenantId, string $dateFrom, AnalyticsRepository $analyticsRepo, int $limit): array
    {
        $byMeeting = $analyticsRepo->getMotionsStatsByMeeting($tenantId, $dateFrom, $limit);
        $summary = $analyticsRepo->getMotionsTotals($tenantId, $dateFrom);

        return [
            'summary' => [
                'total' => (int)($summary['total'] ?? 0),
                'adopted' => (int)($summary['adopted'] ?? 0),
                'rejected' => (int)($summary['rejected'] ?? 0),
                'adoption_rate' => (int)($summary['total'] ?? 0) > 0
                    ? round((int)($summary['adopted'] ?? 0) / (int)$summary['total'] * 100, 1)
                    : 0,
            ],
            'by_meeting' => array_reverse($byMeeting),
        ];
    }

    private static function getVoteDuration(string $tenantId, string $dateFrom, AnalyticsRepository $analyticsRepo, int $limit): array
    {
        $motions = $analyticsRepo->getVoteDurations($tenantId, $dateFrom, $limit);

        $durations = [];
        $totalSeconds = 0;
        foreach ($motions as $mo) {
            $seconds = (float)$mo['duration_seconds'];
            $totalSeconds += $seconds;
            $durations[] = [
                'motion_id' => $mo['id'], 'title' => $mo['title'], 'meeting_title' => $mo['meeting_title'],
                'opened_at' => $mo['opened_at'], 'closed_at' => $mo['closed_at'],
                'duration_seconds' => round($seconds), 'duration_formatted' => self::formatDuration($seconds),
            ];
        }

        $count = count($durations);
        $avgSeconds = $count > 0 ? $totalSeconds / $count : 0;

        $distribution = ['0-30s' => 0, '30s-1m' => 0, '1-2m' => 0, '2-5m' => 0, '5-10m' => 0, '10m+' => 0];
        foreach ($motions as $mo) {
            $s = (float)$mo['duration_seconds'];
            if ($s < 30) $distribution['0-30s']++;
            elseif ($s < 60) $distribution['30s-1m']++;
            elseif ($s < 120) $distribution['1-2m']++;
            elseif ($s < 300) $distribution['2-5m']++;
            elseif ($s < 600) $distribution['5-10m']++;
            else $distribution['10m+']++;
        }

        return [
            'count' => $count, 'avg_seconds' => round($avgSeconds),
            'avg_formatted' => self::formatDuration($avgSeconds),
            'distribution' => $distribution, 'motions' => array_reverse($durations),
        ];
    }

    private static function getProxiesStats(string $tenantId, string $dateFrom, AnalyticsRepository $analyticsRepo, int $limit): array
    {
        $byMeeting = $analyticsRepo->getProxiesStatsByMeeting($tenantId, $dateFrom, $limit);
        $totalCount = $analyticsRepo->countProxies($tenantId, $dateFrom);

        return ['total_proxies' => $totalCount, 'by_meeting' => array_reverse($byMeeting)];
    }

    private static function getAnomalies(string $tenantId, string $dateFrom, AnalyticsRepository $analyticsRepo, int $limit): array
    {
        $lowParticipationCount = $analyticsRepo->countLowParticipationMeetings($tenantId, $dateFrom);
        $quorumIssuesCount = $analyticsRepo->countQuorumIssues($tenantId, $dateFrom);
        $incompleteVotesCount = $analyticsRepo->countIncompleteVotes($tenantId, $dateFrom);
        $highProxyConcentrationCount = $analyticsRepo->countHighProxyConcentration($tenantId, $dateFrom);
        $abstentionRateValue = $analyticsRepo->getAbstentionRate($tenantId, $dateFrom);
        $veryShortVotesCount = $analyticsRepo->countVeryShortVotes($tenantId, $dateFrom);

        $meetings = $analyticsRepo->getFlaggedMeetings($tenantId, $dateFrom, $limit);

        $flaggedList = [];
        foreach ($meetings as $m) {
            $flags = [];
            $eligible = (int)$m['eligible'];
            $attended = (int)$m['attended'];
            $rate = $eligible > 0 ? round($attended / $eligible * 100, 1) : 0;

            if ($rate < 50 && $eligible > 0) $flags[] = 'Participation faible';
            if ((int)$m['quorum_issues'] > 0) $flags[] = 'Quorum';
            if ((int)$m['incomplete'] > 0) $flags[] = 'Votes incomplets';

            if (count($flags) > 0) {
                $flaggedList[] = [
                    'meeting_id' => $m['id'], 'title' => $m['title'], 'date' => $m['date'],
                    'participation_rate' => $rate, 'flags' => $flags,
                ];
            }
        }

        return [
            'indicators' => [
                'low_participation_count' => $lowParticipationCount,
                'quorum_issues_count' => $quorumIssuesCount,
                'incomplete_votes_count' => $incompleteVotesCount,
                'high_proxy_concentration' => $highProxyConcentrationCount,
                'abstention_rate' => $abstentionRateValue,
                'very_short_votes_count' => $veryShortVotesCount,
            ],
            'flagged_meetings' => $flaggedList,
        ];
    }

    private static function getVoteTimingDistribution(string $tenantId, string $dateFrom, AnalyticsRepository $analyticsRepo): array
    {
        $timesRows = $analyticsRepo->getVoteTimingDistribution($tenantId, $dateFrom);

        $distribution = ['0-10s' => 0, '10-30s' => 0, '30s-1m' => 0, '1-2m' => 0, '2-5m' => 0, '5m+' => 0];
        $totalSeconds = 0;
        $count = 0;
        foreach ($timesRows as $row) {
            $s = (float)$row['response_seconds'];
            $totalSeconds += $s;
            $count++;
            if ($s < 10) $distribution['0-10s']++;
            elseif ($s < 30) $distribution['10-30s']++;
            elseif ($s < 60) $distribution['30s-1m']++;
            elseif ($s < 120) $distribution['1-2m']++;
            elseif ($s < 300) $distribution['2-5m']++;
            else $distribution['5m+']++;
        }

        $avgSeconds = $count > 0 ? $totalSeconds / $count : 0;

        return [
            'count' => $count, 'avg_seconds' => round($avgSeconds, 1),
            'avg_formatted' => self::formatDuration($avgSeconds), 'distribution' => $distribution,
        ];
    }

    private static function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds) . 's';
        } elseif ($seconds < 3600) {
            $m = floor($seconds / 60);
            $s = round($seconds % 60);
            return $m . 'm' . ($s > 0 ? ' ' . $s . 's' : '');
        } else {
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);
            return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
        }
    }

    // =========================================================================
    // Aggregate report helper methods (from reports_aggregate.php inline functions)
    // =========================================================================

    private static function getReportHeaders(string $type): array
    {
        return match ($type) {
            'participation' => ['Nom', 'Email', 'Pouvoir de vote', 'Séances totales', 'Présent(e)', 'Par procuration', 'Excusé(e)', 'Absent(e)', 'Taux de participation (%)'],
            'decisions' => ['Séance', 'Date', 'N° résolution', 'Titre', 'Décision', 'Pour', 'Contre', 'Abstention', 'Votants', 'Poids Pour', 'Poids Contre', 'Poids Abstention'],
            'voting_power' => ['Séance', 'Date', 'Présents', 'Pouvoir représenté', 'Pouvoir total', 'Représentation (%)'],
            'proxies' => ['Séance', 'Date', 'Nb procurations', 'Mandants uniques', 'Mandataires uniques', 'Pouvoir délégué', 'Pouvoir total', 'Délégation (%)', 'Max par mandataire'],
            'quorum' => ['Séance', 'Date', 'Statut', 'Membres totaux', 'Présents', 'Pouvoir présent', 'Seuil quorum', 'Unité', 'Quorum atteint'],
            'summary' => ['Métrique', 'Valeur'],
            default => [],
        };
    }

    private static function formatReportRows(string $type, array $data, ExportService $svc): array
    {
        $rows = [];

        if ($type === 'summary') {
            $rows[] = ['Nombre de séances', $data['total_meetings'] ?? 0];
            $rows[] = ['Nombre de résolutions', $data['total_motions'] ?? 0];
            $rows[] = ['Résolutions adoptées', $data['adopted_count'] ?? 0];
            $rows[] = ['Résolutions rejetées', $data['rejected_count'] ?? 0];
            $rows[] = ['Autres décisions', $data['other_count'] ?? 0];
            $rows[] = ['Présence moyenne', $data['avg_attendance'] ?? 0];
            $rows[] = ['Première séance', $svc->formatDate($data['first_meeting'] ?? null)];
            $rows[] = ['Dernière séance', $svc->formatDate($data['last_meeting'] ?? null)];
            return $rows;
        }

        foreach ($data as $row) {
            $formattedRow = match ($type) {
                'participation' => [
                    $row['full_name'] ?? '', $row['email'] ?? '', $svc->formatNumber($row['voting_power'] ?? 1),
                    $row['total_meetings'] ?? 0, $row['attended_present'] ?? 0, $row['attended_proxy'] ?? 0,
                    $row['excused'] ?? 0, $row['absent'] ?? 0, $svc->formatPercent($row['participation_rate'] ?? 0),
                ],
                'decisions' => [
                    $row['meeting_title'] ?? '', $svc->formatDate($row['scheduled_at'] ?? null),
                    $row['position'] ?? '', $row['motion_title'] ?? '', $svc->translateDecision($row['decision'] ?? null),
                    $row['for_count'] ?? 0, $row['against_count'] ?? 0, $row['abstain_count'] ?? 0, $row['total_voters'] ?? 0,
                    $svc->formatNumber($row['for_weight'] ?? 0), $svc->formatNumber($row['against_weight'] ?? 0),
                    $svc->formatNumber($row['abstain_weight'] ?? 0),
                ],
                'voting_power' => [
                    $row['meeting_title'] ?? '', $svc->formatDate($row['scheduled_at'] ?? null),
                    $row['present_count'] ?? 0, $svc->formatNumber($row['present_power'] ?? 0),
                    $svc->formatNumber($row['total_power'] ?? 0), $svc->formatPercent($row['power_represented_pct'] ?? 0),
                ],
                'proxies' => [
                    $row['meeting_title'] ?? '', $svc->formatDate($row['scheduled_at'] ?? null),
                    $row['proxy_count'] ?? 0, $row['unique_givers'] ?? 0, $row['unique_receivers'] ?? 0,
                    $svc->formatNumber($row['delegated_power'] ?? 0), $svc->formatNumber($row['total_power'] ?? 0),
                    $svc->formatPercent($row['delegated_pct'] ?? 0), $row['max_proxies_per_receiver'] ?? 0,
                ],
                'quorum' => [
                    $row['meeting_title'] ?? '', $svc->formatDate($row['scheduled_at'] ?? null),
                    $svc->translateMeetingStatus($row['meeting_status'] ?? ''), $row['total_members'] ?? 0,
                    $row['present_count'] ?? 0, $svc->formatNumber($row['present_power'] ?? 0),
                    $row['quorum_threshold'] ?? '-', $row['quorum_unit'] ?? '-',
                    $svc->formatBoolean($row['quorum_reached'] ?? true),
                ],
                default => [],
            };
            $rows[] = $formattedRow;
        }

        return $rows;
    }
}
