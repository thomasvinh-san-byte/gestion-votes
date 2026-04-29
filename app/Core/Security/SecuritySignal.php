<?php

declare(strict_types=1);

namespace AgVote\Core\Security;

use AgVote\Core\Http\ClientIp;
use AgVote\Core\Providers\RedisProvider;
use Throwable;

/**
 * SecuritySignal — F21 anomaly detection at log layer.
 *
 * Existing call sites already drop structured `error_log` lines for AUTH_FAILURE,
 * RATE_LIMIT, MONITOR_WEBHOOK_REJECTED, EMAIL_TRACKING_RATE_LIMIT, etc. This
 * helper adds a complementary escalation channel: when the rate of these
 * events from a single client crosses a threshold, we synthesize a
 * `SECURITY_ALERT` log line that ops dashboards can filter and alert on.
 *
 * The counter is short-lived (60 s) so the dashboard sees real bursts, not
 * accumulated history. It's stored in Redis under hashed keys so client
 * identifiers never leak into the operational metrics store.
 *
 * Designed to be best-effort: if Redis is unavailable, the helper
 * silently no-ops and the original error_log call still records the
 * underlying event.
 */
final class SecuritySignal {
    private const COUNTER_PREFIX = 'sec:signal:counter:';
    private const FIRED_PREFIX   = 'sec:signal:fired:';
    private const COUNTER_TTL    = 60;
    private const FIRED_COOLDOWN = 300; // 5 min between alerts for same key

    /**
     * Record an event for the current client. If the rolling 60 s count
     * for (eventType, clientIp) crosses the threshold, emit a SECURITY_ALERT
     * log line — but at most once every FIRED_COOLDOWN seconds for the same
     * key (otherwise a sustained attack would flood the alert channel).
     *
     * @param array<string, scalar|null> $context arbitrary structured fields
     *                                            included in the alert log.
     */
    public static function record(string $eventType, int $threshold = 10, array $context = []): void {
        $clientIp = ClientIp::get();
        $key = $eventType . '|' . hash('sha256', $clientIp);
        $counterKey = self::COUNTER_PREFIX . $key;
        $firedKey = self::FIRED_PREFIX . $key;

        try {
            $redis = RedisProvider::connection();
            $count = (int) $redis->incr($counterKey);
            if ($count === 1) {
                $redis->expire($counterKey, self::COUNTER_TTL);
            }
            if ($count < $threshold) {
                return;
            }
            // Cooldown gate to prevent log flooding.
            $alreadyFired = $redis->get($firedKey);
            if ($alreadyFired) {
                return;
            }
            $redis->set($firedKey, '1', ['EX' => self::FIRED_COOLDOWN]);
        } catch (Throwable) {
            return; // best effort
        }

        $payload = [
            'event'       => $eventType,
            'count_60s'   => $count,
            'threshold'   => $threshold,
            'client_ip'   => $clientIp,
            'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
        ] + $context;
        error_log('SECURITY_ALERT ' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
