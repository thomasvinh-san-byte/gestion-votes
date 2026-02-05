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
     * Loads a member by its id.
     */
    public static function getMember(string $memberId): ?array
    {
        $repo = new MemberRepository();
        return $repo->findById($memberId);
    }
}
