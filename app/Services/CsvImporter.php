<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;

/**
 * CsvImporter - CSV file reading and member/attendance import processing.
 *
 * Extracted from ImportService. Process methods are format-agnostic but placed
 * here for LOC distribution. Callers access them via ImportService delegation stubs.
 */
final class CsvImporter {
    private ?RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /** Allowed MIME types for CSV */
    public const CSV_MIME_TYPES = [
        'text/csv',
        'text/plain',
        'application/csv',
        'application/vnd.ms-excel',
    ];

    /**
     * Reads a CSV file and returns rows as arrays.
     *
     * @return array ['headers' => array, 'rows' => array, 'separator' => string, 'error' => ?string]
     */
    public static function readFile(string $filePath): array {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            return ['headers' => [], 'rows' => [], 'separator' => ',', 'error' => 'Impossible d\'ouvrir le fichier.'];
        }

        // Detect and normalize encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        if ($encoding !== false && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        // Write normalized content to temp file for fgetcsv
        $tmpPath = tempnam(sys_get_temp_dir(), 'csv_enc_');
        file_put_contents($tmpPath, $content);
        $handle = @fopen($tmpPath, 'r');
        if (!$handle) {
            @unlink($tmpPath);
            return ['headers' => [], 'rows' => [], 'separator' => ',', 'error' => 'Impossible d\'ouvrir le fichier.'];
        }

        try {
            // Detect separator
            $firstLine = fgets($handle);
            rewind($handle);
            $separator = ($firstLine !== false && strpos($firstLine, ';') !== false) ? ';' : ',';

            $headers = fgetcsv($handle, 0, $separator);
            if (!$headers) {
                return ['headers' => [], 'rows' => [], 'separator' => $separator, 'error' => 'En-têtes CSV invalides.'];
            }
            $headers = array_map(fn ($h) => strtolower(trim($h)), $headers);

            $rows = [];
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                if (!empty(array_filter($row))) {
                    $rows[] = $row;
                }
            }

