<?php
declare(strict_types=1);

/**
 * Redirect tracking pour les clics dans les emails.
 *
 * GET /email_redirect.php?id=X&url=Y
 *
 * Enregistre le clic et redirige vers l'URL cible.
 */

require __DIR__ . '/../../../app/bootstrap.php';

use AgVote\Repository\InvitationRepository;
use AgVote\Repository\EmailEventRepository;

$trackingEnabled = getenv('EMAIL_TRACKING_ENABLED') !== '0';

$invitationId = trim((string)($_GET['id'] ?? ''));
$targetUrl = trim((string)($_GET['url'] ?? ''));

// URL de fallback
$fallbackUrl = getenv('APP_URL') ?: 'http://localhost:8080';

// Si pas d'URL cible, rediriger vers l'app
if ($targetUrl === '') {
    header('Location: ' . $fallbackUrl, true, 302);
    exit;
}

// Valider l'URL (eviter les redirections malveillantes)
$parsedUrl = parse_url($targetUrl);
if (!$parsedUrl || !isset($parsedUrl['scheme']) || !in_array($parsedUrl['scheme'], ['http', 'https'], true)) {
    // URL invalide, rediriger vers le fallback
    header('Location: ' . $fallbackUrl, true, 302);
    exit;
}

// Traquer le clic si on a un ID valide et que le tracking est active
if ($invitationId !== '' && $trackingEnabled) {
    // Valider le format UUID
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $invitationId)) {
        try {
            $invitationRepo = new InvitationRepository();
            $eventRepo = new EmailEventRepository();

            // Recuperer le tenant_id
            $pdo = db();
            $stmt = $pdo->prepare("SELECT tenant_id FROM invitations WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $invitationId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $tenantId = $row['tenant_id'];

                // Incrementer le compteur de clics
                $invitationRepo->incrementClickCount($invitationId);

                // Logger l'evenement avec l'URL cible
                $eventRepo->logEvent(
                    $tenantId,
                    'clicked',
                    $invitationId,
                    null,
                    ['target_url' => $targetUrl],
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            }
        } catch (\Throwable $e) {
            // Silencieux - ne pas bloquer la redirection
            error_log('Email redirect tracking error: ' . $e->getMessage());
        }
    }
}

// Rediriger vers l'URL cible
header('Location: ' . $targetUrl, true, 302);
exit;
