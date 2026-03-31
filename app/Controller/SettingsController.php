<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * SettingsController — handles admin_settings API endpoint.
 *
 * Provides per-tenant key/value settings persistence.
 * Email template operations delegate to EmailTemplatesController.
 */
class SettingsController extends AbstractController {

    public function settings(): void {
        $body = api_request();
        $action = $body['action'] ?? '';
        $tenantId = api_current_tenant_id();

        switch ($action) {
            case 'list':
                $data = $this->repo()->settings()->listByTenant($tenantId);
                api_ok(['data' => $data]);
                break;

            case 'update':
                $key = trim((string) ($body['key'] ?? ''));
                if ($key === '') {
                    api_fail('missing_key', 400, ['detail' => 'key is required']);
                }
                $value = $body['value'] ?? '';
                $this->repo()->settings()->upsert($tenantId, $key, $value);
                api_ok(['saved' => true]);
                break;

            default:
                api_fail('unknown_action', 400, ['detail' => 'Unknown action: ' . $action]);
        }
    }
}
