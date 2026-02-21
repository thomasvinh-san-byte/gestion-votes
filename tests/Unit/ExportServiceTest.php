<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\ExportService;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour ExportService.
 *
 * Toutes les méthodes sont des fonctions pures (pas de DB, pas d'IO)
 * sauf les méthodes d'output CSV/XLSX qui émettent des headers HTTP.
 */
class ExportServiceTest extends TestCase {
    private ExportService $export;

    protected function setUp(): void {
        $this->export = new ExportService();
    }

    // =========================================================================
    // TRANSLATIONS
    // =========================================================================

    public function testTranslateAttendanceMode(): void {
        $this->assertSame('Présent', $this->export->translateAttendanceMode('present'));
        $this->assertSame('À distance', $this->export->translateAttendanceMode('remote'));
        $this->assertSame('Représenté', $this->export->translateAttendanceMode('proxy'));
        $this->assertSame('Excusé', $this->export->translateAttendanceMode('excused'));
        $this->assertSame('Absent', $this->export->translateAttendanceMode('absent'));
        $this->assertSame('Non renseigné', $this->export->translateAttendanceMode(''));
        $this->assertSame('Non renseigné', $this->export->translateAttendanceMode(null));
    }

    public function testTranslateAttendanceModeCaseInsensitive(): void {
        $this->assertSame('Présent', $this->export->translateAttendanceMode('PRESENT'));
        $this->assertSame('À distance', $this->export->translateAttendanceMode('Remote'));
    }

    public function testTranslateAttendanceModeUnknownPassthrough(): void {
        $this->assertSame('unknown_mode', $this->export->translateAttendanceMode('unknown_mode'));
    }

    public function testTranslateDecision(): void {
        $this->assertSame('Adoptée', $this->export->translateDecision('adopted'));
        $this->assertSame('Rejetée', $this->export->translateDecision('rejected'));
        $this->assertSame('En attente', $this->export->translateDecision('pending'));
        $this->assertSame('Annulée', $this->export->translateDecision('cancelled'));
        $this->assertSame('Non décidée', $this->export->translateDecision(''));
        $this->assertSame('Non décidée', $this->export->translateDecision(null));
    }

    public function testTranslateVoteChoice(): void {
        $this->assertSame('Pour', $this->export->translateVoteChoice('for'));
        $this->assertSame('Contre', $this->export->translateVoteChoice('against'));
        $this->assertSame('Abstention', $this->export->translateVoteChoice('abstain'));
        $this->assertSame('Ne se prononce pas', $this->export->translateVoteChoice('nsp'));
        $this->assertSame('Blanc', $this->export->translateVoteChoice('blank'));
        $this->assertSame('Non exprimé', $this->export->translateVoteChoice(''));
    }

    public function testTranslateMeetingStatus(): void {
        $this->assertSame('Brouillon', $this->export->translateMeetingStatus('draft'));
        $this->assertSame('Programmée', $this->export->translateMeetingStatus('scheduled'));
        $this->assertSame('En cours', $this->export->translateMeetingStatus('live'));
        $this->assertSame('Validée', $this->export->translateMeetingStatus('validated'));
    }

    public function testTranslateVoteSource(): void {
        $this->assertSame('Électronique', $this->export->translateVoteSource('electronic'));
        $this->assertSame('Manuel', $this->export->translateVoteSource('manual'));
        $this->assertSame('Mode dégradé', $this->export->translateVoteSource('degraded'));
    }

    public function testTranslateBoolean(): void {
        $this->assertSame('Oui', $this->export->translateBoolean(true));
        $this->assertSame('Non', $this->export->translateBoolean(false));
        $this->assertSame('Oui', $this->export->translateBoolean('1'));
        $this->assertSame('Non', $this->export->translateBoolean('0'));
        $this->assertSame('Oui', $this->export->translateBoolean('t'));
        $this->assertSame('Non', $this->export->translateBoolean('f'));
    }

    // =========================================================================
    // DATE/TIME FORMATTING
    // =========================================================================

    public function testFormatDateWithTime(): void {
        $this->assertSame('15/01/2024 14:30', $this->export->formatDate('2024-01-15 14:30:00'));
    }

    public function testFormatDateWithoutTime(): void {
        $this->assertSame('15/01/2024', $this->export->formatDate('2024-01-15 14:30:00', false));
    }

    public function testFormatDateNull(): void {
        $this->assertSame('', $this->export->formatDate(null));
        $this->assertSame('', $this->export->formatDate(''));
    }

    public function testFormatDateInvalidFallback(): void {
        // Invalid date should return the original string
        $this->assertSame('not-a-date', $this->export->formatDate('not-a-date'));
    }

    public function testFormatTime(): void {
        $this->assertSame('14:30', $this->export->formatTime('2024-01-15 14:30:00'));
        $this->assertSame('', $this->export->formatTime(null));
        $this->assertSame('', $this->export->formatTime(''));
    }

    // =========================================================================
    // NUMBER FORMATTING
    // =========================================================================

    public function testFormatNumberInteger(): void {
        $this->assertSame('42', $this->export->formatNumber(42));
        $this->assertSame('0', $this->export->formatNumber(0));
        $this->assertSame('1 000', $this->export->formatNumber(1000));
    }

    public function testFormatNumberDecimal(): void {
        $this->assertSame('3,5', $this->export->formatNumber(3.5));
        $this->assertSame('1,25', $this->export->formatNumber(1.25));
    }

    public function testFormatNumberNull(): void {
        $this->assertSame('0', $this->export->formatNumber(null));
        $this->assertSame('0', $this->export->formatNumber(''));
    }

    public function testFormatPercent(): void {
        $this->assertSame('75 %', $this->export->formatPercent(0.75));
        $this->assertSame('100 %', $this->export->formatPercent(1.0));
        $this->assertSame('', $this->export->formatPercent(null));
    }

    // =========================================================================
    // FILENAME GENERATION
    // =========================================================================

    public function testGenerateFilename(): void {
        $filename = $this->export->generateFilename('attendance', '', 'csv');
        $this->assertMatchesRegularExpression('/^Emargement_\d{4}-\d{2}-\d{2}\.csv$/', $filename);
    }

    public function testGenerateFilenameWithTitle(): void {
        $filename = $this->export->generateFilename('votes', 'AG 2024', 'xlsx');
        $this->assertMatchesRegularExpression('/^Votes_.*_\d{4}-\d{2}-\d{2}\.xlsx$/', $filename);
    }

    public function testGenerateFilenameMappings(): void {
        $this->assertStringStartsWith('Emargement_', $this->export->generateFilename('presences'));
        $this->assertStringStartsWith('Votes_', $this->export->generateFilename('ballots'));
        $this->assertStringStartsWith('Membres_', $this->export->generateFilename('membres'));
        $this->assertStringStartsWith('Resolutions_', $this->export->generateFilename('resolutions'));
        $this->assertStringStartsWith('Resultats_', $this->export->generateFilename('resultats'));
        $this->assertStringStartsWith('Journal_audit_', $this->export->generateFilename('audit'));
        $this->assertStringStartsWith('Procurations_', $this->export->generateFilename('procurations'));
        $this->assertStringStartsWith('Export_complet_', $this->export->generateFilename('complet'));
    }

    public function testSanitizeFilename(): void {
        $this->assertMatchesRegularExpression('/^[a-z0-9_\-]+$/', $this->export->sanitizeFilename('AG Extraordinaire 2024!'));
        // Length limit
        $long = str_repeat('a', 100);
        $this->assertLessThanOrEqual(50, strlen($this->export->sanitizeFilename($long)));
    }

    // =========================================================================
    // ROW FORMATTING
    // =========================================================================

    public function testFormatAttendanceRow(): void {
        $row = [
            'full_name' => 'Jean Dupont',
            'voting_power' => 2,
            'attendance_mode' => 'present',
            'checked_in_at' => '2024-01-15 09:00:00',
            'checked_out_at' => null,
            'proxy_to_name' => '',
            'proxies_received' => 1,
        ];
        $result = $this->export->formatAttendanceRow($row);

        $this->assertCount(7, $result);
        $this->assertSame('Jean Dupont', $result[0]);
        $this->assertSame('2', $result[1]); // voting_power formatted
        $this->assertSame('Présent', $result[2]); // translated mode
        $this->assertSame('15/01/2024 09:00', $result[3]); // formatted date
    }

    public function testFormatVoteRow(): void {
        $row = [
            'motion_title' => 'Résolution 1',
            'motion_position' => 1,
            'voter_name' => 'Marie Martin',
            'value' => 'for',
            'weight' => 1,
            'is_proxy_vote' => false,
            'proxy_source_name' => '',
            'cast_at' => '2024-01-15 10:30:00',
            'source' => 'electronic',
        ];
        $result = $this->export->formatVoteRow($row);

        $this->assertCount(9, $result);
        $this->assertSame('Résolution 1', $result[0]);
        $this->assertSame('Marie Martin', $result[2]);
        $this->assertSame('Pour', $result[3]); // translated choice
        $this->assertSame('Électronique', $result[8]); // translated source
    }

    public function testFormatMemberRow(): void {
        $row = [
            'full_name' => 'Pierre Durand',
            'email' => 'pierre@test.com',
            'voting_power' => 1,
            'is_active' => true,
            'attendance_mode' => 'remote',
            'checked_in_at' => '2024-01-15 08:45:00',
            'checked_out_at' => null,
            'proxy_to_name' => '',
        ];
        $result = $this->export->formatMemberRow($row);

        $this->assertCount(8, $result);
        $this->assertSame('Pierre Durand', $result[0]);
        $this->assertSame('pierre@test.com', $result[1]);
        $this->assertSame('Oui', $result[3]); // is_active translated
        $this->assertSame('À distance', $result[4]); // translated mode
    }

    public function testFormatMotionResultRow(): void {
        $row = [
            'position' => 1,
            'title' => 'Budget 2024',
            'opened_at' => '2024-01-15 10:00:00',
            'closed_at' => '2024-01-15 10:15:00',
            'w_for' => 15,
            'w_against' => 3,
            'w_abstain' => 2,
            'w_nsp' => 0,
            'w_total' => 20,
            'ballots_count' => 20,
            'decision' => 'adopted',
            'decision_reason' => 'Majorité simple',
        ];
        $result = $this->export->formatMotionResultRow($row);

        $this->assertCount(12, $result);
        $this->assertSame(1, $result[0]); // position
        $this->assertSame('Budget 2024', $result[1]);
        $this->assertSame('Adoptée', $result[10]); // translated decision
    }

    public function testFormatProxyRow(): void {
        $row = [
            'grantor_name' => 'Alice',
            'grantee_name' => 'Bob',
            'grantor_voting_power' => 1.5,
            'created_at' => '2024-01-15 08:00:00',
            'is_active' => true,
        ];
        $result = $this->export->formatProxyRow($row);

        $this->assertCount(5, $result);
        $this->assertSame('Alice', $result[0]);
        $this->assertSame('Bob', $result[1]);
        $this->assertSame('Oui', $result[4]); // is_active
    }

    // =========================================================================
    // HEADERS
    // =========================================================================

    public function testHeadersReturnNonEmptyArrays(): void {
        $this->assertNotEmpty($this->export->getAttendanceHeaders());
        $this->assertNotEmpty($this->export->getVotesHeaders());
        $this->assertNotEmpty($this->export->getMembersHeaders());
        $this->assertNotEmpty($this->export->getMotionResultsHeaders());
        $this->assertNotEmpty($this->export->getProxiesHeaders());
    }

    public function testAttendanceHeadersMatchRowLength(): void {
        $headers = $this->export->getAttendanceHeaders();
        $row = $this->export->formatAttendanceRow([
            'full_name' => 'Test',
            'voting_power' => 1,
            'attendance_mode' => 'present',
        ]);
        $this->assertCount(count($headers), $row);
    }

    public function testVotesHeadersMatchRowLength(): void {
        $headers = $this->export->getVotesHeaders();
        $row = $this->export->formatVoteRow([
            'motion_title' => 'Test',
            'voter_name' => 'Test',
            'value' => 'for',
        ]);
        $this->assertCount(count($headers), $row);
    }

    public function testMembersHeadersMatchRowLength(): void {
        $headers = $this->export->getMembersHeaders();
        $row = $this->export->formatMemberRow(['full_name' => 'Test']);
        $this->assertCount(count($headers), $row);
    }

    public function testMotionResultsHeadersMatchRowLength(): void {
        $headers = $this->export->getMotionResultsHeaders();
        $row = $this->export->formatMotionResultRow(['title' => 'Test']);
        $this->assertCount(count($headers), $row);
    }

    public function testProxiesHeadersMatchRowLength(): void {
        $headers = $this->export->getProxiesHeaders();
        $row = $this->export->formatProxyRow(['grantor_name' => 'A', 'grantee_name' => 'B']);
        $this->assertCount(count($headers), $row);
    }
}
