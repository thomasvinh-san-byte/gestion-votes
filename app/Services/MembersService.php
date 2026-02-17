<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MemberRepository;

/**
 * Business service for members.
 *
 * Delegates all data access to MemberRepository.
 */
final class MembersService
{
    /**
     * Returns the list of active members for a tenant.
     *
     * @param string|null $tenantId
     * @return array<int,array<string,mixed>>
     */
    public static function getActiveMembers(?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $repo = new MemberRepository();
        return $repo->listActive($tenantId);
    }

    /**
     * Loads a member by its id for a given tenant.
     *
     * @param string $memberId
     * @param string|null $tenantId Falls back to global tenant if omitted.
     */
    public static function getMember(string $memberId, ?string $tenantId = null): ?array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $repo = new MemberRepository();
        return $repo->findByIdForTenant($memberId, $tenantId);
    }
}
