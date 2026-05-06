<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;

/**
 * XlsxImporter - XLSX file reading and proxy/motion import processing.
 *
 * Extracted from ImportService. Process methods are format-agnostic but placed
 * here for LOC distribution. Callers access them via ImportService delegation stubs.
 */
final class XlsxImporter {
    private ?RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /** Allowed MIME types for XLSX */
    public const XLSX_MIME_TYPES = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/octet-stream',
        'application/zip',
    ];

    // ========================================================================
    // XLSX READING
    // ========================================================================

    /**
     * Reads an XLSX file and returns rows as arrays.
     *
     * Backed by OpenSpout (streaming reader) — same dependency the export
     * path already uses. PhpSpreadsheet's in-memory model is replaced for
     * lower memory footprint on larger imports.
     *
     * Note: $sheetIndex is honoured by skipping iterator entries; OpenSpout
     * only exposes a sheet iterator, not random access.
     *
     * @return array{headers: array<int,string>, rows: array<int,array<int,string>>, error: ?string}
     */
    public static function readFile(string $filePath, int $sheetIndex = 0): array {
        $reader = new Reader();

        try {
            $reader->open($filePath);

            $headers = [];
            $rows = [];
            $rowIndex = 0;
            $currentSheet = -1;

            foreach ($reader->getSheetIterator() as $sheet) {
                $currentSheet++;
                if ($currentSheet !== $sheetIndex) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $rowData = [];
                    foreach ($row->toArray() as $value) {
                        // OpenSpout returns DateTime/numeric/bool natively; coerce to
                        // string to mirror the previous PhpSpreadsheet contract that
                        // downstream parsers (parseBoolean, parseVotingPower, …) rely on.
                        if ($value instanceof \DateTimeInterface) {
                            $rowData[] = $value->format('Y-m-d');
                        } elseif (is_bool($value)) {
                            $rowData[] = $value ? '1' : '0';
                        } else {
                            $rowData[] = $value !== null ? (string) $value : '';
                        }
                    }

                    if ($rowIndex === 0) {
                        $headers = array_map(fn ($h) => strtolower(trim($h)), $rowData);
                    } elseif (!empty(array_filter($rowData, fn ($v) => trim($v) !== ''))) {
                        $rows[] = $rowData;
                    }
                    $rowIndex++;
                }
                break; // only read the requested sheet
            }

            return [
                'headers' => $headers,
                'rows' => $rows,
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'headers' => [],
                'rows' => [],
                'error' => 'Erreur lecture fichier Excel: ' . $e->getMessage(),
            ];
        } finally {
            $reader->close();
        }
    }

    // ========================================================================
    // PROXY IMPORT PROCESSING
    // ========================================================================

    /**
     * Processes proxy import rows: creates proxies or builds preview with validation.
     *
     * @param array &$proxiesPerReceiver Running count of proxies per receiver ID
     * @param array &$existingGivers     Map of giver_id => receiver_id for already-assigned proxies
     *
     * @return array{imported: int, skipped: int, errors: array, preview: array}
     */
    public function processProxyImport(
        array $rows,
        array $colIndex,
        string $tenantId,
        string $meetingId,
        bool $dryRun,
        int $maxPerReceiver,
        array &$proxiesPerReceiver,
        array &$existingGivers,
    ): array {
        [$membersByEmail, $membersByName] = $this->buildMemberLookups($tenantId);
        $findMember = $this->buildProxyMemberFinder($colIndex, $membersByEmail, $membersByName);
        $proxyRepo = $this->repos->proxy();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

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
                $errors[] = ['line' => $lineNumber, 'error' => "Auto-d\xC3\xA9l\xC3\xA9gation interdite"];
                $skipped++;
                continue;
            }
            if (isset($existingGivers[$giver['id']])) {
                $errors[] = ['line' => $lineNumber, 'error' => "Le mandant {$giver['full_name']} a d\xC3\xA9j\xC3\xA0 une procuration active"];
                $skipped++;
                continue;
            }
            if (isset($existingGivers[$receiver['id']])) {
                $errors[] = ['line' => $lineNumber, 'error' => "Cha\xC3\xAEne de procuration interdite: {$receiver['full_name']} est d\xC3\xA9j\xC3\xA0 mandant"];
                $skipped++;
                continue;
            }

            $currentCount = $proxiesPerReceiver[$receiver['id']] ?? 0;
            if ($currentCount >= $maxPerReceiver) {
                $errors[] = ['line' => $lineNumber, 'error' => "Plafond atteint: {$receiver['full_name']} a d\xC3\xA9j\xC3\xA0 {$currentCount} procurations (max: {$maxPerReceiver})"];
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

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'preview' => $preview];
    }

    // ========================================================================
    // MOTION IMPORT PROCESSING
    // ========================================================================

    /**
     * Processes motion import rows: creates motions or builds preview.
     *
     * @param int &$nextPosition Running position counter (modified in place)
     *
     * @return array{imported: int, skipped: int, errors: array, preview: array}
     */
    public function processMotionImport(
        array $rows,
        array $colIndex,
        string $tenantId,
        string $meetingId,
        bool $dryRun = false,
        int &$nextPosition = 1,
    ): array {
        $motionRepo = $this->repos->motion();

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $preview = [];

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
                $errors[] = ['line' => $lineNumber, 'error' => "Titre trop long (max 500 caract\xC3\xA8res)"];
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

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors, 'preview' => $preview];
    }

    // ========================================================================
    // PRIVATE HELPERS
    // ========================================================================

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
}
