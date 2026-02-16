<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MotionRepository;
use AgVote\Repository\BallotRepository;
use AgVote\WebSocket\EventBroadcaster;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

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
     * Records or updates a ballot for a given motion.
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

        // Load motion + meeting + tenant
        $motionRepo = new MotionRepository();
        $context = $motionRepo->findWithBallotContext($motionId);

        if (!$context) {
            throw new RuntimeException('Motion introuvable');
        }

        $tenantId      = (string)$context['tenant_id'];
        $meetingStatus = (string)$context['meeting_status'];

        if ($meetingStatus !== 'live') {
            throw new RuntimeException('Impossible de voter sur une motion dont la séance n\'est pas en cours');
        }

if (!empty($context['meeting_validated_at'])) {
    throw new RuntimeException('Séance validée : vote interdit');
}

        if (empty($context['motion_opened_at']) || !empty($context['motion_closed_at'])) {
            throw new RuntimeException('Cette motion n\'est pas ouverte au vote');
        }

        $isProxyVote = (bool)($data['is_proxy_vote'] ?? false);
        $proxyVoterId = trim((string)($data['proxy_source_member_id'] ?? ''));

        // Load the represented member (the one whose vote is counted)
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

        // Vote eligibility (MVP):
        // - "Direct" vote: member must be present (present/remote) at the meeting.
        // - Proxy vote: proxy holder must be present (present/remote) and an active proxy must exist.
        //   The giver (member_id) may be absent.
        $meetingId = (string)$context['meeting_id'];

        $weight = (float)($member['voting_power'] ?? 1.0);
        if ($weight < 0) {
            $weight = 0.0;
        }

        // Proxy vote (MVP)
        // Convention: member_id = giver (vote counted), proxy_source_member_id = proxy holder (the one voting)
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

            // The proxy holder must be physically "present" (present/remote) to vote.
            // We exclude 'proxy' mode to avoid proxy chains.
            $proxyModeOk = false;
            try {
                $proxyModeOk = AttendancesService::isPresentDirect($meetingId, $proxyVoterId, $tenantId);
            } catch (Throwable $e) {
                $proxyModeOk = false;
            }
            if (!$proxyModeOk) {
                throw new RuntimeException('Mandataire non enregistré comme présent, vote par procuration impossible');
            }

            // Verify an active proxy exists for this meeting
            if (!ProxiesService::hasActiveProxy($meetingId, $memberId, $proxyVoterId)) {
                throw new RuntimeException('Aucune procuration active ne permet à ce mandataire de voter pour ce membre');
            }
        } else {
            // Direct vote: member must be present (present/remote) at the meeting.
            // Note: AttendancesService also counts 'proxy'; we don't want that here.
            // So we explicitly check for present/remote modes.
            if (!AttendancesService::isPresentDirect($meetingId, $memberId, $tenantId)) {
                throw new RuntimeException('Membre non enregistré comme présent, vote impossible');
            }
        }

        $ballotRepo = new BallotRepository();

        // Wrap ballot insert + audit log in a transaction for atomicity
        $pdo = \db();
        $pdo->beginTransaction();
        try {
            $ballotRepo->castBallot(
                $tenantId,
                $motionId,
                $memberId,
                $value,
                $weight,
                $isProxyVote,
                $isProxyVote ? $proxyVoterId : null
            );

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

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        // Broadcast WebSocket event with updated tally (outside transaction)
        try {
            $tally = $ballotRepo->getTallyForMotion($tenantId, $meetingId, $motionId);
            EventBroadcaster::voteCast($meetingId, $motionId, $tally);
        } catch (Throwable $e) {
            // Silently fail - don't break the vote if broadcast fails
        }

        $row = $ballotRepo->findByMotionAndMember($motionId, $memberId);

        return $row ?? [
            'motion_id' => $motionId,
            'member_id' => $memberId,
            'value'     => $value,
            'weight'    => $weight,
        ];
    }
}
