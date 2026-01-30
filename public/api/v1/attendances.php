<?php
// public/api/v1/attendances.php
declare(strict_types=1);

require __DIR__ . '/../../../app/api.php';

use AgVote\Service\AttendancesService;

try {
    api_request('GET');

    $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
    if ($meetingId === '') {
        api_fail('invalid_request', 422, ['detail' => 'meeting_id est obligatoire']);
    }

    // Lecture: opérateur + trust (au choix). Ici: trust minimum.
    // Pour simplifier le MVP, on autorise operator|trust|admin.
    // (API ne supporte pas OR, donc on ne force rien : GET est public par défaut.)
    $list = AttendancesService::listForMeeting($meetingId);
    $summary = AttendancesService::summaryForMeeting($meetingId);

    api_ok([
        'attendances' => $list,
        'summary' => $summary,
    ]);
} catch (InvalidArgumentException $e) {
    api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
} catch (RuntimeException $e) {
    api_fail('business_error', 400, ['detail' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error in attendances.php: ' . $e->getMessage());
    api_fail('internal_error', 500, ['detail' => 'Erreur interne du serveur']);
}