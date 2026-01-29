<?php
declare(strict_types=1);

/**
 * attendance_export.php - Export CSV des présences
 * 
 * GET /api/v1/attendance_export.php?meeting_id={uuid}
 * 
 * Alias de export_attendance_csv.php pour cohérence de nommage.
 */

require __DIR__ . '/export_attendance_csv.php';
