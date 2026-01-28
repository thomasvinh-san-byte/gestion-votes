<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

final class MembersService
{
    /**
     * Retourne la liste des membres actifs pour un tenant.
     *
     * @param string|null $tenantId
     * @return array<int,array<string,mixed>>
     */
    public static function getActiveMembers(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

        $sql = "
            SELECT
              id,
              full_name,
              email,
              role,
              COALESCE(voting_power, vote_weight, 1.0) AS voting_power,
              vote_weight,
              is_active,
              created_at,
              updated_at,
              tenant_id
            FROM members
            WHERE tenant_id = :tenant_id
              AND is_active = true
            ORDER BY full_name ASC
        ";

        global $pdo;
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        $rows = $stmt->fetchAll();
        return $rows === false ? [] : $rows;
    }

    /**
     * Charge un membre par son id.
     */
    public static function getMember(string $memberId): ?array
    {
        $row = db_select_one(
            "SELECT * FROM members WHERE id = :id",
            [':id' => $memberId]
        );

        return $row ?: null;
    }
}
