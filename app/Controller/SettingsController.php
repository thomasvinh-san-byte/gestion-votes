<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * SettingsController — handles admin_settings.php API endpoint.
 *
 * Actions:
 *   list           — return all settings for current tenant
 *   update         — upsert a single setting key/value
 *   get_template   — stub (email templates are at /api/v1/email_templates)
 *   save_template  — stub
 *   test_smtp      — stub
 *   reset_templates — stub
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

            case 'get_template':
                // Stub — real email templates live at /api/v1/email_templates
                api_ok(['data' => ['subject' => '', 'body' => '']]);
                break;

            case 'save_template':
                // Stub
                api_ok(['saved' => true]);
                break;

            case 'test_smtp':
                // Stub — SMTP not configured
                api_ok(['sent' => true, 'message' => 'Test SMTP non configure']);
                break;

            case 'reset_templates':
                // Stub
                api_ok(['reset' => true]);
                break;

            default:
                api_fail('unknown_action', 400, ['detail' => 'Unknown action: ' . $action]);
        }
    }
}
