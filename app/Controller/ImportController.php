<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\ImportService;

final class ImportController extends AbstractController {
    private ?ImportService $importService = null;

    private function importService(): ImportService {
        return $this->importService ??= new ImportService($this->repo());
    }

    // =========================================================================
    // Public API methods — Members
    // =========================================================================

    public function membersCsv(): void {
        $in = api_request('POST');
        $file = api_file('file', 'csv_file');
        $csvContent = $in['csv_content'] ?? null;
        if (!$file && !$csvContent) {
            $jsonBody = json_decode(file_get_contents('php://input'), true);
            $csvContent = $jsonBody['csv_content'] ?? null;
        }
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
                api_fail('file_too_large', 400, ['detail' => 'Fichier trop volumineux. Maximum 10 Mo.']);
            }
            $validation = ImportService::validateUploadedFile($file, 'csv');
            if (!$validation['ok']) { api_fail('invalid_file', 400, ['detail' => $validation['error']]); }
            $result = ImportService::readCsvFile($file['tmp_name']);
            if ($result['error']) { api_fail('file_read_error', 400, ['detail' => $result['error']]); }
            [$headers, $rows] = [$result['headers'], $result['rows']];
        } elseif ($csvContent && is_string($csvContent) && strlen($csvContent) > 0) {
            if (strlen($csvContent) > 5 * 1024 * 1024) { api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']); }
            $tmpPath = tempnam(sys_get_temp_dir(), 'csv_'); chmod($tmpPath, 0600);
            file_put_contents($tmpPath, $csvContent);
            try { $result = ImportService::readCsvFile($tmpPath); } finally { unlink($tmpPath); }
            if ($result['error']) { api_fail('file_read_error', 400, ['detail' => $result['error']]); }
            [$headers, $rows] = [$result['headers'], $result['rows']];
        } else {
            api_fail('upload_error', 400, ['detail' => 'Fichier CSV manquant. Envoyez un fichier (file/csv_file) ou le contenu texte (csv_content).']);
        }
        $colIndex = ImportService::mapColumns($headers, ImportService::getMembersColumnMap());
        $hasName = isset($colIndex['name']); $hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
        if (!$hasName && !$hasFirstLast) { api_fail('missing_name_column', 400, ['detail' => 'Colonne "name" ou "first_name"+"last_name" requise.', 'found' => $headers]); }
        $dupes = ImportService::checkDuplicateEmails($rows, $colIndex);
        if (!empty($dupes)) { api_fail('duplicate_emails', 422, ['detail' => 'Le fichier contient des adresses email en double.', 'duplicate_emails' => $dupes]); }
        $tenantId = api_current_tenant_id(); $imported = 0; $skipped = 0; $errors = [];
        self::wrapApiCall(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
            api_transaction(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
                $this->processMemberRows($rows, $colIndex, $hasName, $hasFirstLast, $tenantId, $imported, $skipped, $errors);
            });
        }, 'import_failed');
        audit_log('members_import', 'member', null, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'] ?? 'csv_content']);
        api_ok(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    }

    public function membersXlsx(): void {
        api_request('POST');
        [$headers, $rows] = $this->readImportFile('xlsx');
        $colIndex = ImportService::mapColumns($headers, ImportService::getMembersColumnMap());
        $hasName = isset($colIndex['name']); $hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
        if (!$hasName && !$hasFirstLast) { api_fail('missing_name_column', 400, ['detail' => 'Colonne "name" ou "first_name"+"last_name" requise.', 'found' => $headers]); }
        $dupes = ImportService::checkDuplicateEmails($rows, $colIndex);
        if (!empty($dupes)) { api_fail('duplicate_emails', 422, ['detail' => 'Le fichier contient des adresses email en double.', 'duplicate_emails' => $dupes]); }
        $tenantId = api_current_tenant_id(); $imported = 0; $skipped = 0; $errors = [];
        self::wrapApiCall(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
            api_transaction(function () use ($rows, $hasName, $hasFirstLast, $colIndex, $tenantId, &$imported, &$skipped, &$errors) {
                $this->processMemberRows($rows, $colIndex, $hasName, $hasFirstLast, $tenantId, $imported, $skipped, $errors);
            });
        }, 'import_failed');
        $file = api_file('file', 'xlsx_file');
        audit_log('members_import_xlsx', 'member', null, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name']]);
        api_ok(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    }

    // =========================================================================
    // Public API methods — Attendances
    // =========================================================================

    public function attendancesCsv(): void {
        $in = api_request('POST'); [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        [$headers, $rows] = $this->readImportFile('csv');
        $colIndex = ImportService::mapColumns($headers, ImportService::getAttendancesColumnMap());
        if (!isset($colIndex['name']) && !isset($colIndex['email'])) { api_fail('missing_identifier', 400, ['detail' => 'Colonne "name", "nom" ou "email" requise.', 'found' => $headers]); }
        $tenantId = api_current_tenant_id(); $imported = 0; $skipped = 0; $errors = []; $preview = [];
        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
                $this->processAttendanceRows($rows, $colIndex, [], [], $tenantId, $meetingId, $dryRun, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dryRun && $imported > 0) { $file = api_file('file', 'csv_file'); audit_log('attendances_import', 'attendance', $meetingId, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name']], $meetingId); }
        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) { $response['preview'] = array_slice($preview, 0, 50); }
        api_ok($response);
    }

    public function attendancesXlsx(): void {
        $in = api_request('POST'); [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        [$headers, $rows] = $this->readImportFile('xlsx');
        $colIndex = ImportService::mapColumns($headers, ImportService::getAttendancesColumnMap());
        if (!isset($colIndex['name']) && !isset($colIndex['email'])) { api_fail('missing_identifier', 400, ['detail' => 'Colonne "name", "nom" ou "email" requise.', 'found' => $headers]); }
        $tenantId = api_current_tenant_id(); $imported = 0; $skipped = 0; $errors = []; $preview = [];
        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$imported, &$skipped, &$errors, &$preview) {
                $this->processAttendanceRows($rows, $colIndex, [], [], $tenantId, $meetingId, $dryRun, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dryRun && $imported > 0) { $file = api_file('file', 'xlsx_file'); audit_log('attendances_import_xlsx', 'attendance', $meetingId, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name']], $meetingId); }
        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) { $response['preview'] = array_slice($preview, 0, 50); }
        api_ok($response);
    }

    // =========================================================================
    // Public API methods — Proxies
    // =========================================================================

    public function proxiesCsv(): void {
        $in = api_request('POST'); [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $maxProxiesPerReceiver = (int) config('proxy_max_per_receiver', 3);
        $file = api_file('file', 'csv_file'); $csvContent = $in['csv_content'] ?? null;
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $validation = ImportService::validateUploadedFile($file, 'csv');
            if (!$validation['ok']) { api_fail('invalid_file', 400, ['detail' => $validation['error']]); }
            $result = ImportService::readCsvFile($file['tmp_name']);
            if ($result['error']) { api_fail('file_read_error', 400, ['detail' => $result['error']]); }
            [$headers, $rows] = [$result['headers'], $result['rows']];
        } elseif ($csvContent && is_string($csvContent) && strlen($csvContent) > 0) {
            if (strlen($csvContent) > 5 * 1024 * 1024) { api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']); }
            $tmpPath = tempnam(sys_get_temp_dir(), 'csv_'); chmod($tmpPath, 0600);
            file_put_contents($tmpPath, $csvContent);
            try { $result = ImportService::readCsvFile($tmpPath); } finally { unlink($tmpPath); }
            if ($result['error']) { api_fail('file_read_error', 400, ['detail' => $result['error']]); }
            [$headers, $rows] = [$result['headers'], $result['rows']];
        } else { api_fail('upload_error', 400, ['detail' => 'Fichier CSV manquant. Envoyez un fichier (file/csv_file) ou le contenu texte (csv_content).']); }
        $colIndex = ImportService::mapColumns($headers, ImportService::getProxiesColumnMap());
        $hasGiver = isset($colIndex['giver_name']) || isset($colIndex['giver_email']);
        $hasReceiver = isset($colIndex['receiver_name']) || isset($colIndex['receiver_email']);
        if (!$hasGiver || !$hasReceiver) { api_fail('missing_columns', 400, ['detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).', 'found' => $headers]); }
        $tenantId = api_current_tenant_id();
        $proxyRepo = $this->repo()->proxy(); $existingProxies = $proxyRepo->listForMeeting($meetingId, $tenantId);
        $proxiesPerReceiver = []; $existingGivers = [];
        foreach ($existingProxies as $p) { $proxiesPerReceiver[$p['receiver_member_id']] = ($proxiesPerReceiver[$p['receiver_member_id']] ?? 0) + 1; $existingGivers[$p['giver_member_id']] = $p['receiver_member_id']; }
        $imported = 0; $skipped = 0; $errors = []; $preview = [];
        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
                $this->processProxyRows($rows, $colIndex, null, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, $proxiesPerReceiver, $existingGivers, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dryRun && $imported > 0) { $filename = $file ? ($file['name'] ?? 'upload.csv') : 'csv_content'; audit_log('proxies_import', 'proxy', $meetingId, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $filename], $meetingId); }
        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun, 'max_proxies_per_receiver' => $maxProxiesPerReceiver];
        if ($dryRun) { $response['preview'] = array_slice($preview, 0, 50); }
        api_ok($response);
    }

    public function proxiesXlsx(): void {
        $in = api_request('POST'); [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $maxProxiesPerReceiver = (int) config('proxy_max_per_receiver', 3);
        [$headers, $rows] = $this->readImportFile('xlsx');
        $colIndex = ImportService::mapColumns($headers, ImportService::getProxiesColumnMap());
        $hasGiver = isset($colIndex['giver_name']) || isset($colIndex['giver_email']);
        $hasReceiver = isset($colIndex['receiver_name']) || isset($colIndex['receiver_email']);
        if (!$hasGiver || !$hasReceiver) { api_fail('missing_columns', 400, ['detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).', 'found' => $headers]); }
        $tenantId = api_current_tenant_id();
        $proxyRepo = $this->repo()->proxy(); $existingProxies = $proxyRepo->listForMeeting($meetingId, $tenantId);
        $proxiesPerReceiver = []; $existingGivers = [];
        foreach ($existingProxies as $p) { $proxiesPerReceiver[$p['receiver_member_id']] = ($proxiesPerReceiver[$p['receiver_member_id']] ?? 0) + 1; $existingGivers[$p['giver_member_id']] = $p['receiver_member_id']; }
        $imported = 0; $skipped = 0; $errors = []; $preview = [];
        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, &$proxiesPerReceiver, &$existingGivers, &$imported, &$skipped, &$errors, &$preview) {
                $this->processProxyRows($rows, $colIndex, null, $tenantId, $meetingId, $dryRun, $maxProxiesPerReceiver, $proxiesPerReceiver, $existingGivers, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dryRun && $imported > 0) { $file = api_file('file', 'xlsx_file'); audit_log('proxies_import_xlsx', 'proxy', $meetingId, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name']], $meetingId); }
        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun, 'max_proxies_per_receiver' => $maxProxiesPerReceiver];
        if ($dryRun) { $response['preview'] = array_slice($preview, 0, 50); }
        api_ok($response);
    }

    // =========================================================================
    // Public API methods — Motions
    // =========================================================================

    public function motionsCsv(): void {
        $in = api_request('POST'); [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        [$headers, $rows] = $this->readImportFile('csv');
        $colIndex = ImportService::mapColumns($headers, ImportService::getMotionsColumnMap());
        if (!isset($colIndex['title'])) { api_fail('missing_title_column', 400, ['detail' => 'Colonne "title" ou "titre" requise.', 'found' => $headers]); }
        $tenantId = api_current_tenant_id();
        $motionRepo = $this->repo()->motion(); $nextPosition = $motionRepo->countForMeeting($meetingId, $tenantId) + 1;
        $imported = 0; $skipped = 0; $errors = []; $preview = [];
        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
                $this->processMotionRows($rows, $colIndex, $tenantId, $meetingId, $dryRun, $nextPosition, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dryRun && $imported > 0) { $file = api_file('file', 'csv_file'); audit_log('motions_import', 'motion', $meetingId, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name']], $meetingId); }
        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) { $response['preview'] = array_slice($preview, 0, 50); }
        api_ok($response);
    }

    public function motionsXlsx(): void {
        $in = api_request('POST'); [$meetingId, $meeting] = $this->requireWritableMeeting($in);
        $dryRun = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        [$headers, $rows] = $this->readImportFile('xlsx');
        $colIndex = ImportService::mapColumns($headers, ImportService::getMotionsColumnMap());
        if (!isset($colIndex['title'])) { api_fail('missing_title_column', 400, ['detail' => 'Colonne "title" ou "titre" requise.', 'found' => $headers]); }
        $tenantId = api_current_tenant_id();
        $motionRepo = $this->repo()->motion(); $nextPosition = $motionRepo->countForMeeting($meetingId, $tenantId) + 1;
        $imported = 0; $skipped = 0; $errors = []; $preview = [];
        self::wrapApiCall(function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
            $work = function () use ($rows, $colIndex, $tenantId, $meetingId, $dryRun, &$nextPosition, &$imported, &$skipped, &$errors, &$preview) {
                $this->processMotionRows($rows, $colIndex, $tenantId, $meetingId, $dryRun, $nextPosition, $imported, $skipped, $errors, $preview);
            };
            $dryRun ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dryRun && $imported > 0) { $file = api_file('file', 'xlsx_file'); audit_log('motions_import_xlsx', 'motion', $meetingId, ['imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name']], $meetingId); }
        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) { $response['preview'] = array_slice($preview, 0, 50); }
        api_ok($response);
    }

    // =========================================================================
    // Private helpers — File reading & validation
    // =========================================================================

    private function readImportFile(string $format): array {
        $fileKeys = $format === 'csv' ? ['file', 'csv_file'] : ['file', 'xlsx_file'];
        $file = api_file(...$fileKeys);
        if (!$file) { api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']); }
        if (($file['size'] ?? 0) > 10 * 1024 * 1024) { api_fail('file_too_large', 400, ['detail' => 'Fichier trop volumineux. Maximum 10 Mo.']); }
        $validation = ImportService::validateUploadedFile($file, $format);
        if (!$validation['ok']) { api_fail('invalid_file', 400, ['detail' => $validation['error']]); }
        $result = $format === 'csv' ? ImportService::readCsvFile($file['tmp_name']) : ImportService::readXlsxFile($file['tmp_name']);
        if ($result['error']) { api_fail('file_read_error', 400, ['detail' => $result['error']]); }
        return [$result['headers'], $result['rows']];
    }

    private function requireWritableMeeting(array $in): array {
        $meetingId = trim((string) ($in['meeting_id'] ?? ''));
        if (!api_is_uuid($meetingId)) { api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']); }
        $tenantId = api_current_tenant_id();
        $meeting = $this->repo()->meeting()->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) { api_fail('meeting_not_found', 404); }
        if (in_array($meeting['status'], ['validated', 'archived'], true)) { api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']); }
        return [$meetingId, $meeting];
    }

    // =========================================================================
    // Private delegation wrappers — delegates to ImportService instance methods
    // =========================================================================

    private function buildMemberLookups(string $tenantId): array {
        $allMembers = $this->repo()->member()->listByTenant($tenantId);
        $membersByEmail = []; $membersByName = [];
        foreach ($allMembers as $m) { if (!empty($m['email'])) { $membersByEmail[strtolower($m['email'])] = $m; } $membersByName[mb_strtolower($m['full_name'])] = $m; }
        return [$membersByEmail, $membersByName];
    }

    private function buildProxyMemberFinder(array $colIndex, array $membersByEmail, array $membersByName): callable {
        return function (array $row, string $nameField, string $emailField) use ($colIndex, $membersByEmail, $membersByName): ?array {
            if (isset($colIndex[$emailField])) { $email = strtolower(trim($row[$colIndex[$emailField]] ?? '')); if ($email !== '' && isset($membersByEmail[$email])) { return $membersByEmail[$email]; } }
            if (isset($colIndex[$nameField])) { $name = mb_strtolower(trim($row[$colIndex[$nameField]] ?? '')); if ($name !== '' && isset($membersByName[$name])) { return $membersByName[$name]; } }
            return null;
        };
    }

    private function processMemberRows(array $rows, array $colIndex, bool $hasName, bool $hasFirstLast, string $tenantId, int &$imported, int &$skipped, array &$errors): void {
        $result = $this->importService()->processMemberImport($rows, $colIndex, $hasName, $hasFirstLast, $tenantId);
        $imported += $result['imported']; $skipped += $result['skipped']; $errors = array_merge($errors, $result['errors']);
    }

    private function processAttendanceRows(array $rows, array $colIndex, array $membersByEmail, array $membersByName, string $tenantId, string $meetingId, bool $dryRun, int &$imported, int &$skipped, array &$errors, array &$preview): void {
        $result = $this->importService()->processAttendanceImport($rows, $colIndex, $tenantId, $meetingId, $dryRun);
        $imported += $result['imported']; $skipped += $result['skipped']; $errors = array_merge($errors, $result['errors']); $preview = array_merge($preview, $result['preview']);
    }

    private function processProxyRows(array $rows, array $colIndex, ?callable $findMember, string $tenantId, string $meetingId, bool $dryRun, int $maxPerReceiver, array &$proxiesPerReceiver, array &$existingGivers, int &$imported, int &$skipped, array &$errors, array &$preview): void {
        $result = $this->importService()->processProxyImport($rows, $colIndex, $tenantId, $meetingId, $dryRun, $maxPerReceiver, $proxiesPerReceiver, $existingGivers);
        $imported += $result['imported']; $skipped += $result['skipped']; $errors = array_merge($errors, $result['errors']); $preview = array_merge($preview, $result['preview']);
    }

    private function processMotionRows(array $rows, array $colIndex, string $tenantId, string $meetingId, bool $dryRun, int &$nextPosition, int &$imported, int &$skipped, array &$errors, array &$preview): void {
        $result = $this->importService()->processMotionImport($rows, $colIndex, $tenantId, $meetingId, $dryRun, $nextPosition);
        $imported += $result['imported']; $skipped += $result['skipped']; $errors = array_merge($errors, $result['errors']); $preview = array_merge($preview, $result['preview']);
    }
}
