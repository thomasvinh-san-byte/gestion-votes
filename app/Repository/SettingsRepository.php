<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository for tenant_settings key/value store.
 *
 * Provides CRUD for per-tenant settings persistence.
 * Table is created on first use (SQLite — no migration runner).
 */
class SettingsRepository extends AbstractRepository {

    public function __construct(?\PDO $pdo = null) {
        parent::__construct($pdo);
        $this->ensureTable();
    }

    /**
     * Create tenant_settings table if it does not exist yet.
     */
    private function ensureTable(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS tenant_settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tenant_id TEXT NOT NULL,
                key TEXT NOT NULL,
                value TEXT,
                updated_at TEXT DEFAULT (datetime('now')),
                UNIQUE(tenant_id, key)
            )
        ");
    }

    /**
     * Return all settings for a tenant as an associative array key => value.
     *
     * @param string $tenantId
     * @return array<string, string|null>
     */
    public function listByTenant(string $tenantId): array {
        $rows = $this->selectAll(
            'SELECT key, value FROM tenant_settings WHERE tenant_id = ?',
            [$tenantId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['key']] = $row['value'];
        }
        return $result;
    }

    /**
     * Insert or replace a setting value.
     *
     * @param string $tenantId
     * @param string $key
     * @param mixed  $value
     */
    public function upsert(string $tenantId, string $key, mixed $value): void {
        $this->execute(
            'INSERT OR REPLACE INTO tenant_settings (tenant_id, key, value, updated_at)
             VALUES (?, ?, ?, datetime(\'now\'))',
            [$tenantId, $key, (string) $value]
        );
    }

    /**
     * Get a single setting value for a tenant.
     *
     * @param string $tenantId
     * @param string $key
     * @return string|null
     */
    public function get(string $tenantId, string $key): ?string {
        $row = $this->selectOne(
            'SELECT value FROM tenant_settings WHERE tenant_id = ? AND key = ?',
            [$tenantId, $key]
        );
        return $row ? $row['value'] : null;
    }
}
