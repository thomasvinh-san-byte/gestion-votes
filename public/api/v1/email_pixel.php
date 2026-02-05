<?php
declare(strict_types=1);

/**
 * Pixel tracking pour les ouvertures d'email.
 *
 * GET /email_pixel.php?id=X
 *
 * Retourne un GIF transparent 1x1 et enregistre l'evenement d'ouverture.
 */

require __DIR__ . '/../../../app/bootstrap.php';

use AgVote\Repository\InvitationRepository;
use AgVote\Repository\EmailEventRepository;

// Verifier si le tracking est active
$trackingEnabled = getenv('EMAIL_TRACKING_ENABLED') !== '0';

$invitationId = trim((string)($_GET['id'] ?? ''));

// Toujours retourner le pixel, meme si tracking desactive
function outputPixel(): never
{
    // GIF transparent 1x1
    $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

    header('Content-Type: image/gif');
    header('Content-Length: ' . strlen($gif));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo $gif;
    exit;
}

// Si pas d'ID ou tracking desactive, juste retourner le pixel
if ($invitationId === '' || !$trackingEnabled) {
    outputPixel();
}

// Valider le format UUID
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invitationId)) {
    outputPixel();
}

try {
    $invitationRepo = new InvitationRepository();
    $eventRepo = new EmailEventRepository();

    // Recuperer le tenant_id via repository
    $tenantId = $invitationRepo->findTenantById($invitationId);

    if ($tenantId !== null) {
        // Incrementer le compteur d'ouvertures
        $invitationRepo->incrementOpenCount($invitationId);

        // Logger l'evenement
        $eventRepo->logEvent(
            $tenantId,
            'opened',
            $invitationId,
            null,
            null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
    }
} catch (\Throwable $e) {
    // Silencieux - ne pas bloquer l'affichage de l'email
    error_log('Email pixel tracking error: ' . $e->getMessage());
}

outputPixel();
