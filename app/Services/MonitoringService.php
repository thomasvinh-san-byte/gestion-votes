<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Logger;
use AgVote\Core\Providers\RepositoryFactory;
use Throwable;

/**
 * MonitoringService - Collects system metrics, evaluates alert thresholds,
 * and dispatches notifications (email + webhook) for critical alerts.
 *
 * Designed to be called periodically by a CLI command (cron every 5 min).
 */
final class MonitoringService {
    private array $config;
    private RepositoryFactory $repo;

    // Alert thresholds (configurable via env)
    private int $authFailuresThreshold;
    private float $dbLatencyThresholdMs;
    private float $diskFreeThresholdPct;
    private int $emailQueueBacklogThreshold;

    public function __construct(array $config, RepositoryFactory $repo) {
        $this->config = $config;
        $this->repo = $repo;

        $this->authFailuresThreshold = (int) (getenv('MONITOR_AUTH_FAILURES_THRESHOLD') ?: 5);
        $this->dbLatencyThresholdMs = (float) (getenv('MONITOR_DB_LATENCY_MS') ?: 2000.0);
        $this->diskFreeThresholdPct = (float) (getenv('MONITOR_DISK_FREE_PCT') ?: 10.0);
        $this->emailQueueBacklogThreshold = (int) (getenv('MONITOR_EMAIL_BACKLOG') ?: 100);
    }

    /**
     * Run a full monitoring check: collect metrics, evaluate thresholds, dispatch alerts.
     *
     * @return array{metrics: array, alerts_created: array, notifications_sent: int}
     */
    public function check(): array {
        $sysRepo = $this->repo->system();

        // ── Collect metrics ──────────────────────────────────────────────
        $serverTime = date('c');
        $dbLat = $sysRepo->dbPing();
        $active = $sysRepo->dbActiveConnections();

        $free = @disk_free_space(dirname(__DIR__)) ?: null;
        $total = @disk_total_space(dirname(__DIR__)) ?: null;
        $diskPct = ($free !== null && $total && $total > 0) ? ($free / $total) * 100.0 : null;

        $tenantId = $this->config['default_tenant_id'] ?? '';

        $cntMeet = $this->repo->meeting()->countForTenant($tenantId);
        $cntMot = $this->repo->motion()->countAll($tenantId);
        $cntTok = $this->repo->voteToken()->countAll();
        $cntAud = $sysRepo->countAuditEvents($tenantId);
        $fail15 = $sysRepo->countAuthFailures15m();

        // Email queue backlog
        $emailBacklog = $this->countEmailBacklog();

        $metrics = [
            'server_time' => $serverTime,
            'db_latency_ms' => $dbLat !== null ? round($dbLat, 2) : null,
            'db_active_connections' => $active !== null ? (int) $active : null,
            'disk_free_bytes' => $free,
            'disk_total_bytes' => $total,
            'disk_free_pct' => $diskPct !== null ? round($diskPct, 2) : null,
            'count_meetings' => $cntMeet,
            'count_motions' => $cntMot,
            'count_vote_tokens' => $cntTok,
            'count_audit_events' => $cntAud,
            'auth_failures_15m' => $fail15,
            'email_queue_backlog' => $emailBacklog,
            'php_version' => phpversion(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 1),
        ];

        // ── Persist metrics ──────────────────────────────────────────────
        try {
            $sysRepo->insertSystemMetric([
                'server_time' => $serverTime,
                'db_latency_ms' => $dbLat,
                'db_active_connections' => $active !== null ? (int) $active : null,
                'disk_free_bytes' => $free,
                'disk_total_bytes' => $total,
                'count_meetings' => $cntMeet,
                'count_motions' => $cntMot,
                'count_vote_tokens' => $cntTok,
                'count_audit_events' => $cntAud,
                'auth_failures_15m' => $fail15,
            ]);
        } catch (Throwable) {
            // Non-critical
        }

        // ── Evaluate alert thresholds ────────────────────────────────────
        $alertsToCreate = [];

        if ($fail15 !== null && $fail15 > $this->authFailuresThreshold) {
            $alertsToCreate[] = [
                'code' => 'auth_failures',
                'severity' => 'warn',
                'message' => "Plus de {$this->authFailuresThreshold} echecs d'authentification sur 15 min ({$fail15}).",
                'details' => ['count' => $fail15, 'threshold' => $this->authFailuresThreshold],
            ];
        }

        if ($dbLat !== null && $dbLat > $this->dbLatencyThresholdMs) {
            $alertsToCreate[] = [
                'code' => 'slow_db',
                'severity' => 'critical',
                'message' => 'Latence DB > ' . round($this->dbLatencyThresholdMs) . 'ms (' . round($dbLat) . 'ms).',
                'details' => ['db_latency_ms' => round($dbLat, 2), 'threshold_ms' => $this->dbLatencyThresholdMs],
            ];
        }

        if ($dbLat === null) {
            $alertsToCreate[] = [
                'code' => 'db_unreachable',
                'severity' => 'critical',
                'message' => 'Base de donnees injoignable.',
                'details' => [],
            ];
        }

        if ($diskPct !== null && $diskPct < $this->diskFreeThresholdPct) {
            $alertsToCreate[] = [
                'code' => 'low_disk',
                'severity' => 'critical',
                'message' => 'Espace disque < ' . round($this->diskFreeThresholdPct) . '% (' . round($diskPct, 1) . '%).',
                'details' => ['free_pct' => round($diskPct, 2), 'free_bytes' => $free, 'total_bytes' => $total],
            ];
        }

        if ($emailBacklog > $this->emailQueueBacklogThreshold) {
            $alertsToCreate[] = [
                'code' => 'email_backlog',
                'severity' => 'warn',
                'message' => "File email encombree : {$emailBacklog} emails en attente (seuil: {$this->emailQueueBacklogThreshold}).",
                'details' => ['backlog' => $emailBacklog, 'threshold' => $this->emailQueueBacklogThreshold],
            ];
        }

        // ── Insert alerts (deduplicated by 10-min window) ────────────────
        $newAlerts = [];
        foreach ($alertsToCreate as $a) {
            try {
                if (!$sysRepo->findRecentAlert($a['code'])) {
                    $sysRepo->insertSystemAlert($a['code'], $a['severity'], $a['message'], json_encode($a['details']));
                    $newAlerts[] = $a;
                }
            } catch (Throwable) {
                // Non-critical
            }
        }

        // ── Dispatch notifications for new alerts ────────────────────────
        $notifsSent = 0;
        foreach ($newAlerts as $alert) {
            $notifsSent += $this->dispatchAlert($alert);
        }

        return [
            'metrics' => $metrics,
            'alerts_created' => $newAlerts,
            'notifications_sent' => $notifsSent,
        ];
    }

