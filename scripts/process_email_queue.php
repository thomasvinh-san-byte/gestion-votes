#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Worker de traitement de la file d'attente email.
 *
 * Execute via cron toutes les 5 minutes:
 * /5 * * * * /usr/bin/php /var/www/agvote/scripts/process_email_queue.php >> /var/log/agvote/email_queue.log 2>&1
 *
 * Options:
 *   --batch=N       Nombre d'emails a traiter par lot (defaut: 50)
 *   --cleanup       Nettoyer les anciens emails (30 jours)
 *   --reminders     Traiter les rappels programmes
 *   --verbose       Afficher les details
 */

// Eviter l'execution via web
if (php_sapi_name() !== 'cli') {
    die("Ce script doit etre execute en ligne de commande.\n");
}

require __DIR__ . '/../app/bootstrap.php';

use AgVote\Service\EmailQueueService;

// Parser les options
$options = getopt('', ['batch:', 'cleanup', 'reminders', 'verbose', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
        Usage: php process_email_queue.php [options]

        Options:
          --batch=N       Nombre d'emails a traiter par lot (defaut: 50)
          --cleanup       Nettoyer les anciens emails (30 jours)
          --reminders     Traiter les rappels programmes
          --verbose       Afficher les details
          --help          Afficher cette aide

        Exemples:
          php process_email_queue.php                    # Traiter la file d'attente
          php process_email_queue.php --batch=100        # Traiter 100 emails
          php process_email_queue.php --reminders        # Traiter les rappels
          php process_email_queue.php --cleanup          # Nettoyer les anciens

        HELP;
    exit(0);
}

$batchSize = isset($options['batch']) ? (int) $options['batch'] : 50;
$doCleanup = isset($options['cleanup']);
$doReminders = isset($options['reminders']);
$verbose = isset($options['verbose']);

function log_msg(string $msg, bool $verbose): void {
    $timestamp = date('Y-m-d H:i:s');
    if ($verbose) {
        echo "[{$timestamp}] {$msg}\n";
    }
    // Toujours logger pour le fichier de log
    error_log("[{$timestamp}] {$msg}");
}

try {
    global $config;
    $service = new EmailQueueService($config ?? []);

    log_msg('Demarrage du worker email', $verbose);

    // 1. Traiter la file d'attente
    log_msg("Traitement de la file d'attente (batch: {$batchSize})", $verbose);

    $result = $service->processQueue($batchSize);

    log_msg(sprintf(
        'File traitee: %d traites, %d envoyes, %d echecs',
        $result['processed'],
        $result['sent'],
        $result['failed'],
    ), $verbose);

    if (!empty($result['errors'])) {
        foreach ($result['errors'] as $err) {
            log_msg("  Erreur: {$err['email']} - {$err['error']}", $verbose);
        }
    }

    // 2. Traiter les rappels si demande
    if ($doReminders) {
        log_msg('Traitement des rappels programmes', $verbose);

        $reminderResult = $service->processReminders();

        log_msg(sprintf(
            'Rappels traites: %d traites, %d envoyes',
            $reminderResult['processed'],
            $reminderResult['sent'],
        ), $verbose);
    }

    // 3. Nettoyage si demande
    if ($doCleanup) {
        log_msg('Nettoyage des anciens emails (>30 jours)', $verbose);

        $cleaned = $service->cleanup(30);

        log_msg("Nettoyes: {$cleaned} emails supprimes", $verbose);
    }

    log_msg('Worker termine avec succes', $verbose);

    // Code de sortie base sur les erreurs
    $exitCode = empty($result['errors']) ? 0 : 1;
    exit($exitCode);

} catch (\Throwable $e) {
    log_msg('ERREUR FATALE: ' . $e->getMessage(), true);
    log_msg('Trace: ' . $e->getTraceAsString(), $verbose);
    exit(2);
}
