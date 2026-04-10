<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\BallotSource;
use DateTime;
use Throwable;

/**
 * ValueTranslator - Translation, formatting, row formatting, and header definitions for exports.
 *
 * Extracted from ExportService to keep each class under 300 LOC.
 * Handles French labels, date formatting, number formatting, and row preparation
 * for attendance, votes, members, motions, proxies, and audit exports.
 */
final class ValueTranslator {
    // ========================================================================
    // VALUE TRANSLATION CONSTANTS
    // ========================================================================

    /** Attendance modes */
    public const ATTENDANCE_MODES = [
        'present' => 'Présent',
        'remote' => 'À distance',
        'proxy' => 'Représenté',
        'excused' => 'Excusé',
        'absent' => 'Absent',
        '' => 'Non renseigné',
    ];

    /** Vote decisions */
    public const DECISIONS = [
        'adopted' => 'Adoptée',
        'rejected' => 'Rejetée',
        'pending' => 'En attente',
        'cancelled' => 'Annulée',
        '' => 'Non décidée',
    ];

    /** Vote choices */
    public const VOTE_CHOICES = [
        'for' => 'Pour',
        'against' => 'Contre',
        'abstain' => 'Abstention',
        'nsp' => 'Ne se prononce pas',
        'blank' => 'Blanc',
        '' => 'Non exprimé',
    ];

    /** Meeting statuses */
    public const MEETING_STATUSES = [
        'draft' => 'Brouillon',
        'scheduled' => 'Programmée',
        'frozen' => 'Figée',
        'live' => 'En cours',
        'closed' => 'Clôturée',
        'validated' => 'Validée',
        'archived' => 'Archivée',
    ];

    /** Vote sources — delegates to BallotSource::LABELS for consistency. */
    public const VOTE_SOURCES = BallotSource::LABELS;

    /** Booleans */
    public const BOOLEANS = [
        '1' => 'Oui',
        '0' => 'Non',
        't' => 'Oui',
        'f' => 'Non',
    ];

    // ========================================================================
    // TRANSLATE METHODS
    // ========================================================================

    public function translateAttendanceMode(?string $mode): string {
        $mode = strtolower(trim((string) $mode));
        return self::ATTENDANCE_MODES[$mode] ?? $mode;
    }

    public function translateDecision(?string $decision): string {
        $decision = strtolower(trim((string) $decision));
        return self::DECISIONS[$decision] ?? $decision;
    }

    public function translateVoteChoice(?string $choice): string {
        $choice = strtolower(trim((string) $choice));
        return self::VOTE_CHOICES[$choice] ?? $choice;
    }

    public function translateMeetingStatus(?string $status): string {
        $status = strtolower(trim((string) $status));
        return self::MEETING_STATUSES[$status] ?? $status;
    }

    public function translateVoteSource(?string $source): string {
        $source = strtolower(trim((string) $source));
        return self::VOTE_SOURCES[$source] ?? $source;
    }

    public function translateBoolean($value): string {
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        $strVal = strtolower(trim((string) $value));
        return self::BOOLEANS[$strVal] ?? $strVal;
    }

    // ========================================================================
    // FORMAT METHODS
    // ========================================================================

    public function formatDate(?string $datetime, bool $includeTime = true): string {
        if ($datetime === null || $datetime === '') {
            return '';
        }
        try {
            $dt = new DateTime($datetime);
            return $includeTime ? $dt->format('d/m/Y H:i') : $dt->format('d/m/Y');
        } catch (Throwable $e) {
            return (string) $datetime;
        }
    }

    public function formatTime(?string $datetime): string {
        if ($datetime === null || $datetime === '') {
            return '';
        }
        try {
            $dt = new DateTime($datetime);
            return $dt->format('H:i');
        } catch (Throwable $e) {
            return '';
        }
    }

    public function formatNumber($value, int $decimals = 2): string {
        if ($value === null || $value === '') {
            return '0';
        }
        $num = (float) $value;
        if (abs($num - round($num)) < 0.000001) {
            return number_format((int) round($num), 0, ',', ' ');
        }
        $formatted = number_format($num, $decimals, ',', ' ');
        return rtrim(rtrim($formatted, '0'), ',');
    }

    public function formatPercent($value): string {
        if ($value === null || $value === '') {
            return '';
        }
        return $this->formatNumber((float) $value * 100, 1) . ' %';
    }

    // ========================================================================
    // ROW FORMATTERS
    // ========================================================================

    public function formatAttendanceRow(array $row): array {
        return [
            (string) ($row['full_name'] ?? ''),
            $this->formatNumber($row['voting_power'] ?? 1),
            $this->translateAttendanceMode($row['attendance_mode'] ?? $row['mode'] ?? ''),
            $this->formatDate($row['checked_in_at'] ?? null),
            $this->formatDate($row['checked_out_at'] ?? null),
            (string) ($row['proxy_to_name'] ?? ''),
            $this->formatNumber($row['proxies_received'] ?? 0, 0),
        ];
    }

