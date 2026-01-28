<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/MembersService.php';
require_once __DIR__ . '/ProxiesService.php';
require_once __DIR__ . '/AttendancesService.php';

final class BallotsService
{
    private static function isUuid(string $s): bool
    {
        return (bool)preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $s
        );
    }

    /**
     * Enregistre ou met à jour un bulletin pour une motion donnée.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function castBallot(array $data): array
    {
        $motionId = trim((string)($data['motion_id'] ?? ''));
        $memberId = trim((string)($data['member_id'] ?? ''));
        $value    = trim((string)($data['value'] ?? ''));

        if ($motionId === '' || $memberId === '' || $value === '') {
            throw new InvalidArgumentException('motion_id, member_id et value sont obligatoires');
        }

        $allowedValues = ['for', 'against', 'abstain', 'nsp'];
        if (!in_array($value, $allowedValues, true)) {
            throw new InvalidArgumentException('Valeur de vote invalide (for/against/abstain/nsp attendue)');
        }

        // Charger motion + meeting + tenant
        $context = db_select_one(
            "
            SELECT
              m.id          AS motion_id,
              m.opened_at   AS motion_opened_at,
              m.closed_at   AS motion_closed_at,
              mt.id         AS meeting_id,
              mt.status     AS meeting_status,
              mt.validated_at AS meeting_validated_at,
              mt.tenant_id  AS tenant_id
            FROM motions m
            JOIN meetings mt ON mt.id = m.meeting_id
            WHERE m.id = :motion_id
            ",
            [':motion_id' => $motionId]
        );

        if (!$context) {
            throw new RuntimeException('Motion introuvable');
        }

        $tenantId      = (string)$context['tenant_id'];
        $meetingStatus = (string)$context['meeting_status'];

        if ($meetingStatus !== 'live') {
            throw new RuntimeException('Impossible de voter sur une motion dont la séance n’est pas en cours');
        }

if (!empty($context['meeting_validated_at'])) {
    throw new RuntimeException('Séance validée : vote interdit');
}

        if (empty($context['motion_opened_at']) || !empty($context['motion_closed_at'])) {
            throw new RuntimeException('Cette motion n’est pas ouverte au vote');
        }

        $isProxyVote = (bool)($data['is_proxy_vote'] ?? false);
        $proxyVoterId = trim((string)($data['proxy_source_member_id'] ?? ''));

        // Charger le membre "représenté" (celui dont le vote est compté)
        $member = MembersService::getMember($memberId);
        if (!$member) {
            throw new RuntimeException('Membre inconnu');
        }

        if ((string)$member['tenant_id'] !== $tenantId) {
            throw new RuntimeException('Le membre ne fait pas partie de ce tenant');
        }

        if (!($member['is_active'] ?? false)) {
            throw new RuntimeException('Membre inactif, vote impossible');
        }

        // Éligibilité au vote (MVP):
        // - Vote "direct": le membre doit être présent (present/remote) sur la séance.
        // - Vote par procuration: le mandataire doit être présent (present/remote) et une procuration active doit exister.
        //   Le mandant (member_id) peut être absent.
        $meetingId = (string)$context['meeting_id'];

        $weight = (float)($member['voting_power'] ?? 1.0);
        if ($weight < 0) {
            $weight = 0.0;
        }

        // Vote par procuration (MVP)
        // Convention: member_id = mandant (vote compté), proxy_source_member_id = mandataire (celui qui vote)
        if ($isProxyVote) {
            if ($proxyVoterId === '' || !self::isUuid($proxyVoterId)) {
                throw new InvalidArgumentException('proxy_source_member_id est obligatoire (UUID) pour un vote par procuration');
            }

            $proxyVoter = MembersService::getMember($proxyVoterId);
            if (!$proxyVoter) {
                throw new RuntimeException('Mandataire inconnu');
            }
            if ((string)$proxyVoter['tenant_id'] !== $tenantId) {
                throw new RuntimeException('Le mandataire ne fait pas partie de ce tenant');
            }
            if (!($proxyVoter['is_active'] ?? false)) {
                throw new RuntimeException('Mandataire inactif, vote impossible');
            }

            // Le mandataire doit être physiquement "présent" (present/remote) pour voter.
            // On exclut le mode 'proxy' pour éviter les chaînes de procurations.
            $proxyModeOk = false;
            try {
                $proxyModeOk = AttendancesService::isPresentDirect($meetingId, $proxyVoterId, $tenantId);
            } catch (Throwable $e) {
                $proxyModeOk = false;
            }
            if (!$proxyModeOk) {
                throw new RuntimeException('Mandataire non enregistré comme présent, vote par procuration impossible');
            }

            // Vérifie qu'une procuration active existe pour cette séance
            if (!ProxiesService::hasActiveProxy($meetingId, $memberId, $proxyVoterId)) {
                throw new RuntimeException('Aucune procuration active ne permet à ce mandataire de voter pour ce membre');
            }
        } else {
            // Vote direct: le membre doit être présent (present/remote) sur la séance.
            // Note: AttendancesService compte aussi 'proxy'; on ne le souhaite pas ici.
            // On vérifie donc explicitement les modes present/remote.
            if (!AttendancesService::isPresentDirect($meetingId, $memberId, $tenantId)) {
                throw new RuntimeException('Membre non enregistré comme présent, vote impossible');
            }
        }

        global $pdo;

        $sql = "
            INSERT INTO ballots (
              id,
              tenant_id,
              motion_id,
              member_id,
              value,
              weight,
              cast_at,
              is_proxy_vote,
              proxy_source_member_id
            ) VALUES (
              gen_random_uuid(),
              :tenant_id,
              :motion_id,
              :member_id,
              :value,
              :weight,
              now(),
              :is_proxy_vote,
              :proxy_source_member_id
            )
            ON CONFLICT (motion_id, member_id) DO UPDATE
            SET value                  = EXCLUDED.value,
                weight                 = EXCLUDED.weight,
                cast_at                = now(),
                is_proxy_vote          = EXCLUDED.is_proxy_vote,
                proxy_source_member_id = EXCLUDED.proxy_source_member_id
        ";

        $params = [
            ':tenant_id'              => $tenantId,
            ':motion_id'              => $motionId,
            ':member_id'              => $memberId,
            ':value'                  => $value,
            ':weight'                 => $weight,
            ':is_proxy_vote'          => $isProxyVote,
            ':proxy_source_member_id' => $isProxyVote ? $proxyVoterId : null,
        ];

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (function_exists('audit_log')) {
            audit_log('ballot_cast', 'motion', $motionId, [
                'meeting_id' => $context['meeting_id'],
                'member_id'  => $memberId,
                'value'      => $value,
                'weight'     => $weight,
                'is_proxy_vote' => $isProxyVote,
                'proxy_source_member_id' => $isProxyVote ? $proxyVoterId : null,
            ]);
        }

        $row = db_select_one(
            "
            SELECT
              motion_id,
              member_id,
              value,
              weight,
              cast_at,
              is_proxy_vote,
              proxy_source_member_id
            FROM ballots
            WHERE motion_id = :motion_id
              AND member_id = :member_id
            ",
            [
                ':motion_id' => $motionId,
                ':member_id' => $memberId,
            ]
        );

        return $row ?? [
            'motion_id' => $motionId,
            'member_id' => $memberId,
            'value'     => $value,
            'weight'    => $weight,
        ];
    }
}