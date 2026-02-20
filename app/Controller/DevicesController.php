<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\DeviceRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Consolidates 5 device endpoints.
 *
 * block/unblock/kick share the same structure:
 *   POST with device_id → repo action → audit_log with device context.
 */
final class DevicesController extends AbstractController
{
    private function requireDeviceId(array $in): string
    {
        $id = (string)($in['device_id'] ?? '');
        if ($id === '') {
            api_fail('missing_device_id', 400);
        }
        return $id;
    }

    private function deviceAuditContext(array $hb, string $meetingId, string $deviceId): array
    {
        return [
            'meeting_id' => $meetingId,
            'device_id' => $deviceId,
            'role' => $hb['role'] ?? null,
            'ip' => $hb['ip'] ?? null,
            'user_agent' => $hb['user_agent'] ?? null,
            'battery_pct' => isset($hb['battery_pct']) ? (int)$hb['battery_pct'] : null,
            'is_charging' => isset($hb['is_charging']) ? (bool)$hb['is_charging'] : null,
            'last_seen_at' => $hb['last_seen_at'] ?? null,
        ];
    }

    public function listDevices(): void
    {
        $q = api_request('GET');
        $tenantId = api_current_tenant_id();
        $meetingId = (string)($q['meeting_id'] ?? '');

        $repo = new DeviceRepository();
        $rows = $repo->listHeartbeats($tenantId, $meetingId);

        $now = new \DateTimeImmutable('now');
        $onlineCut = $now->sub(new \DateInterval('PT30S'));
        $staleCut = $now->sub(new \DateInterval('PT120S'));

        $items = [];
        $counts = ['total' => 0, 'online' => 0, 'stale' => 0, 'offline' => 0, 'blocked' => 0];

        foreach ($rows as $r) {
            $counts['total']++;
            $lastSeen = new \DateTimeImmutable((string)$r['last_seen_at']);
            $isBlocked = (bool)$r['is_blocked'];
            if ($isBlocked) {
                $counts['blocked']++;
            }

            if ($lastSeen >= $onlineCut) {
                $status = 'online';
                $counts['online']++;
            } elseif ($lastSeen >= $staleCut) {
                $status = 'stale';
                $counts['stale']++;
            } else {
                $status = 'offline';
                $counts['offline']++;
            }

            $items[] = [
                'device_id' => (string)$r['device_id'],
                'meeting_id' => (string)($r['meeting_id'] ?? ''),
                'role' => (string)($r['role'] ?? ''),
                'ip' => (string)($r['ip'] ?? ''),
                'user_agent' => (string)($r['user_agent'] ?? ''),
                'battery_pct' => $r['battery_pct'] !== null ? (int)$r['battery_pct'] : null,
                'is_charging' => $r['is_charging'] !== null ? (bool)$r['is_charging'] : null,
                'last_seen_at' => (string)$r['last_seen_at'],
                'status' => $status,
                'is_blocked' => $isBlocked,
                'block_reason' => (string)($r['block_reason'] ?? ''),
            ];
        }

        api_ok(['counts' => $counts, 'items' => $items]);
    }

    public function block(): void
    {
        $in = api_request('POST');
        $tenantId = api_current_tenant_id();
        $meetingId = (string)($in['meeting_id'] ?? '');
        $deviceId = $this->requireDeviceId($in);
        $reason = trim((string)($in['reason'] ?? ''));
        if ($reason === '') {
            $reason = 'blocked_by_operator';
        }

        $repo = new DeviceRepository();
        $hb = $repo->findHeartbeat($tenantId, $deviceId);
        $repo->blockDevice($tenantId, $meetingId, $deviceId, $reason);

        $ctx = $this->deviceAuditContext($hb ?: [], $meetingId, $deviceId);
        $ctx['reason'] = $reason;
        audit_log('device_blocked', 'device', $deviceId, $ctx);

        api_ok([]);
    }

    public function unblock(): void
    {
        $in = api_request('POST');
        $tenantId = api_current_tenant_id();
        $meetingId = (string)($in['meeting_id'] ?? '');
        $deviceId = $this->requireDeviceId($in);

        $repo = new DeviceRepository();
        $hb = $repo->findHeartbeat($tenantId, $deviceId);
        $repo->unblockDevice($tenantId, $meetingId, $deviceId);

        audit_log('device_unblocked', 'device', $deviceId,
            $this->deviceAuditContext($hb ?: [], $meetingId, $deviceId));

        api_ok([]);
    }

    public function kick(): void
    {
        $in = api_request('POST');
        $tenantId = api_current_tenant_id();
        $meetingId = (string)($in['meeting_id'] ?? '');
        $deviceId = $this->requireDeviceId($in);
        $message = trim((string)($in['message'] ?? ''));
        if ($message === '') {
            $message = 'Veuillez recharger la page.';
        }

        $repo = new DeviceRepository();
        $hb = $repo->findHeartbeat($tenantId, $deviceId);
        $repo->insertKickCommand($tenantId, $meetingId, $deviceId, $message);

        $ctx = $this->deviceAuditContext($hb ?: [], $meetingId, $deviceId);
        $ctx['message'] = $message;
        audit_log('device_kicked', 'device', $deviceId, $ctx);

        api_ok([]);
    }

    public function heartbeat(): void
    {
        $in = api_request('POST');
        $tenantId = api_current_tenant_id();
        $deviceId = $this->requireDeviceId($in);
        $meetingId = (string)($in['meeting_id'] ?? '');
        $role = (string)($in['role'] ?? '');
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? ($in['ip'] ?? ''));
        $userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? ($in['user_agent'] ?? ''));
        $battery = isset($in['battery_pct']) ? (int)$in['battery_pct'] : null;
        $charging = isset($in['is_charging']) ? (bool)$in['is_charging'] : null;

        // Tenant isolation: verify meeting belongs to current tenant
        if ($meetingId !== '' && api_is_uuid($meetingId)) {
            $meeting = (new MeetingRepository())->findByIdForTenant($meetingId, $tenantId);
            if (!$meeting) {
                $meetingId = ''; // silently ignore cross-tenant meeting_id
            }
        }

        $repo = new DeviceRepository();
        $repo->upsertHeartbeat($deviceId, $tenantId, $meetingId, $role, $ip, $userAgent, $battery, $charging);

        $b = $repo->findBlockStatus($tenantId, $deviceId, $meetingId);
        $isBlocked = $b ? (bool)$b['is_blocked'] : false;
        $blockReason = $b ? (string)($b['reason'] ?? '') : '';

        $command = null;
        $cmd = $repo->findPendingKick($tenantId, $deviceId);
        if ($cmd) {
            $payload = json_decode((string)$cmd['payload'], true);
            $kickMsg = (is_array($payload) && isset($payload['message'])) ? (string)$payload['message'] : '';
            $command = ['type' => 'kick', 'message' => $kickMsg];
            $repo->consumeCommand((string)$cmd['id'], $tenantId);
        }

        api_ok([
            'device_id' => $deviceId,
            'blocked' => $isBlocked,
            'block_reason' => $blockReason,
            'command' => $command,
        ]);
    }
}
