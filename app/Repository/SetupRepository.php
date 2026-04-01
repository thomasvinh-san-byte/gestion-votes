<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository for first-run setup operations.
 *
 * Provides admin existence check and atomic tenant+admin creation.
 * Used exclusively by SetupController.
 */
class SetupRepository extends AbstractRepository {
    /**
     * Returns true if at least one active admin user exists.
     */
    public function hasAnyAdmin(): bool {
        $count = $this->scalar(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = true"
        );
        return ((int) $count) > 0;
    }

    /**
     * Creates a tenant and admin user in a single transaction.
     *
     * @param string $orgName      Organisation name
     * @param string $email        Admin email
     * @param string $name         Admin display name
     * @param string $passwordHash Hashed password (password_hash output)
     * @return array{tenant_id: string, user_id: string}
     * @throws \Throwable on DB error (transaction rolled back)
     */
    public function createTenantAndAdmin(
        string $orgName,
        string $email,
        string $name,
        string $passwordHash,
    ): array {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            // Create tenant
            $tenantId = $this->scalar(
                'INSERT INTO tenants (name) VALUES (:name) RETURNING id',
                [':name' => $orgName]
            );

            // Create admin user
            $userId = $this->scalar(
                "INSERT INTO users (tenant_id, email, name, role, password_hash, is_active)
                 VALUES (:tid, :email, :name, 'admin', :hash, true)
                 RETURNING id",
                [
                    ':tid'   => $tenantId,
                    ':email' => $email,
                    ':name'  => $name,
                    ':hash'  => $passwordHash,
                ]
            );

            $pdo->commit();
            return ['tenant_id' => (string) $tenantId, 'user_id' => (string) $userId];
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
