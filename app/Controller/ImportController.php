<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Service\ImportService;

final class ImportController extends AbstractController {
    private ?ImportService $importService = null;
    private function importService(): ImportService { return $this->importService ??= new ImportService($this->repo()); }

    public function membersCsv(): void {
        $in = api_request('POST'); $file = api_file('file', 'csv_file'); $csv = $in['csv_content'] ?? null;
        if (!$file && !$csv) { $j = json_decode(file_get_contents('php://input'), true); $csv = $j['csv_content'] ?? null; }
        [$h, $rw] = $this->readCsvOrContent($file, $csv);
        $this->runMembersImport($h, $rw, $file['name'] ?? 'csv_content', 'members_import');
    }

    public function membersXlsx(): void {
        api_request('POST'); [$h, $rw] = $this->readImportFile('xlsx');
        $this->runMembersImport($h, $rw, api_file('file', 'xlsx_file')['name'] ?? 'upload.xlsx', 'members_import_xlsx');
    }

    public function attendancesCsv(): void {
        $in = api_request('POST'); [$mid] = $this->requireWritableMeeting($in); [$h, $rw] = $this->readImportFile('csv');
        $this->runAttendancesImport($h, $rw, $mid, filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN), api_file('file', 'csv_file')['name'] ?? '', 'attendances_import');
    }

    public function attendancesXlsx(): void {
        $in = api_request('POST'); [$mid] = $this->requireWritableMeeting($in); [$h, $rw] = $this->readImportFile('xlsx');
        $this->runAttendancesImport($h, $rw, $mid, filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN), api_file('file', 'xlsx_file')['name'] ?? '', 'attendances_import_xlsx');
    }

    public function proxiesCsv(): void {
        $in = api_request('POST'); [$mid] = $this->requireWritableMeeting($in);
        $dry = filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN); $maxPPR = (int) config('proxy_max_per_receiver', 3);
        $file = api_file('file', 'csv_file'); [$h, $rw] = $this->readCsvOrContent($file, $in['csv_content'] ?? null);
        $this->runProxiesImport($h, $rw, $mid, $dry, $maxPPR, $file ? ($file['name'] ?? 'upload.csv') : 'csv_content', 'proxies_import');
    }

    public function proxiesXlsx(): void {
        $in = api_request('POST'); [$mid] = $this->requireWritableMeeting($in); [$h, $rw] = $this->readImportFile('xlsx');
        $this->runProxiesImport($h, $rw, $mid, filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN), (int) config('proxy_max_per_receiver', 3), api_file('file', 'xlsx_file')['name'] ?? '', 'proxies_import_xlsx');
    }

    public function motionsCsv(): void {
        $in = api_request('POST'); [$mid] = $this->requireWritableMeeting($in); [$h, $rw] = $this->readImportFile('csv');
        $this->runMotionsImport($h, $rw, $mid, filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN), api_file('file', 'csv_file')['name'] ?? '', 'motions_import');
    }

    public function motionsXlsx(): void {
        $in = api_request('POST'); [$mid] = $this->requireWritableMeeting($in); [$h, $rw] = $this->readImportFile('xlsx');
        $this->runMotionsImport($h, $rw, $mid, filter_var($in['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN), api_file('file', 'xlsx_file')['name'] ?? '', 'motions_import_xlsx');
    }

    private function runMembersImport(array $h, array $rw, string $fn, string $ev): void {
        $ci = ImportService::mapColumns($h, ImportService::getMembersColumnMap());
        $hn = isset($ci['name']); $hfl = isset($ci['first_name']) && isset($ci['last_name']);
        if (!$hn && !$hfl) { api_fail('missing_name_column', 400, ['detail' => 'Colonne "name" ou "first_name"+"last_name" requise.', 'found' => $h]); }
        if (!empty($d = ImportService::checkDuplicateEmails($rw, $ci))) { api_fail('duplicate_emails', 422, ['detail' => 'Le fichier contient des adresses email en double.', 'duplicate_emails' => $d]); }
        $tid = api_current_tenant_id(); $res = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        self::wrapApiCall(function () use ($rw, $ci, $hn, $hfl, $tid, &$res) {
            api_transaction(function () use ($rw, $ci, $hn, $hfl, $tid, &$res) { $this->mergeResult($res, $this->importService()->processMemberImport($rw, $ci, $hn, $hfl, $tid)); });
        }, 'import_failed');
        audit_log($ev, 'member', null, ['imported' => $res['imported'], 'skipped' => $res['skipped'], 'filename' => $fn]);
        api_ok(['imported' => $res['imported'], 'skipped' => $res['skipped'], 'errors' => array_slice($res['errors'], 0, 20)]);
    }

    private function runAttendancesImport(array $h, array $rw, string $mid, bool $dry, string $fn, string $ev): void {
        $ci = ImportService::mapColumns($h, ImportService::getAttendancesColumnMap());
        if (!isset($ci['name']) && !isset($ci['email'])) { api_fail('missing_identifier', 400, ['detail' => 'Colonne "name", "nom" ou "email" requise.', 'found' => $h]); }
        $tid = api_current_tenant_id(); $res = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'preview' => []];
        self::wrapApiCall(function () use ($rw, $ci, $tid, $mid, $dry, &$res) {
            $work = function () use ($rw, $ci, $tid, $mid, $dry, &$res) { $this->mergeResult($res, $this->importService()->processAttendanceImport($rw, $ci, $tid, $mid, $dry)); };
            $dry ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dry && $res['imported'] > 0) { audit_log($ev, 'attendance', $mid, ['imported' => $res['imported'], 'skipped' => $res['skipped'], 'filename' => $fn], $mid); }
        $out = ['imported' => $res['imported'], 'skipped' => $res['skipped'], 'errors' => array_slice($res['errors'], 0, 20), 'dry_run' => $dry];
        if ($dry) { $out['preview'] = array_slice($res['preview'], 0, 50); } api_ok($out);
    }

    private function runProxiesImport(array $h, array $rw, string $mid, bool $dry, int $maxPPR, string $fn, string $ev): void {
        $ci = ImportService::mapColumns($h, ImportService::getProxiesColumnMap());
        if (!(isset($ci['giver_name']) || isset($ci['giver_email'])) || !(isset($ci['receiver_name']) || isset($ci['receiver_email']))) { api_fail('missing_columns', 400, ['detail' => 'Colonnes requises: (giver_name OU giver_email) ET (receiver_name OU receiver_email).', 'found' => $h]); }
        $tid = api_current_tenant_id(); $ppr = []; $eg = [];
        foreach ($this->repo()->proxy()->listForMeeting($mid, $tid) as $p) { $ppr[$p['receiver_member_id']] = ($ppr[$p['receiver_member_id']] ?? 0) + 1; $eg[$p['giver_member_id']] = $p['receiver_member_id']; }
        $res = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'preview' => []];
        self::wrapApiCall(function () use ($rw, $ci, $tid, $mid, $dry, $maxPPR, &$ppr, &$eg, &$res) {
            $work = function () use ($rw, $ci, $tid, $mid, $dry, $maxPPR, &$ppr, &$eg, &$res) { $this->mergeResult($res, $this->importService()->processProxyImport($rw, $ci, $tid, $mid, $dry, $maxPPR, $ppr, $eg)); };
            $dry ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dry && $res['imported'] > 0) { audit_log($ev, 'proxy', $mid, ['imported' => $res['imported'], 'skipped' => $res['skipped'], 'filename' => $fn], $mid); }
        $out = ['imported' => $res['imported'], 'skipped' => $res['skipped'], 'errors' => array_slice($res['errors'], 0, 20), 'dry_run' => $dry, 'max_proxies_per_receiver' => $maxPPR];
        if ($dry) { $out['preview'] = array_slice($res['preview'], 0, 50); } api_ok($out);
    }

    private function runMotionsImport(array $h, array $rw, string $mid, bool $dry, string $fn, string $ev): void {
        $ci = ImportService::mapColumns($h, ImportService::getMotionsColumnMap());
        if (!isset($ci['title'])) { api_fail('missing_title_column', 400, ['detail' => 'Colonne "title" ou "titre" requise.', 'found' => $h]); }
        $tid = api_current_tenant_id(); $npos = $this->repo()->motion()->countForMeeting($mid, $tid) + 1;
        $res = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'preview' => []];
        self::wrapApiCall(function () use ($rw, $ci, $tid, $mid, $dry, &$npos, &$res) {
            $work = function () use ($rw, $ci, $tid, $mid, $dry, &$npos, &$res) { $this->mergeResult($res, $this->importService()->processMotionImport($rw, $ci, $tid, $mid, $dry, $npos)); };
            $dry ? $work() : api_transaction($work);
        }, 'import_failed');
        if (!$dry && $res['imported'] > 0) { audit_log($ev, 'motion', $mid, ['imported' => $res['imported'], 'skipped' => $res['skipped'], 'filename' => $fn], $mid); }
        $out = ['imported' => $res['imported'], 'skipped' => $res['skipped'], 'errors' => array_slice($res['errors'], 0, 20), 'dry_run' => $dry];
        if ($dry) { $out['preview'] = array_slice($res['preview'], 0, 50); } api_ok($out);
    }

    private function mergeResult(array &$res, array $x): void {
        $res['imported'] += $x['imported']; $res['skipped'] += $x['skipped']; $res['errors'] = array_merge($res['errors'], $x['errors']);
        if (isset($x['preview'])) { $res['preview'] = array_merge($res['preview'] ?? [], $x['preview']); }
    }

    private function readCsvOrContent(?array $file, mixed $csv): array {
        if ($file && $file['error'] === UPLOAD_ERR_OK) {
            if (($file['size'] ?? 0) > 10485760) { api_fail('file_too_large', 400, ['detail' => 'Fichier trop volumineux. Maximum 10 Mo.']); }
            $v = ImportService::validateUploadedFile($file, 'csv'); if (!$v['ok']) { api_fail('invalid_file', 400, ['detail' => $v['error']]); }
            $r = ImportService::readCsvFile($file['tmp_name']); if ($r['error']) { api_fail('file_read_error', 400, ['detail' => $r['error']]); }
        } elseif ($csv && is_string($csv) && strlen($csv) > 0) {
            if (strlen($csv) > 5242880) { api_fail('file_too_large', 400, ['detail' => 'Max 5 MB.']); }
            $t = tempnam(sys_get_temp_dir(), 'csv_'); chmod($t, 0600); file_put_contents($t, $csv);
            try { $r = ImportService::readCsvFile($t); } finally { unlink($t); }
            if ($r['error']) { api_fail('file_read_error', 400, ['detail' => $r['error']]); }
        } else { api_fail('upload_error', 400, ['detail' => 'Fichier CSV manquant. Envoyez un fichier (file/csv_file) ou le contenu texte (csv_content).']); }
        return [$r['headers'], $r['rows']];
    }

    private function readImportFile(string $fmt): array {
        $file = api_file(...($fmt === 'csv' ? ['file', 'csv_file'] : ['file', 'xlsx_file']));
        if (!$file) { api_fail('upload_error', 400, ['detail' => 'Fichier manquant.']); }
        if (($file['size'] ?? 0) > 10485760) { api_fail('file_too_large', 400, ['detail' => 'Fichier trop volumineux. Maximum 10 Mo.']); }
        $v = ImportService::validateUploadedFile($file, $fmt); if (!$v['ok']) { api_fail('invalid_file', 400, ['detail' => $v['error']]); }
        $r = $fmt === 'csv' ? ImportService::readCsvFile($file['tmp_name']) : ImportService::readXlsxFile($file['tmp_name']);
        if ($r['error']) { api_fail('file_read_error', 400, ['detail' => $r['error']]); }
        return [$r['headers'], $r['rows']];
    }

    private function requireWritableMeeting(array $in): array {
        $mid = trim((string) ($in['meeting_id'] ?? ''));
        if (!api_is_uuid($mid)) { api_fail('missing_meeting_id', 422, ['detail' => 'meeting_id requis et valide.']); }
        $tid = api_current_tenant_id(); $meeting = $this->repo()->meeting()->findByIdForTenant($mid, $tid);
        if (!$meeting) { api_fail('meeting_not_found', 404); }
        if (in_array($meeting['status'], ['validated', 'archived'], true)) { api_fail('meeting_locked', 403, ['detail' => 'Séance validée ou archivée, modifications interdites.']); }
        return [$mid, $meeting];
    }
}
