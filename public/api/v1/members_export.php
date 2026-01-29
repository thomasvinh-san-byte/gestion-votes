<?php
declare(strict_types=1);

/**
 * members_export.php - Export CSV des membres
 * 
 * GET /api/v1/members_export.php?meeting_id={uuid}
 */

require __DIR__ . '/export_members_csv.php';
