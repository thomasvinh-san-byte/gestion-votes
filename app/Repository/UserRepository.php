<?php
declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Acces donnees pour les utilisateurs (users).
 */
class UserRepository extends AbstractRepository
{
    /**
     * Trouve un utilisateur par hash de cle API.
     */
    public function findByApiKeyHash(string $tenantId, string $hash): ?array
    {
        return $this->selectOne(
            "SELECT id, tenant_id, email, name, role, is_active
             FROM users
             WHERE tenant_id = :tid AND api_key_hash = :hash
             LIMIT 1",
            [':tid' => $tenantId, ':hash' => $hash]
        );
    }
}