            return ['headers' => $headers, 'rows' => $rows, 'separator' => $separator, 'error' => null];
        } finally {
            fclose($handle);
            @unlink($tmpPath);
        }
    }

    /**
     * Processes member import rows: creates or updates members and assigns groups.
     *
     * @return array{imported: int, skipped: int, errors: array}
     */
    public function processMemberImport(
        array $rows,
        array $colIndex,
        bool $hasName,
        bool $hasFirstLast,
        string $tenantId,
    ): array {
        $memberRepo = $this->repos->member();
        $groupRepo = $this->repos->memberGroup();

        $existingGroups = [];
        foreach ($groupRepo->listForTenant($tenantId, false) as $g) {
            $existingGroups[mb_strtolower($g['name'])] = $g['id'];
        }

        $findOrCreateGroup = function (string $name) use ($groupRepo, $tenantId, &$existingGroups): ?string {
            $name = trim($name);
            if ($name === '') {
                return null;
            }
            $key = mb_strtolower($name);
            if (isset($existingGroups[$key])) {
                return $existingGroups[$key];
            }
            $group = $groupRepo->create($tenantId, $name);
            $existingGroups[$key] = $group['id'];
            return $group['id'];
        };

        $seenEmails = [];
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

            $data = [];
            if ($hasName && isset($colIndex['name'])) {
                $data['full_name'] = trim($row[$colIndex['name']] ?? '');
            } elseif ($hasFirstLast) {
                $data['full_name'] = trim(trim($row[$colIndex['first_name']] ?? '') . ' ' . trim($row[$colIndex['last_name']] ?? ''));
            }

            if (isset($colIndex['email'])) {
                $data['email'] = strtolower(trim($row[$colIndex['email']] ?? ''));
            }

            // In-batch duplicate email detection
            if (!empty($data['email'])) {
                if (isset($seenEmails[$data['email']])) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Email en double dans le fichier: {$data['email']} (déjà à la ligne {$seenEmails[$data['email']]})"];
                    $skipped++;
                    continue;
                }
                $seenEmails[$data['email']] = $lineNumber;
            }
            $data['voting_power'] = isset($colIndex['voting_power'])
                ? ImportService::parseVotingPower($row[$colIndex['voting_power']] ?? '1') : 1.0;
            $data['is_active'] = isset($colIndex['is_active'])
                ? ImportService::parseBoolean($row[$colIndex['is_active']] ?? '1') : true;

            if (empty($data['full_name']) || mb_strlen($data['full_name']) < 2) {
                $errors[] = ['line' => $lineNumber, 'error' => 'Nom invalide'];
                $skipped++;
                continue;
            }
            if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['line' => $lineNumber, 'error' => 'Email invalide'];
                $skipped++;
                continue;
            }

            $existing = null;
            if (!empty($data['email'])) {
                $existing = $memberRepo->findByEmail($tenantId, $data['email']);
            }
            if (!$existing) {
                $existing = $memberRepo->findByFullName($tenantId, $data['full_name']);
            }

            $groupNames = [];
            if (isset($colIndex['groups'])) {
                $groupsRaw = trim($row[$colIndex['groups']] ?? '');
                if ($groupsRaw !== '') {
                    $groupNames = preg_split('/[|;]/', $groupsRaw);
                    $groupNames = array_filter(array_map('trim', $groupNames));
                }
            }

            $memberId = null;
            if ($existing) {
                $memberId = $existing['id'];
                $memberRepo->updateImport($memberId, $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active'], $tenantId);
            } else {
                $memberId = $memberRepo->createImport($tenantId, $data['full_name'], $data['email'] ?: null, $data['voting_power'], $data['is_active']);
            }

            if (!empty($groupNames) && $memberId) {
                $groupIds = [];
                foreach ($groupNames as $gn) {
                    $gid = $findOrCreateGroup($gn);
                    if ($gid) {
                        $groupIds[] = $gid;
                    }
                }
                if (!empty($groupIds)) {
                    $groupRepo->setMemberGroups($memberId, $groupIds);
                }
            }

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Processes attendance import rows: upserts attendance records or builds preview.
     *
     * @return array{imported: int, skipped: int, errors: array, preview: array}
     */
    public function processAttendanceImport(
        array $rows,
        array $colIndex,
        string $tenantId,
        string $meetingId,
        bool $dryRun = false,
    ): array {
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);
        $attendanceRepo = $this->repos->attendance();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

            $member = null;
            if (isset($colIndex['email'])) {
                $email = strtolower(trim($row[$colIndex['email']] ?? ''));
                if ($email !== '' && isset($membersByEmail[$email])) {
                    $member = $membersByEmail[$email];
                }
            }
            if (!$member && isset($colIndex['name'])) {
                $name = mb_strtolower(trim($row[$colIndex['name']] ?? ''));
                if ($name !== '' && isset($membersByName[$name])) {
                    $member = $membersByName[$name];
                }
            }
            if (!$member) {
                $identifier = isset($colIndex['email']) ? ($row[$colIndex['email']] ?? '') : ($row[$colIndex['name']] ?? '');
                $errors[] = ['line' => $lineNumber, 'error' => "Membre introuvable: {$identifier}"];
                $skipped++;
                continue;
            }

            $mode = 'present';
            if (isset($colIndex['mode'])) {
                $modeRaw = trim($row[$colIndex['mode']] ?? '');
                $parsedMode = ImportService::parseAttendanceMode($modeRaw);
                if ($parsedMode === null && $modeRaw !== '') {
                    $errors[] = ['line' => $lineNumber, 'error' => "Mode invalide: {$modeRaw}"];
                    $skipped++;
                    continue;
                }
                $mode = $parsedMode ?? 'present';
            }

            $notes = null;
            if (isset($colIndex['notes'])) {
                $notes = trim($row[$colIndex['notes']] ?? '') ?: null;
            }

            if ($dryRun) {
                $preview[] = ['line' => $lineNumber, 'member_id' => $member['id'], 'member_name' => $member['full_name'], 'mode' => $mode, 'notes' => $notes];
            } else {
                $attendanceRepo->upsert($tenantId, $meetingId, $member['id'], $mode, (float) ($member['voting_power'] ?? 1), $notes);
            }
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'preview' => $preview];
    }

    /**
     * Builds email and name lookup maps for all members in a tenant.
     *
     * @return array{0: array, 1: array} [$membersByEmail, $membersByName]
     */
    private function buildMemberLookups(string $tenantId): array {
        $allMembers = $this->repos->member()->listByTenant($tenantId);
        $membersByEmail = [];
        $membersByName = [];
        foreach ($allMembers as $m) {
            if (!empty($m['email'])) {
                $membersByEmail[strtolower($m['email'])] = $m;
            }
            $membersByName[mb_strtolower($m['full_name'])] = $m;
        }
        return [$membersByEmail, $membersByName];
    }
}