    /**
     * Dispatch an alert via email + webhook.
     *
     * @return int Number of notifications sent
     */
    private function dispatchAlert(array $alert): int {
        $count = 0;

        // Email alert to admin users
        $count += $this->sendAlertEmails($alert);

        // Webhook
        $count += $this->sendWebhook($alert);

        return $count;
    }

    /**
     * Send alert email to all active admin users.
     */
    private function sendAlertEmails(array $alert): int {
        $alertEmailsEnv = getenv('MONITOR_ALERT_EMAILS');
        if ($alertEmailsEnv === false || $alertEmailsEnv === '' || $alertEmailsEnv === '0') {
            return 0;
        }

        $mailer = new MailerService($this->config);
        if (!$mailer->isConfigured()) {
            Logger::warning('MonitoringService: SMTP not configured, skipping alert emails');
            return 0;
        }

        // Get recipients: from env var or from admin users in DB
        $recipients = $this->getAlertRecipients($alertEmailsEnv);
        if (empty($recipients)) {
            return 0;
        }

        $severityLabel = strtoupper($alert['severity']);
        $subject = "[AG-VOTE {$severityLabel}] {$alert['code']}";
        $html = $this->renderAlertEmail($alert);
        $sent = 0;

        foreach ($recipients as $email) {
            try {
                $result = $mailer->send($email, $subject, $html);
                if ($result['ok']) {
                    $sent++;
                } else {
                    Logger::warning('MonitoringService: alert email failed', [
                        'to' => $email,
                        'error' => $result['error'],
                    ]);
                }
            } catch (Throwable $e) {
                Logger::warning('MonitoringService: alert email exception', [
                    'to' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * Resolve alert email recipients.
     * If MONITOR_ALERT_EMAILS is "auto", fetch admin users from DB.
     * Otherwise, treat as comma-separated list of emails.
     */
    private function getAlertRecipients(string $envValue): array {
        if (strtolower(trim($envValue)) === 'auto') {
            $tenantId = $this->config['default_tenant_id'] ?? '';
            try {
                $admins = $this->repo->user()->listByTenant($tenantId, 'admin');
                return array_filter(array_map(
                    fn (array $u) => $u['email'] ?? '',
                    $admins,
                ));
            } catch (Throwable $e) {
                Logger::warning('MonitoringService: failed to load admin users', ['error' => $e->getMessage()]);
                return [];
            }
        }

        return array_filter(array_map('trim', explode(',', $envValue)));
    }

    /**
     * Render alert email HTML.
     */
    private function renderAlertEmail(array $alert): string {
        $severityColor = $alert['severity'] === 'critical' ? '#C42828' : '#B56700';
        $severityLabel = strtoupper($alert['severity']);
        $time = date('d/m/Y H:i:s');
        $details = '';
        if (!empty($alert['details'])) {
            $details = '<h3 style="margin:16px 0 8px;font-size:14px;color:#333;">Details</h3><table style="border-collapse:collapse;width:100%;">';
            foreach ($alert['details'] as $key => $val) {
                $details .= '<tr><td style="padding:4px 8px;border:1px solid #ddd;font-weight:600;">' . htmlspecialchars((string) $key) . '</td><td style="padding:4px 8px;border:1px solid #ddd;">' . htmlspecialchars((string) $val) . '</td></tr>';
            }
            $details .= '</table>';
        }
        $appUrl = htmlspecialchars($this->config['app_url'] ?? 'http://localhost:8080');

        return <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"></head>
<body style="font-family:system-ui,-apple-system,sans-serif;max-width:600px;margin:0 auto;padding:20px;color:#333;">
  <div style="background:{$severityColor};color:#fff;padding:12px 20px;border-radius:8px 8px 0 0;">
    <strong>[{$severityLabel}] Alerte systeme AG-VOTE</strong>
  </div>
  <div style="border:1px solid #ddd;border-top:none;padding:20px;border-radius:0 0 8px 8px;">
    <p style="margin:0 0 12px;"><strong>Code :</strong> {$alert['code']}</p>
    <p style="margin:0 0 12px;"><strong>Message :</strong> {$alert['message']}</p>
    <p style="margin:0 0 12px;"><strong>Horodatage :</strong> {$time}</p>
    {$details}
    <p style="margin:16px 0 0;">
      <a href="{$appUrl}/admin.htmx.html" style="display:inline-block;padding:8px 16px;background:#1650E0;color:#fff;text-decoration:none;border-radius:6px;">Voir le tableau de bord</a>
    </p>
  </div>
  <p style="font-size:11px;color:#999;margin-top:12px;">Cet email a ete envoye automatiquement par le systeme de monitoring AG-VOTE.</p>
</body></html>
HTML;
    }

    /**
     * Send webhook notification for an alert.
     */
    private function sendWebhook(array $alert): int {
        $webhookUrl = getenv('MONITOR_WEBHOOK_URL');
        if ($webhookUrl === false || $webhookUrl === '') {
            return 0;
        }

        $payload = json_encode([
            'event' => 'system_alert',
            'timestamp' => date('c'),
            'code' => $alert['code'],
            'severity' => $alert['severity'],
            'message' => $alert['message'],
            'details' => $alert['details'] ?? [],
            'source' => 'ag-vote',
        ]);

        try {
            $ch = curl_init($webhookUrl);
            if ($ch === false) {
                return 0;
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                return 1;
            }

            Logger::warning('MonitoringService: webhook returned non-2xx', [
                'url' => $webhookUrl,
                'http_code' => $httpCode,
                'response' => is_string($response) ? substr($response, 0, 200) : '',
            ]);
        } catch (Throwable $e) {
            Logger::warning('MonitoringService: webhook failed', [
                'url' => $webhookUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return 0;
    }

    /**
     * Count pending emails in queue.
     */
    private function countEmailBacklog(): int {
        return $this->repo->system()->countPendingEmails();
    }

    /**
     * Clean up old metrics data (retain last N days).
     */
    public function cleanupMetrics(int $retainDays = 30): int {
        return $this->repo->system()->cleanupMetrics($retainDays);
    }

    /**
     * Clean up old alerts (retain last N days).
     */
    public function cleanupAlerts(int $retainDays = 90): int {
        return $this->repo->system()->cleanupAlerts($retainDays);
    }
}
