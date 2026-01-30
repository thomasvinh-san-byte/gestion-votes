<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MemberRepository;

/**
 * Service metier pour les membres.
 *
 * Delegue tout l'acces donnees a MemberRepository.
 */
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
        $repo = new MemberRepository();
        return $repo->listActive($tenantId);
    }

    /**
     * Charge un membre par son id.
     */
    public static function getMember(string $memberId): ?array
    {
        $repo = new MemberRepository();
        return $repo->findById($memberId);
    }
}