    public function formatVoteRow(array $row): array {
        return [
            (string) ($row['motion_title'] ?? ''),
            (int) ($row['motion_position'] ?? $row['position'] ?? 0),
            (string) ($row['voter_name'] ?? ''),
            $this->translateVoteChoice($row['value'] ?? ''),
            $this->formatNumber($row['weight'] ?? 1),
            $this->translateBoolean($row['is_proxy_vote'] ?? false),
            (string) ($row['proxy_source_name'] ?? ''),
            $this->formatDate($row['cast_at'] ?? null),
            $this->translateVoteSource($row['source'] ?? ''),
        ];
    }

    public function formatMemberRow(array $row): array {
        return [
            (string) ($row['full_name'] ?? ''),
            (string) ($row['email'] ?? ''),
            $this->formatNumber($row['voting_power'] ?? 1),
            $this->translateBoolean($row['is_active'] ?? true),
            $this->translateAttendanceMode($row['attendance_mode'] ?? ''),
            $this->formatDate($row['checked_in_at'] ?? null),
            $this->formatDate($row['checked_out_at'] ?? null),
            (string) ($row['proxy_to_name'] ?? ''),
        ];
    }

    public function formatMotionResultRow(array $row): array {
        return [
            (int) ($row['position'] ?? 0),
            (string) ($row['title'] ?? ''),
            $this->formatDate($row['opened_at'] ?? null),
            $this->formatDate($row['closed_at'] ?? null),
            $this->formatNumber($row['w_for'] ?? $row['official_for'] ?? 0),
            $this->formatNumber($row['w_against'] ?? $row['official_against'] ?? 0),
            $this->formatNumber($row['w_abstain'] ?? $row['official_abstain'] ?? 0),
            $this->formatNumber($row['w_nsp'] ?? 0),
            $this->formatNumber($row['w_total'] ?? $row['official_total'] ?? 0),
            (int) ($row['ballots_count'] ?? 0),
            $this->translateDecision($row['decision'] ?? ''),
            (string) ($row['decision_reason'] ?? ''),
        ];
    }

    public function formatProxyRow(array $row): array {
        return [
            (string) ($row['grantor_name'] ?? ''),
            (string) ($row['grantee_name'] ?? ''),
            $this->formatNumber($row['grantor_voting_power'] ?? 1),
            $this->formatDate($row['created_at'] ?? null),
            $this->translateBoolean($row['is_active'] ?? true),
        ];
    }

    public function formatAuditRow(array $r): array {
        return [
            (string) ($r['ballot_id'] ?? ''),
            (string) ($r['motion_id'] ?? ''),
            (string) ($r['motion_title'] ?? ''),
            (string) ($r['member_id'] ?? ''),
            (string) ($r['voter_name'] ?? ''),
            $this->translateAttendanceMode($r['attendance_mode'] ?? ''),
            $this->translateVoteChoice($r['value'] ?? ''),
            (string) ($r['weight'] ?? ''),
            $this->translateBoolean($r['is_proxy_vote'] ?? false),
            (string) ($r['proxy_source_member_id'] ?? ''),
            $this->formatDate($r['cast_at'] ?? null),
            $this->translateVoteSource($r['source'] ?? ''),
            (string) ($r['token_id'] ?? ''),
            (string) ($r['token_hash_prefix'] ?? ''),
            $this->formatDate($r['token_expires_at'] ?? null),
            $this->formatDate($r['token_used_at'] ?? null),
            (string) ($r['manual_justification'] ?? ''),
        ];
    }

    // ========================================================================
    // HEADER DEFINITIONS
    // ========================================================================

    public function getAttendanceHeaders(): array {
        return ['Nom', 'Pouvoir de vote', 'Mode de présence', 'Arrivée', 'Départ', 'Représenté par', 'Procurations détenues'];
    }

    public function getVotesHeaders(): array {
        return ['Résolution', 'N°', 'Votant', 'Vote', 'Poids', 'Par procuration', 'Au nom de', 'Date/Heure', 'Mode'];
    }

    public function getMembersHeaders(): array {
        return ['Nom', 'Email', 'Pouvoir de vote', 'Actif', 'Mode de présence', 'Arrivée', 'Départ', 'Représenté par'];
    }

    public function getMotionResultsHeaders(): array {
        return ['N°', 'Résolution', 'Ouverture', 'Clôture', 'Pour', 'Contre', 'Abstention', 'NSPP', 'Total exprimés', 'Nb votants', 'Décision', 'Motif'];
    }

    public function getProxiesHeaders(): array {
        return ['Mandant', 'Mandataire', 'Pouvoir de vote', 'Date', 'Active'];
    }

    public function getAuditHeaders(): array {
        return [
            'Ballot ID', 'Motion ID', 'Résolution', 'Member ID', 'Votant', 'Mode présence',
            'Choix', 'Poids', 'Proxy vote', 'Proxy source member_id', 'Horodatage vote',
            'Source vote', 'Token ID', 'Token hash (prefix)', 'Token expires_at', 'Token used_at',
            'Justification (manual)',
        ];
    }
}
