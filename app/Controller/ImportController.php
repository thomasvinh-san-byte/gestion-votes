<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\MemberRepository;
use AgVote\Repository\MemberGroupRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\ProxyRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Service\ImportService;

final class ImportController extends AbstractController
{
    public function membersCsv(): void
    {
        api_rate_limit('csv_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        // Support 3 modes: file upload, csv_content as FormData, csv_content as JSON
        $file = $_FILES['file'] ?? $_FILES['csv_file'] ?? null;
        $csvContent = $_POST['csv_content'] ?? null;

        if (!$file && !$csvContent) {
            $jsonBody = json_decode(file_get_contents('php://input'), true);
            $csvContent = $jsonBody['csv_content'] ?? null;
        }

        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($ext !== 'csv') api_fail('invalid_file_type', 400, ['detail' => 'Seuls les fichiers CSV sont acceptés.']);
            if ($file['size'] > 5 * 1024 * 1024) api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
                api_fail('invalid_mime_type', 400, ['detail' => "Type non autorisé: {$mime}"]);
            }

            $handle = fopen($file['tmp_name'], 'r');
            if (!$handle) api_fail('file_read_error', 500);
        } elseif ($csvContent && is_string($csvContent) && strlen($csvContent) > 0) {
            if (strlen($csvContent) > 5 * 1024 * 1024) api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);
            $tmpFile = tmpfile();
            fwrite($tmpFile, $csvContent);
            rewind($tmpFile);
            $handle = $tmpFile;
        } else {
            api_fail('upload_error', 400, ['detail' => 'Fichier CSV manquant. Envoyez un fichier (file/csv_file) ou le contenu texte (csv_content).']);
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $separator = strpos($firstLine, ';') !== false ? ';' : ',';

        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) { fclose($handle); api_fail('invalid_csv', 400, ['detail' => 'En-têtes CSV invalides.']); }

        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $columnMap = [
            'name' => ['name', 'nom', 'full_name', 'nom_complet'],
            'first_name' => ['first_name', 'prenom', 'prénom'],
            'last_name' => ['last_name', 'nom_famille'],
            'email' => ['email', 'mail', 'e-mail'],
            'voting_power' => ['voting_power', 'ponderation', 'pondération', 'weight', 'tantiemes'],
            'is_active' => ['is_active', 'actif', 'active'],
            'groups' => ['groups', 'groupes', 'group', 'groupe', 'college', 'collège', 'categorie', 'catégorie'],
        ];

        $colIndex = self::mapColumns($headers, $columnMap);

        $hasName = isset($colIndex['name']);
        $hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
        if (!$hasName && !$hasFirstLast) {
            fclose($handle);
            api_fail('missing_name_column', 400, ['detail' => 'Colonne "name" ou "first_name"+"last_name" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        $imported = 0; $skipped = 0; $errors = []; $lineNumber = 1;

        $memberRepo = new MemberRepository();
        $groupRepo = new MemberGroupRepository();

        $existingGroups = [];
        foreach ($groupRepo->listForTenant($tenantId, false) as $g) {
            $existingGroups[mb_strtolower($g['name'])] = $g['id'];
        }

        $findOrCreateGroup = function(string $name) use ($groupRepo, $tenantId, &$existingGroups): ?string {
            $name = trim($name);
            if ($name === '') return null;
            $key = mb_strtolower($name);
            if (isset($existingGroups[$key])) return $existingGroups[$key];
            $group = $groupRepo->create($tenantId, $name);
            $existingGroups[$key] = $group['id'];
            return $group['id'];
        };

        db()->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                $lineNumber++;
                if (empty(array_filter($row))) continue;

                $data = [];
                if ($hasName && isset($colIndex['name'])) {
                    $data['full_name'] = trim($row[$colIndex['name']] ?? '');
                } elseif ($hasFirstLast) {
                    $data['full_name'] = trim(trim($row[$colIndex['first_name']] ?? '') . ' ' . trim($row[$colIndex['last_name']] ?? ''));
                }

                if (isset($colIndex['email'])) $data['email'] = strtolower(trim($row[$colIndex['email']] ?? ''));
                if (isset($colIndex['voting_power'])) {
                    $vp = (float)($row[$colIndex['voting_power']] ?? 1);
                    $data['voting_power'] = max(0, min($vp, 100000));
                } else {
                    $data['voting_power'] = 1.0;
                }
                if (isset($colIndex['is_active'])) {
                    $val = strtolower(trim($row[$colIndex['is_active']] ?? '1'));
                    $data['is_active'] = in_array($val, ['1', 'true', 'oui', 'yes', 'actif'], true);
                } else {
                    $data['is_active'] = true;
                }

                if (empty($data['full_name']) || mb_strlen($data['full_name']) < 2) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Nom invalide']; $skipped++; continue;
                }
                if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Email invalide']; $skipped++; continue;
                }

                $existing = null;
                if (!empty($data['email'])) $existing = $memberRepo->findByEmail($tenantId, $data['email']);
                if (!$existing) $existing = $memberRepo->findByFullName($tenantId, $data['full_name']);

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
                        if ($gid) $groupIds[] = $gid;
                    }
                    if (!empty($groupIds)) $groupRepo->setMemberGroups($memberId, $groupIds);
                }

                $imported++;
            }
            db()->commit();
        } catch (\Throwable $e) {
            db()->rollBack();
            fclose($handle);
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        fclose($handle);

        audit_log('members_import', 'member', null, [
            'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'] ?? 'csv_content',
        ]);

        api_ok(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    }

    public function membersXlsx(): void
    {
        api_rate_limit('xlsx_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        $file = $_FILES['file'] ?? $_FILES['xlsx_file'] ?? null;
        if (!$file) api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']);

        $validation = ImportService::validateUploadedFile($file, 'xlsx');
        if (!$validation['ok']) api_fail('invalid_file', 400, ['detail' => $validation['error']]);

        $result = ImportService::readXlsxFile($file['tmp_name']);
        if ($result['error']) api_fail('file_read_error', 400, ['detail' => $result['error']]);

        $headers = $result['headers'];
        $rows = $result['rows'];

        $columnMap = ImportService::getMembersColumnMap();
        $colIndex = ImportService::mapColumns($headers, $columnMap);

        $hasName = isset($colIndex['name']);
        $hasFirstLast = isset($colIndex['first_name']) && isset($colIndex['last_name']);
        if (!$hasName && !$hasFirstLast) {
            api_fail('missing_name_column', 400, ['detail' => 'Colonne "name" ou "first_name"+"last_name" requise.', 'found' => $headers]);
        }

        $tenantId = api_current_tenant_id();
        $imported = 0; $skipped = 0; $errors = [];

        $memberRepo = new MemberRepository();
        $groupRepo = new MemberGroupRepository();

        $existingGroups = [];
        foreach ($groupRepo->listForTenant($tenantId, false) as $g) {
            $existingGroups[mb_strtolower($g['name'])] = $g['id'];
        }

        $findOrCreateGroup = function(string $name) use ($groupRepo, $tenantId, &$existingGroups): ?string {
            $name = trim($name);
            if ($name === '') return null;
            $key = mb_strtolower($name);
            if (isset($existingGroups[$key])) return $existingGroups[$key];
            $group = $groupRepo->create($tenantId, $name);
            $existingGroups[$key] = $group['id'];
            return $group['id'];
        };

        db()->beginTransaction();

        try {
            foreach ($rows as $lineIndex => $row) {
                $lineNumber = $lineIndex + 2;

                $data = [];
                if ($hasName && isset($colIndex['name'])) {
                    $data['full_name'] = trim($row[$colIndex['name']] ?? '');
                } elseif ($hasFirstLast) {
                    $data['full_name'] = trim(trim($row[$colIndex['first_name']] ?? '') . ' ' . trim($row[$colIndex['last_name']] ?? ''));
                }

                if (isset($colIndex['email'])) $data['email'] = strtolower(trim($row[$colIndex['email']] ?? ''));
                $data['voting_power'] = isset($colIndex['voting_power'])
                    ? ImportService::parseVotingPower($row[$colIndex['voting_power']] ?? '1') : 1.0;
                $data['is_active'] = isset($colIndex['is_active'])
                    ? ImportService::parseBoolean($row[$colIndex['is_active']] ?? '1') : true;

                if (empty($data['full_name']) || mb_strlen($data['full_name']) < 2) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Nom invalide']; $skipped++; continue;
                }
                if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Email invalide']; $skipped++; continue;
                }

                $existing = null;
                if (!empty($data['email'])) $existing = $memberRepo->findByEmail($tenantId, $data['email']);
                if (!$existing) $existing = $memberRepo->findByFullName($tenantId, $data['full_name']);

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
                        if ($gid) $groupIds[] = $gid;
                    }
                    if (!empty($groupIds)) $groupRepo->setMemberGroups($memberId, $groupIds);
                }

                $imported++;
            }
            db()->commit();
        } catch (\Throwable $e) {
            db()->rollBack();
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        audit_log('members_import_xlsx', 'member', null, [
            'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
        ]);

        api_ok(['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20)]);
    }

    public function attendancesCsv(): void
    {
        api_rate_limit('csv_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        $meetingId = trim($_POST['meeting_id'] ?? '');
        if (!api_is_uuid($meetingId)) api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);

        $tenantId = api_current_tenant_id();
        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) api_fail('meeting_not_found', 404);
        if (in_array($meeting['status'], ['validated', 'archived'], true)) {
            api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
        }

        $dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $file = $_FILES['file'] ?? $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) api_fail('upload_error', 400, ['detail' => 'Fichier manquant ou erreur upload.']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') api_fail('invalid_file_type', 400, ['detail' => 'Seuls les fichiers CSV sont acceptés.']);
        if ($file['size'] > 5 * 1024 * 1024) api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            api_fail('invalid_mime_type', 400, ['detail' => "Type non autorisé: {$mime}"]);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) api_fail('file_read_error', 500);

        $firstLine = fgets($handle);
        rewind($handle);
        $separator = strpos($firstLine, ';') !== false ? ';' : ',';

        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) { fclose($handle); api_fail('invalid_csv', 400, ['detail' => 'En-têtes CSV invalides.']); }
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $columnMap = [
            'name' => ['name', 'nom', 'full_name', 'nom_complet', 'membre'],
            'email' => ['email', 'mail', 'e-mail'],
            'mode' => ['mode', 'statut', 'status', 'presence', 'présence', 'etat', 'état'],
            'notes' => ['notes', 'commentaire', 'comment', 'remarque'],
        ];
        $colIndex = self::mapColumns($headers, $columnMap);

        if (!isset($colIndex['name']) && !isset($colIndex['email'])) {
            fclose($handle);
            api_fail('missing_identifier', 400, ['detail' => 'Colonne "name", "nom" ou "email" requise.', 'found' => $headers]);
        }

        $memberRepo = new MemberRepository();
        $attendanceRepo = new AttendanceRepository();

        $allMembers = $memberRepo->listByTenant($tenantId);
        $membersByEmail = []; $membersByName = [];
        foreach ($allMembers as $m) {
            if (!empty($m['email'])) $membersByEmail[strtolower($m['email'])] = $m;
            $membersByName[mb_strtolower($m['full_name'])] = $m;
        }

        $imported = 0; $skipped = 0; $errors = []; $lineNumber = 1; $preview = [];

        if (!$dryRun) db()->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                $lineNumber++;
                if (empty(array_filter($row))) continue;

                $member = null;
                if (isset($colIndex['email'])) {
                    $email = strtolower(trim($row[$colIndex['email']] ?? ''));
                    if ($email !== '' && isset($membersByEmail[$email])) $member = $membersByEmail[$email];
                }
                if (!$member && isset($colIndex['name'])) {
                    $name = mb_strtolower(trim($row[$colIndex['name']] ?? ''));
                    if ($name !== '' && isset($membersByName[$name])) $member = $membersByName[$name];
                }
                if (!$member) {
                    $identifier = isset($colIndex['email']) ? ($row[$colIndex['email']] ?? '') : ($row[$colIndex['name']] ?? '');
                    $errors[] = ['line' => $lineNumber, 'error' => "Membre introuvable: {$identifier}"]; $skipped++; continue;
                }

                $mode = 'present';
                if (isset($colIndex['mode'])) {
                    $modeRaw = trim($row[$colIndex['mode']] ?? '');
                    $parsedMode = self::parseAttendanceMode($modeRaw);
                    if ($parsedMode === null && $modeRaw !== '') {
                        $errors[] = ['line' => $lineNumber, 'error' => "Mode invalide: {$modeRaw}"]; $skipped++; continue;
                    }
                    $mode = $parsedMode ?? 'present';
                }

                $notes = null;
                if (isset($colIndex['notes'])) $notes = trim($row[$colIndex['notes']] ?? '') ?: null;

                if ($dryRun) {
                    $preview[] = ['line' => $lineNumber, 'member_id' => $member['id'], 'member_name' => $member['full_name'], 'mode' => $mode, 'notes' => $notes];
                } else {
                    $attendanceRepo->upsert($tenantId, $meetingId, $member['id'], $mode, (float)($member['voting_power'] ?? 1), $notes);
                }
                $imported++;
            }
            if (!$dryRun) db()->commit();
        } catch (\Throwable $e) {
            if (!$dryRun) db()->rollBack();
            fclose($handle);
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        fclose($handle);

        if (!$dryRun && $imported > 0) {
            audit_log('attendances_import', 'attendance', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) $response['preview'] = array_slice($preview, 0, 50);
        api_ok($response);
    }

    public function attendancesXlsx(): void
    {
        api_rate_limit('xlsx_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        $meetingId = trim($_POST['meeting_id'] ?? '');
        if (!api_is_uuid($meetingId)) api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);

        $tenantId = api_current_tenant_id();
        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) api_fail('meeting_not_found', 404);
        if (in_array($meeting['status'], ['validated', 'archived'], true)) {
            api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
        }

        $dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $file = $_FILES['file'] ?? $_FILES['xlsx_file'] ?? null;
        if (!$file) api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']);

        $validation = ImportService::validateUploadedFile($file, 'xlsx');
        if (!$validation['ok']) api_fail('invalid_file', 400, ['detail' => $validation['error']]);

        $result = ImportService::readXlsxFile($file['tmp_name']);
        if ($result['error']) api_fail('file_read_error', 400, ['detail' => $result['error']]);

        $headers = $result['headers'];
        $rows = $result['rows'];

        $columnMap = ImportService::getAttendancesColumnMap();
        $colIndex = ImportService::mapColumns($headers, $columnMap);

        if (!isset($colIndex['name']) && !isset($colIndex['email'])) {
            api_fail('missing_identifier', 400, ['detail' => 'Colonne "name", "nom" ou "email" requise.', 'found' => $headers]);
        }

        $memberRepo = new MemberRepository();
        $attendanceRepo = new AttendanceRepository();

        $allMembers = $memberRepo->listByTenant($tenantId);
        $membersByEmail = []; $membersByName = [];
        foreach ($allMembers as $m) {
            if (!empty($m['email'])) $membersByEmail[strtolower($m['email'])] = $m;
            $membersByName[mb_strtolower($m['full_name'])] = $m;
        }

        $imported = 0; $skipped = 0; $errors = []; $preview = [];

        if (!$dryRun) db()->beginTransaction();

        try {
            foreach ($rows as $lineIndex => $row) {
                $lineNumber = $lineIndex + 2;

                $member = null;
                if (isset($colIndex['email'])) {
                    $email = strtolower(trim($row[$colIndex['email']] ?? ''));
                    if ($email !== '' && isset($membersByEmail[$email])) $member = $membersByEmail[$email];
                }
                if (!$member && isset($colIndex['name'])) {
                    $name = mb_strtolower(trim($row[$colIndex['name']] ?? ''));
                    if ($name !== '' && isset($membersByName[$name])) $member = $membersByName[$name];
                }
                if (!$member) {
                    $identifier = isset($colIndex['email']) ? ($row[$colIndex['email']] ?? '') : ($row[$colIndex['name']] ?? '');
                    $errors[] = ['line' => $lineNumber, 'error' => "Membre introuvable: {$identifier}"]; $skipped++; continue;
                }

                $mode = 'present';
                if (isset($colIndex['mode'])) {
                    $modeRaw = trim($row[$colIndex['mode']] ?? '');
                    $parsedMode = ImportService::parseAttendanceMode($modeRaw);
                    if ($parsedMode === null && $modeRaw !== '') {
                        $errors[] = ['line' => $lineNumber, 'error' => "Mode invalide: {$modeRaw}"]; $skipped++; continue;
                    }
                    $mode = $parsedMode ?? 'present';
                }

                $notes = null;
                if (isset($colIndex['notes'])) $notes = trim($row[$colIndex['notes']] ?? '') ?: null;

                if ($dryRun) {
                    $preview[] = ['line' => $lineNumber, 'member_id' => $member['id'], 'member_name' => $member['full_name'], 'mode' => $mode, 'notes' => $notes];
                } else {
                    $attendanceRepo->upsert($tenantId, $meetingId, $member['id'], $mode, (float)($member['voting_power'] ?? 1), $notes);
                }
                $imported++;
            }
            if (!$dryRun) db()->commit();
        } catch (\Throwable $e) {
            if (!$dryRun) db()->rollBack();
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        if (!$dryRun && $imported > 0) {
            audit_log('attendances_import_xlsx', 'attendance', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) $response['preview'] = array_slice($preview, 0, 50);
        api_ok($response);
    }

    public function proxiesCsv(): void
    {
        api_rate_limit('csv_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        $meetingId = trim($_POST['meeting_id'] ?? '');
        if (!api_is_uuid($meetingId)) api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);

        $tenantId = api_current_tenant_id();
        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) api_fail('meeting_not_found', 404);
        if (in_array($meeting['status'], ['validated', 'archived'], true)) {
            api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
        }

        $dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $maxProxiesPerReceiver = (int)(getenv('PROXY_MAX_PER_RECEIVER') ?: 3);

        $file = $_FILES['file'] ?? $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) api_fail('upload_error', 400, ['detail' => 'Fichier manquant ou erreur upload.']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') api_fail('invalid_file_type', 400, ['detail' => 'Seuls les fichiers CSV sont acceptés.']);
        if ($file['size'] > 5 * 1024 * 1024) api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            api_fail('invalid_mime_type', 400, ['detail' => "Type non autorisé: {$mime}"]);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) api_fail('file_read_error', 500);

        $firstLine = fgets($handle);
        rewind($handle);
        $separator = strpos($firstLine, ';') !== false ? ';' : ',';

        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) { fclose($handle); api_fail('invalid_csv', 400, ['detail' => 'En-têtes CSV invalides.']); }
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $columnMap = [
            'giver_name' => ['giver_name', 'mandant_nom', 'mandant', 'donneur', 'donneur_nom', 'from_name', 'de'],
            'giver_email' => ['giver_email', 'mandant_email', 'donneur_email', 'from_email'],
            'receiver_name' => ['receiver_name', 'mandataire_nom', 'mandataire', 'receveur', 'receveur_nom', 'to_name', 'vers'],
            'receiver_email' => ['receiver_email', 'mandataire_email', 'receveur_email', 'to_email'],
        ];
        $colIndex = self::mapColumns($headers, $columnMap);

        $hasGiver = isset($colIndex['giver_name']) || isset($colIndex['giver_email']);
        $hasReceiver = isset($colIndex['receiver_name']) || isset($colIndex['receiver_email']);
        if (!$hasGiver || !$hasReceiver) {
            fclose($handle);
            api_fail('missing_columns', 400, ['detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).', 'found' => $headers]);
        }

        $memberRepo = new MemberRepository();
        $proxyRepo = new ProxyRepository();

        $allMembers = $memberRepo->listByTenant($tenantId);
        $membersByEmail = []; $membersByName = [];
        foreach ($allMembers as $m) {
            if (!empty($m['email'])) $membersByEmail[strtolower($m['email'])] = $m;
            $membersByName[mb_strtolower($m['full_name'])] = $m;
        }

        $existingProxies = $proxyRepo->listForMeeting($meetingId, $tenantId);
        $proxiesPerReceiver = []; $existingGivers = [];
        foreach ($existingProxies as $p) {
            $proxiesPerReceiver[$p['receiver_member_id']] = ($proxiesPerReceiver[$p['receiver_member_id']] ?? 0) + 1;
            $existingGivers[$p['giver_member_id']] = $p['receiver_member_id'];
        }

        $findMember = function(array $row, string $nameField, string $emailField) use ($colIndex, $membersByEmail, $membersByName): ?array {
            if (isset($colIndex[$emailField])) {
                $email = strtolower(trim($row[$colIndex[$emailField]] ?? ''));
                if ($email !== '' && isset($membersByEmail[$email])) return $membersByEmail[$email];
            }
            if (isset($colIndex[$nameField])) {
                $name = mb_strtolower(trim($row[$colIndex[$nameField]] ?? ''));
                if ($name !== '' && isset($membersByName[$name])) return $membersByName[$name];
            }
            return null;
        };

        $imported = 0; $skipped = 0; $errors = []; $lineNumber = 1; $preview = [];
        $tempProxiesPerReceiver = $proxiesPerReceiver;
        $tempExistingGivers = $existingGivers;

        if (!$dryRun) db()->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                $lineNumber++;
                if (empty(array_filter($row))) continue;

                $giver = $findMember($row, 'giver_name', 'giver_email');
                if (!$giver) {
                    $identifier = $row[$colIndex['giver_email'] ?? $colIndex['giver_name'] ?? 0] ?? 'inconnu';
                    $errors[] = ['line' => $lineNumber, 'error' => "Mandant introuvable: {$identifier}"]; $skipped++; continue;
                }

                $receiver = $findMember($row, 'receiver_name', 'receiver_email');
                if (!$receiver) {
                    $identifier = $row[$colIndex['receiver_email'] ?? $colIndex['receiver_name'] ?? 0] ?? 'inconnu';
                    $errors[] = ['line' => $lineNumber, 'error' => "Mandataire introuvable: {$identifier}"]; $skipped++; continue;
                }

                if ($giver['id'] === $receiver['id']) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Auto-délégation interdite']; $skipped++; continue;
                }
                if (isset($tempExistingGivers[$giver['id']])) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Le mandant {$giver['full_name']} a déjà une procuration active"]; $skipped++; continue;
                }
                if (isset($tempExistingGivers[$receiver['id']])) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Chaîne de procuration interdite: {$receiver['full_name']} est déjà mandant"]; $skipped++; continue;
                }

                $currentCount = $tempProxiesPerReceiver[$receiver['id']] ?? 0;
                if ($currentCount >= $maxProxiesPerReceiver) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Plafond atteint: {$receiver['full_name']} a déjà {$currentCount} procurations (max: {$maxProxiesPerReceiver})"]; $skipped++; continue;
                }

                if ($dryRun) {
                    $preview[] = ['line' => $lineNumber, 'giver_id' => $giver['id'], 'giver_name' => $giver['full_name'], 'receiver_id' => $receiver['id'], 'receiver_name' => $receiver['full_name']];
                    $tempProxiesPerReceiver[$receiver['id']] = $currentCount + 1;
                    $tempExistingGivers[$giver['id']] = $receiver['id'];
                } else {
                    $proxyRepo->upsertProxy($tenantId, $meetingId, $giver['id'], $receiver['id']);
                    $proxiesPerReceiver[$receiver['id']] = $currentCount + 1;
                    $existingGivers[$giver['id']] = $receiver['id'];
                }
                $imported++;
            }
            if (!$dryRun) db()->commit();
        } catch (\Throwable $e) {
            if (!$dryRun) db()->rollBack();
            fclose($handle);
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        fclose($handle);

        if (!$dryRun && $imported > 0) {
            audit_log('proxies_import', 'proxy', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun, 'max_proxies_per_receiver' => $maxProxiesPerReceiver];
        if ($dryRun) $response['preview'] = array_slice($preview, 0, 50);
        api_ok($response);
    }

    public function proxiesXlsx(): void
    {
        api_rate_limit('xlsx_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        $meetingId = trim($_POST['meeting_id'] ?? '');
        if (!api_is_uuid($meetingId)) api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);

        $tenantId = api_current_tenant_id();
        $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) api_fail('meeting_not_found', 404);
        if (in_array($meeting['status'], ['validated', 'archived'], true)) {
            api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
        }

        $dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $maxProxiesPerReceiver = (int)(getenv('PROXY_MAX_PER_RECEIVER') ?: 3);

        $file = $_FILES['file'] ?? $_FILES['xlsx_file'] ?? null;
        if (!$file) api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']);

        $validation = ImportService::validateUploadedFile($file, 'xlsx');
        if (!$validation['ok']) api_fail('invalid_file', 400, ['detail' => $validation['error']]);

        $result = ImportService::readXlsxFile($file['tmp_name']);
        if ($result['error']) api_fail('file_read_error', 400, ['detail' => $result['error']]);

        $headers = $result['headers'];
        $rows = $result['rows'];

        $columnMap = ImportService::getProxiesColumnMap();
        $colIndex = ImportService::mapColumns($headers, $columnMap);

        $hasGiver = isset($colIndex['giver_name']) || isset($colIndex['giver_email']);
        $hasReceiver = isset($colIndex['receiver_name']) || isset($colIndex['receiver_email']);
        if (!$hasGiver || !$hasReceiver) {
            api_fail('missing_columns', 400, ['detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).', 'found' => $headers]);
        }

        $memberRepo = new MemberRepository();
        $proxyRepo = new ProxyRepository();

        $allMembers = $memberRepo->listByTenant($tenantId);
        $membersByEmail = []; $membersByName = [];
        foreach ($allMembers as $m) {
            if (!empty($m['email'])) $membersByEmail[strtolower($m['email'])] = $m;
            $membersByName[mb_strtolower($m['full_name'])] = $m;
        }

        $existingProxies = $proxyRepo->listForMeeting($meetingId, $tenantId);
        $proxiesPerReceiver = []; $existingGivers = [];
        foreach ($existingProxies as $p) {
            $proxiesPerReceiver[$p['receiver_member_id']] = ($proxiesPerReceiver[$p['receiver_member_id']] ?? 0) + 1;
            $existingGivers[$p['giver_member_id']] = $p['receiver_member_id'];
        }

        $findMember = function(array $row, string $nameField, string $emailField) use ($colIndex, $membersByEmail, $membersByName): ?array {
            if (isset($colIndex[$emailField])) {
                $email = strtolower(trim($row[$colIndex[$emailField]] ?? ''));
                if ($email !== '' && isset($membersByEmail[$email])) return $membersByEmail[$email];
            }
            if (isset($colIndex[$nameField])) {
                $name = mb_strtolower(trim($row[$colIndex[$nameField]] ?? ''));
                if ($name !== '' && isset($membersByName[$name])) return $membersByName[$name];
            }
            return null;
        };

        $imported = 0; $skipped = 0; $errors = []; $preview = [];
        $tempProxiesPerReceiver = $proxiesPerReceiver;
        $tempExistingGivers = $existingGivers;

        if (!$dryRun) db()->beginTransaction();

        try {
            foreach ($rows as $lineIndex => $row) {
                $lineNumber = $lineIndex + 2;

                $giver = $findMember($row, 'giver_name', 'giver_email');
                if (!$giver) {
                    $identifier = $row[$colIndex['giver_email'] ?? $colIndex['giver_name'] ?? 0] ?? 'inconnu';
                    $errors[] = ['line' => $lineNumber, 'error' => "Mandant introuvable: {$identifier}"]; $skipped++; continue;
                }

                $receiver = $findMember($row, 'receiver_name', 'receiver_email');
                if (!$receiver) {
                    $identifier = $row[$colIndex['receiver_email'] ?? $colIndex['receiver_name'] ?? 0] ?? 'inconnu';
                    $errors[] = ['line' => $lineNumber, 'error' => "Mandataire introuvable: {$identifier}"]; $skipped++; continue;
                }

                if ($giver['id'] === $receiver['id']) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Auto-délégation interdite']; $skipped++; continue;
                }
                if (isset($tempExistingGivers[$giver['id']])) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Le mandant {$giver['full_name']} a déjà une procuration active"]; $skipped++; continue;
                }
                if (isset($tempExistingGivers[$receiver['id']])) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Chaîne de procuration interdite: {$receiver['full_name']} est déjà mandant"]; $skipped++; continue;
                }

                $currentCount = $tempProxiesPerReceiver[$receiver['id']] ?? 0;
                if ($currentCount >= $maxProxiesPerReceiver) {
                    $errors[] = ['line' => $lineNumber, 'error' => "Plafond atteint: {$receiver['full_name']} a déjà {$currentCount} procurations (max: {$maxProxiesPerReceiver})"]; $skipped++; continue;
                }

                if ($dryRun) {
                    $preview[] = ['line' => $lineNumber, 'giver_id' => $giver['id'], 'giver_name' => $giver['full_name'], 'receiver_id' => $receiver['id'], 'receiver_name' => $receiver['full_name']];
                    $tempProxiesPerReceiver[$receiver['id']] = $currentCount + 1;
                    $tempExistingGivers[$giver['id']] = $receiver['id'];
                } else {
                    $proxyRepo->upsertProxy($tenantId, $meetingId, $giver['id'], $receiver['id']);
                    $proxiesPerReceiver[$receiver['id']] = $currentCount + 1;
                    $existingGivers[$giver['id']] = $receiver['id'];
                }
                $imported++;
            }
            if (!$dryRun) db()->commit();
        } catch (\Throwable $e) {
            if (!$dryRun) db()->rollBack();
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        if (!$dryRun && $imported > 0) {
            audit_log('proxies_import_xlsx', 'proxy', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun, 'max_proxies_per_receiver' => $maxProxiesPerReceiver];
        if ($dryRun) $response['preview'] = array_slice($preview, 0, 50);
        api_ok($response);
    }

    public function motionsCsv(): void
    {
        api_rate_limit('csv_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        $meetingId = trim($_POST['meeting_id'] ?? '');
        if (!api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);
        }

        $tenantId = api_current_tenant_id();
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) api_fail('meeting_not_found', 404);

        if (in_array($meeting['status'], ['validated', 'archived'], true)) {
            api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
        }

        $dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $file = $_FILES['file'] ?? $_FILES['csv_file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            api_fail('upload_error', 400, ['detail' => 'Fichier manquant ou erreur upload.']);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') api_fail('invalid_file_type', 400, ['detail' => 'Seuls les fichiers CSV sont acceptés.']);
        if ($file['size'] > 5 * 1024 * 1024) api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']);

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], true)) {
            api_fail('invalid_mime_type', 400, ['detail' => "Type non autorisé: {$mime}"]);
        }

        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) api_fail('file_read_error', 500, ['detail' => 'Impossible d\'ouvrir le fichier CSV']);

        $firstLine = fgets($handle);
        rewind($handle);
        $separator = strpos($firstLine, ';') !== false ? ';' : ',';

        $headers = fgetcsv($handle, 0, $separator);
        if (!$headers) { fclose($handle); api_fail('invalid_csv', 400, ['detail' => 'En-têtes CSV invalides.']); }
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        $columnMap = [
            'title' => ['title', 'titre', 'intitule', 'intitulé', 'resolution', 'résolution'],
            'description' => ['description', 'texte', 'content', 'contenu', 'detail', 'détail'],
            'position' => ['position', 'ordre', 'order', 'rang', 'index'],
            'secret' => ['secret', 'vote_secret', 'secret_vote', 'scrutin_secret'],
        ];
        $colIndex = self::mapColumns($headers, $columnMap);

        if (!isset($colIndex['title'])) {
            fclose($handle);
            api_fail('missing_title_column', 400, ['detail' => 'Colonne "title" ou "titre" requise.', 'found' => $headers]);
        }

        $motionRepo = new MotionRepository();
        $existingMotions = $motionRepo->countForMeeting($meetingId);
        $nextPosition = $existingMotions + 1;

        $imported = 0; $skipped = 0; $errors = []; $lineNumber = 1; $preview = [];

        if (!$dryRun) db()->beginTransaction();

        try {
            while (($row = fgetcsv($handle, 0, $separator)) !== false) {
                $lineNumber++;
                if (empty(array_filter($row))) continue;

                $title = trim($row[$colIndex['title']] ?? '');
                $description = isset($colIndex['description']) ? trim($row[$colIndex['description']] ?? '') : null;

                $position = null;
                if (isset($colIndex['position'])) {
                    $posVal = trim($row[$colIndex['position']] ?? '');
                    if ($posVal !== '' && is_numeric($posVal)) $position = (int)$posVal;
                }
                if ($position === null) {
                    $position = $nextPosition++;
                } else {
                    $nextPosition = max($nextPosition, $position + 1);
                }

                $secret = false;
                if (isset($colIndex['secret'])) {
                    $secretVal = strtolower(trim($row[$colIndex['secret']] ?? '0'));
                    $secret = in_array($secretVal, ['1', 'true', 'oui', 'yes', 'secret'], true);
                }

                if (empty($title) || mb_strlen($title) < 2) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Titre invalide ou trop court']; $skipped++; continue;
                }
                if (mb_strlen($title) > 500) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Titre trop long (max 500 caractères)']; $skipped++; continue;
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
            if (!$dryRun) db()->commit();
        } catch (\Throwable $e) {
            if (!$dryRun) db()->rollBack();
            fclose($handle);
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        fclose($handle);

        if (!$dryRun && $imported > 0) {
            audit_log('motions_import', 'motion', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) $response['preview'] = array_slice($preview, 0, 50);
        api_ok($response);
    }

    public function motionsXlsx(): void
    {
        api_rate_limit('xlsx_import', 10, 3600);
        api_require_role(['operator', 'admin']);
        api_request('POST');

        $meetingId = trim($_POST['meeting_id'] ?? '');
        if (!api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']);
        }

        $tenantId = api_current_tenant_id();
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) api_fail('meeting_not_found', 404);

        if (in_array($meeting['status'], ['validated', 'archived'], true)) {
            api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']);
        }

        $dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $file = $_FILES['file'] ?? $_FILES['xlsx_file'] ?? null;
        if (!$file) api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']);

        $validation = ImportService::validateUploadedFile($file, 'xlsx');
        if (!$validation['ok']) api_fail('invalid_file', 400, ['detail' => $validation['error']]);

        $result = ImportService::readXlsxFile($file['tmp_name']);
        if ($result['error']) api_fail('file_read_error', 400, ['detail' => $result['error']]);

        $headers = $result['headers'];
        $rows = $result['rows'];

        $columnMap = ImportService::getMotionsColumnMap();
        $colIndex = ImportService::mapColumns($headers, $columnMap);

        if (!isset($colIndex['title'])) {
            api_fail('missing_title_column', 400, ['detail' => 'Colonne "title" ou "titre" requise.', 'found' => $headers]);
        }

        $motionRepo = new MotionRepository();
        $existingMotions = $motionRepo->countForMeeting($meetingId);
        $nextPosition = $existingMotions + 1;

        $imported = 0; $skipped = 0; $errors = []; $preview = [];

        if (!$dryRun) db()->beginTransaction();

        try {
            foreach ($rows as $lineIndex => $row) {
                $lineNumber = $lineIndex + 2;

                $title = trim($row[$colIndex['title']] ?? '');
                $description = isset($colIndex['description']) ? trim($row[$colIndex['description']] ?? '') : null;

                $position = null;
                if (isset($colIndex['position'])) {
                    $posVal = trim($row[$colIndex['position']] ?? '');
                    if ($posVal !== '' && is_numeric($posVal)) $position = (int)$posVal;
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
                    $errors[] = ['line' => $lineNumber, 'error' => 'Titre invalide ou trop court']; $skipped++; continue;
                }
                if (mb_strlen($title) > 500) {
                    $errors[] = ['line' => $lineNumber, 'error' => 'Titre trop long (max 500 caractères)']; $skipped++; continue;
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
            if (!$dryRun) db()->commit();
        } catch (\Throwable $e) {
            if (!$dryRun) db()->rollBack();
            api_fail('import_failed', 500, ['detail' => $e->getMessage()]);
        }

        if (!$dryRun && $imported > 0) {
            audit_log('motions_import_xlsx', 'motion', $meetingId, [
                'imported' => $imported, 'skipped' => $skipped, 'filename' => $file['name'],
            ], $meetingId);
        }

        $response = ['imported' => $imported, 'skipped' => $skipped, 'errors' => array_slice($errors, 0, 20), 'dry_run' => $dryRun];
        if ($dryRun) $response['preview'] = array_slice($preview, 0, 50);
        api_ok($response);
    }

    // =========================================================================
    // Shared helpers
    // =========================================================================

    private static function mapColumns(array $headers, array $columnMap): array
    {
        $colIndex = [];
        foreach ($columnMap as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $headers, true);
                if ($idx !== false) {
                    $colIndex[$field] = $idx;
                    break;
                }
            }
        }
        return $colIndex;
    }

    private static function parseAttendanceMode(string $val): ?string
    {
        $val = mb_strtolower(trim($val));

        if (in_array($val, ['present', 'présent', 'p', '1', 'oui', 'yes'], true)) return 'present';
        if (in_array($val, ['remote', 'distant', 'd', 'distanciel', 'visio'], true)) return 'remote';
        if (in_array($val, ['excused', 'excusé', 'excuse', 'e', 'excusée'], true)) return 'excused';
        if (in_array($val, ['absent', 'a', '0', 'non', 'no', ''], true)) return 'absent';
        if (in_array($val, ['proxy', 'procuration', 'mandataire'], true)) return 'proxy';

        return null;
    }
}
