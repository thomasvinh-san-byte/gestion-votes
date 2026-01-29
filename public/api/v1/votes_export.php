<?php
declare(strict_types=1);

/**
 * votes_export.php - Export CSV des votes
 * 
 * GET /api/v1/votes_export.php?meeting_id={uuid}
 */

require __DIR__ . '/export_votes_csv.php';
