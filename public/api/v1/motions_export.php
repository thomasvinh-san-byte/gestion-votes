<?php
declare(strict_types=1);

/**
 * motions_export.php - Export CSV des résolutions
 * 
 * GET /api/v1/motions_export.php?meeting_id={uuid}
 */

require __DIR__ . '/export_motions_results_csv.php';
