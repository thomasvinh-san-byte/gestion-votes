<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository for tenant_settings key/value store.
 *
 * Provides CRUD for per-tenant settings persistence.
 * Table created by migration 20260322_tenant_settings.sql.
 */
class SettingsRepository extends AbstractRepository {

    /**
     * Return all settings for a tenant as an associative array key => value.
     */
    public function listByTenant(string $tenantId): array {
        $rows = $this->selectAll(
            'SELECT key, value FROM tenant_settings WHERE tenant_id = :tid',
            [':tid' => $tenantId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    /**
     * Insert or update a setting value (PostgreSQL UPSERT).
     */
    public function upsert(string $tenantId, string $key, mixed $value): void {
        $this->execute(
            'INSERT INTO tenant_settings (tenant_id, key, value, updated_at)
             VALUES (:tid, :key, :val, NOW())
             ON CONFLICT (tenant_id, key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()',
            [':tid' => $tenantId, ':key' => $key, ':val' => (string) $value]
        );
    }

    /**
     * Get a single setting value for a tenant.
     */
    public function get(string $tenantId, string $key): ?string {
        $row = $this->selectOne(
            'SELECT value FROM tenant_settings WHERE tenant_id = :tid AND key = :key',
            [':tid' => $tenantId, ':key' => $key]
        );
        return $row ? $row['value'] : null;
    }
}
