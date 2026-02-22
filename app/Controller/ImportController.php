<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Service\ImportService;

final class ImportController extends AbstractController {
    // =========================================================================
    // Public API methods — Members
    // =========================================================================

    public function membersCsv(): void {
        $in = api_request('POST');

        // Support 3 modes: file upload, csv_content as FormData, csv_content as JSON
        $file = api_file('file', 'csv_file');
        $csvContent = $in['csv_content'] ?? null;

        if (!$file && !$csvContent) {
            $jsonBody = json_decode(file_get_contents('php://input'), true);
            $csvContent = $jsonBody['csv_content'] ?? null;
        }

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $validation = ImportService::validateUploadedFile($file, 'csv');
            if (!$validation['ok']) {
                api_fail('invalid_file', 400, ['detail' => $validation['error']]);
            }
            $result = ImportService::readCsvFile($file['tmp_name']);
            if ($result['error']) {
                api_fail('file_read_error', 400, ['detail' => $result['error']]);
            }
            $headers = $result['headers'];
            $rows = $result['rows'];
        } elseif ($csvContent && is_string($csvContent) && strlen($csvContent) > 0) {
            if (strlen($csvContent) > 5 * 1024 * 1024) {
                api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);
            }
            $tmpPath = tempnam(sys_get_temp_dir(), 'csv_');
            chmod($tmpPath, 0600);
            file_put_contents($tmpPath, $csvContent);
            try {
                $result = ImportService::readCsvFile($tmpPath);
            } finally {
                unlink($tmpPath);
            }
            if ($result['error']) {
                api_fail('file_read_error', 400, ['detail' => $result['error']]);
            }
            $headers = $result['headers'];
            $rows = $result['rows'];
        } else {
            api_fail('upload_error', 400, ['detail' => 'Fichier CSV manquant. Envoyez un fichier (file/csv_file) ou le contenu texte (csv_content).']);
        }

        $colIndex = ImportService::mapColumns($headers, ImportService::getMembersColumnMap());

        $hasName = isset($colIndex['name']);
        $hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
        if (!$hasName && !$hasFirstLast) {
            api_fail('missing_name_column', 400, ['detail' => 'Colonne "name" ou "first_name"+"last_name" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        self::wrapApiCall(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
            api_transaction(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
                $this->processMemberRows($rows, $colIndex, $hasName, $hasFirstLast, $tenantId, $imported, $skipped, $errors);
            });
        }, 'import_failed');

        audit_log('members_import', 'member', null, [
            'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'] ?? 'csv_content',
        ]);

        api_ok(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    }

    public function membersXlsx(): void {
        api_request('POST');

        [$headers, $rows] = $this->readImportFile('xlsx');

        $colIndex = ImportService::mapColumns($headers, ImportService::getMembersColumnMap());

        $hasName = isset($colIndex['name']);
        $hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
        if (!$hasName && !$hasFirstLast) {
            api_fail('missing_name_column', 400, ['detail' => 'Colonne "name" ou "first_name"+"last_name" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        self::wrapApiCall(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
            api_transaction(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
                $this->processMemberRows($rows, $colIndex, $hasName, $hasFirstLast, $tenantId, $imported, $skipped, $errors);
            });
        }, 'import_failed');

        $file = api_file('file', 'xlsx_file');
        audit_log('members_import_xlsx', 'member', null, [
            'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
        ]);

        api_ok(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    }

    // =========================================================================
    // Public API methods — Attendances
    // =========================================================================

    public function attendancesCsv(): void {
        $in = api_request('POST');
        [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        [$headers, $rows] = $this->readImportFile('csv');

        $colIndex = ImportService::mapColumns($headers, ImportService::getAttendancesColumnMap());
        if (!isset($colIndex['name']) && !isset($colIndex['email'])) {
            api_fail('missing_identifier', 400, ['detail' => 'Colonne "name", "nom" ou "email" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        self::wrapApiCall(function () use ($rows, $colIndex, $membersByEmail, $membersByName, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $membersByEmail, $membersByName, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
                $this->processAttendanceRows($rows, $colIndex, $membersByEmail, $membersByName, $tenantId, $meetingId, $dryRun, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');

        if (!$dryRun && $imported > 0) {
            $file = api_file('file', 'csv_file');
            audit_log('attendances_import', 'attendance', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) {
            $response['preview'] = array_slice($preview, 0, 50);
        }
        api_ok($response);
    }

    public function attendancesXlsx(): void {
        $in = api_request('POST');
        [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        [$headers, $rows] = $this->readImportFile('xlsx');

        $colIndex = ImportService::mapColumns($headers, ImportService::getAttendancesColumnMap());
        if (!isset($colIndex['name']) && !isset($colIndex['email'])) {
            api_fail('missing_identifier', 400, ['detail' => 'Colonne "name", "nom" ou "email" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        self::wrapApiCall(function () use ($rows, $colIndex, $membersByEmail, $membersByName, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $membersByEmail, $membersByName, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
                $this->processAttendanceRows($rows, $colIndex, $membersByEmail, $membersByName, $tenantId, $meetingId, $dryRun, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');

        if (!$dryRun && $imported > 0) {
            $file = api_file('file', 'xlsx_file');
            audit_log('attendances_import_xlsx', 'attendance', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) {
            $response['preview'] = array_slice($preview, 0, 50);
        }
        api_ok($response);
    }

    // =========================================================================
    // Public API methods — Proxies
    // =========================================================================

    public function proxiesCsv(): void {
        $in = api_request('POST');
        [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $maxProxiesPerReceiver = (int) config('proxy_max_per_receiver', 3);

        [$headers, $rows] = $this->readImportFile('csv');

        $colIndex = ImportService::mapColumns($headers, ImportService::getProxiesColumnMap());
        $hasGiver = isset($colIndex['giver_name']) || isset($colIndex['giver_email']);
        $hasReceiver = isset($colIndex['receiver_name']) || isset($colIndex['receiver_email']);
        if (!$hasGiver || !$hasReceiver) {
            api_fail('missing_columns', 400, ['detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);

        $findMember = $this->buildProxyMemberFinder($colIndex, $membersByEmail, $membersByName);

        $proxyRepo = new ProxyRepository();
        $existingProxies = $proxyRepo->listForMeeting($meetingId, $tenantId);
        $proxiesPerReceiver = [];
        $existingGivers = [];
        foreach ($existingProxies as $p) {
            $proxiesPerReceiver[$p['receiver_member_id']] = ($proxiesPerReceiver[$p['receiver_member_id']] ?? 0) + 1;
            $existingGivers[$p['giver_member_id']] = $p['receiver_member_id'];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        self::wrapApiCall(function () use ($rows, $colIndex, $findMember, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $findMember, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
                $this->processProxyRows($rows, $colIndex, $findMember, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, $proxiesPerReceiver, $existingGivers, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');

        if (!$dryRun && $imported > 0) {
            $file = api_file('file', 'csv_file');
            audit_log('proxies_import', 'proxy', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun, 'max_proxies_per_receiver' => $maxProxiesPerReceiver];
        if ($dryRun) {
            $response['preview'] = array_slice($preview, 0, 50);
        }
        api_ok($response);
    }

    public function proxiesXlsx(): void {
        $in = api_request('POST');
        [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $maxProxiesPerReceiver = (int) config('proxy_max_per_receiver', 3);

        [$headers, $rows] = $this->readImportFile('xlsx');

        $colIndex = ImportService::mapColumns($headers, ImportService::getProxiesColumnMap());
        $hasGiver = isset($colIndex['giver_name']) || isset($colIndex['giver_email']);
        $hasReceiver = isset($colIndex['receiver_name']) || isset($colIndex['receiver_email']);
        if (!$hasGiver || !$hasReceiver) {
            api_fail('missing_columns', 400, ['detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);

        $findMember = $this->buildProxyMemberFinder($colIndex, $membersByEmail, $membersByName);

        $proxyRepo = new ProxyRepository();
        $existingProxies = $proxyRepo->listForMeeting($meetingId, $tenantId);
        $proxiesPerReceiver = [];
        $existingGivers = [];
        foreach ($existingProxies as $p) {
            $proxiesPerReceiver[$p['receiver_member_id']] = ($proxiesPerReceiver[$p['receiver_member_id']] ?? 0) + 1;
            $existingGivers[$p['giver_member_id']] = $p['receiver_member_id'];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        self::wrapApiCall(function () use ($rows, $colIndex, $findMember, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $findMember, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
                $this->processProxyRows($rows, $colIndex, $findMember, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, $proxiesPerReceiver, $existingGivers, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');

        if (!$dryRun && $imported > 0) {
            $file = api_file('file', 'xlsx_file');
            audit_log('proxies_import_xlsx', 'proxy', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun, 'max_proxies_per_receiver' => $maxProxiesPerReceiver];
        if ($dryRun) {
            $response['preview'] = array_slice($preview, 0, 50);
        }
        api_ok($response);
    }

    // =========================================================================
    // Public API methods — Motions
    // =========================================================================

    public function motionsCsv(): void {
        $in = api_request('POST');
        [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        [$headers, $rows] = $this->readImportFile('csv');

        $colIndex = ImportService::mapColumns($headers, ImportService::getMotionsColumnMap());
        if (!isset($colIndex['title'])) {
            api_fail('missing_title_column', 400, ['detail' => 'Colonne "title" ou "titre" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        $motionRepo = new MotionRepository();
        $nextPosition = $motionRepo->countForMeeting($meetingId, $tenantId) + 1;

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
                $this->processMotionRows($rows, $colIndex, $tenantId, $meetingId, $dryRun, $nextPosition, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');

        if (!$dryRun && $imported > 0) {
            $file = api_file('file', 'csv_file');
            audit_log('motions_import', 'motion', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) {
            $response['preview'] = array_slice($preview, 0, 50);
        }
        api_ok($response);
    }

    public function motionsXlsx(): void {
        $in = api_request('POST');
        [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        [$headers, $rows] = $this->readImportFile('xlsx');

        $colIndex = ImportService::mapColumns($headers, ImportService::getMotionsColumnMap());
        if (!isset($colIndex['title'])) {
            api_fail('missing_title_column', 400, ['detail' => 'Colonne "title" ou "titre" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        $motionRepo = new MotionRepository();
        $nextPosition = $motionRepo->countForMeeting($meetingId, $tenantId) + 1;

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
                $this->processMotionRows($rows, $colIndex, $tenantId, $meetingId, $dryRun, $nextPosition, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');

        if (!$dryRun && $imported > 0) {
            $file = api_file('file', 'xlsx_file');
            audit_log('motions_import_xlsx', 'motion', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) {
            $response['preview'] = array_slice($preview, 0, 50);
        }
        api_ok($response);
    }

    // =========================================================================
    // Private helpers — File reading & validation
    // =========================================================================

    /**
     * Reads and validates an uploaded import file (CSV or XLSX).
     *
     * @return array{0: array, 1: array} [$headers, $rows]
     */
    private function readImportFile(string $format): array {
        $fileKeys = $format === 'csv' ? ['file', 'csv_file'] : ['file', 'xlsx_file'];
        $file = api_file(...$fileKeys);
        if (!$file) {
            api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']);
        }

        $validation = ImportService::validateUploadedFile($file, $format);
        if (!$validation['ok']) {
            api_fail('invalid_file', 400, ['detail' => $validation['error']]);
        }

        if ($format === 'csv') {
            $result = ImportService::readCsvFile($file['tmp_name']);
            if ($result['error']) {
                api_fail('file_read_error', 400, ['detail' => $result['error']]);
            }
        } else {
            $result = ImportService::readXlsxFile($file['tmp_name']);
            if ($result['error']) {
                api_fail('file_read_error', 400, ['detail' => $result['error']]);
            }
        }

        return [$result['headers'], $result['rows']];
    }

    /**
     * Validates that a meeting exists and is writable (not validated/archived).
     *
     * @return array{0: string, 1: array} [$meetingId, $meeting]
     */
    private function requireWritableMeeting(array $in): array {
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        if (!api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);
        }

        $tenantId = api_current_tenant_id();
        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }
        if (in_array($meeting['status'], ['validated', 'archived'], true)) {
            api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
        }

        return [$meetingId, $meeting];
    }

    /**
     * Builds email and name lookup maps for all members in a tenant.
     *
     * @return array{0: array, 1: array} [$membersByEmail, $membersByName]
     */
    private function buildMemberLookups(string $tenantId): array {
        $allMembers = (new MemberRepository())->listByTenant($tenantId);
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

    /**
     * Creates a callable that finds a member by name/email fields in a proxy row.
     */
    private function buildProxyMemberFinder(array $colIndex, array $membersByEmail, array $membersByName): callable {
        return function (array $row, string $nameField, string $emailField) use ($colIndex, $membersByEmail, $membersByName): ?array {
            if (isset($colIndex[$emailField])) {
                $email = strtolower(trim($row[$colIndex[$emailField]] ?? ''));
                if ($email !== '' && isset($membersByEmail[$email])) {
                    return $membersByEmail[$email];
                }
            }
            if (isset($colIndex[$nameField])) {
                $name = mb_strtolower(trim($row[$colIndex[$nameField]] ?? ''));
                if ($name !== '' && isset($membersByName[$name])) {
                    return $membersByName[$name];
                }
            }
            return null;
        };
    }

    // =========================================================================
    // Private helpers — Row processing
    // =========================================================================

    /**
     * Processes member import rows: creates or updates members and assigns groups.
     */
    private function processMemberRows(
        array $rows,
        array $colIndex,
        bool $hasName,
        bool $hasFirstLast,
        string $tenantId,
        int &$imported,
        int &$skipped,
        array &$errors,
    ): void {
        $memberRepo = new MemberRepository();
        $groupRepo = new MemberGroupRepository();

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
    }

    /**
     * Processes attendance import rows: upserts attendance records or builds preview.
     */
    private function processAttendanceRows(
        array $rows,
        array $colIndex,
        array $membersByEmail,
        array $membersByName,
        string $tenantId,
        string $meetingId,
        bool $dryRun,
        int &$imported,
        int &$skipped,
        array &$errors,
        array &$preview,
    ): void {
        $attendanceRepo = new AttendanceRepository();

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
    }

    /**
     * Processes proxy import rows: creates proxies or builds preview with validation.
     */
    private function processProxyRows(
        array $rows,
        array $colIndex,
        callable $findMember,
        string $tenantId,
        string $meetingId,
        bool $dryRun,
        int $maxPerReceiver,
        array &$proxiesPerReceiver,
        array &$existingGivers,
        int &$imported,
        int &$skipped,
        array &$errors,
        array &$preview,
    ): void {
        $proxyRepo = new ProxyRepository();

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

            $giver = $findMember($row, 'giver_name', 'giver_email');
            if (!$giver) {
                $identifier = $row[$colIndex['giver_email'] ?? $colIndex['giver_name'] ?? 0] ?? 'inconnu';
                $errors[] = ['line' => $lineNumber, 'error' => "Mandant introuvable: {$identifier}"];
                $skipped++;
                continue;
            }

            $receiver = $findMember($row, 'receiver_name', 'receiver_email');
            if (!$receiver) {
                $identifier = $row[$colIndex['receiver_email'] ?? $colIndex['receiver_name'] ?? 0] ?? 'inconnu';
                $errors[] = ['line' => $lineNumber, 'error' => "Mandataire introuvable: {$identifier}"];
                $skipped++;
                continue;
            }

            if ($giver['id'] === $receiver['id']) {
                $errors[] = ['line' => $lineNumber, 'error' => 'Auto-délégation interdite'];
                $skipped++;
                continue;
            }
            if (isset($existingGivers[$giver['id']])) {
                $errors[] = ['line' => $lineNumber, 'error' => "Le mandant {$giver['full_name']} a déjà une procuration active"];
                $skipped++;
                continue;
            }
            if (isset($existingGivers[$receiver['id']])) {
                $errors[] = ['line' => $lineNumber, 'error' => "Chaîne de procuration interdite: {$receiver['full_name']} est déjà mandant"];
                $skipped++;
                continue;
            }

            $currentCount = $proxiesPerReceiver[$receiver['id']] ?? 0;
            if ($currentCount >= $maxPerReceiver) {
                $errors[] = ['line' => $lineNumber, 'error' => "Plafond atteint: {$receiver['full_name']} a déjà {$currentCount} procurations (max: {$maxPerReceiver})"];
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $preview[] = ['line' => $lineNumber, 'giver_id' => $giver['id'], 'giver_name' => $giver['full_name'], 'receiver_id' => $receiver['id'], 'receiver_name' => $receiver['full_name']];
            } else {
                $proxyRepo->upsertProxy($tenantId, $meetingId, $giver['id'], $receiver['id']);
            }
            $proxiesPerReceiver[$receiver['id']] = $currentCount + 1;
            $existingGivers[$giver['id']] = $receiver['id'];
            $imported++;
        }
    }

    /**
     * Processes motion import rows: creates motions or builds preview.
     */
    private function processMotionRows(
        array $rows,
        array $colIndex,
        string $tenantId,
        string $meetingId,
        bool $dryRun,
        int &$nextPosition,
        int &$imported,
        int &$skipped,
        array &$errors,
        array &$preview,
    ): void {
        $motionRepo = new MotionRepository();

        foreach ($rows as $lineIndex => $row) {
            $lineNumber = $lineIndex + 2;

            $title = trim($row[$colIndex['title']] ?? '');
            $description = isset($colIndex['description']) ? trim($row[$colIndex['description']] ?? '') : null;

            $position = null;
            if (isset($colIndex['position'])) {
                $posVal = trim($row[$colIndex['position']] ?? '');
                if ($posVal !== '' && is_numeric($posVal)) {
                    $position = (int) $posVal;
                }
            }
            if ($position === null) {
                $position = $nextPosition++;
            } else {
                $nextPosition = max($nextPosition, $position + 1);
            }

            $secret = false;
            if (isset($colIndex['secret'])) {
                $secret = ImportService::parseBoolean($row[$colIndex['secret']] ?? '0');
            }

            if (empty($title) || mb_strlen($title) < 2) {
                $errors[] = ['line' => $lineNumber, 'error' => 'Titre invalide ou trop court'];
                $skipped++;
                continue;
            }
            if (mb_strlen($title) > 500) {
                $errors[] = ['line' => $lineNumber, 'error' => 'Titre trop long (max 500 caractères)'];
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $preview[] = [
                    'line' => $lineNumber, 'title' => $title,
                    'description' => $description ? mb_substr($description, 0, 100) . (mb_strlen($description) > 100 ? '...' : '') : null,
                    'position' => $position, 'secret' => $secret,
                ];
            } else {
                $motionId = $motionRepo->generateUuid();
                $motionRepo->create($motionId, $tenantId, $meetingId, null, $title, $description ?? '', $secret, null, null);
                $motionRepo->updatePosition($motionId, $tenantId, $position);
            }
            $imported++;
        }
    }
}
