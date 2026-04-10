<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use InvalidArgumentException;
use RuntimeException;

/**
 * Business logic extracted from MeetingsController.
 * Handles meeting CRUD, summary, stats, and validation.
 */
final class MeetingLifecycleService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /** @return array{updated: bool, meeting_id: string} */
    public function updateMeeting(string $meetingId, string $tenantId, array $data): array {
        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : null;
        $presidentName = array_key_exists('president_name', $data) ? trim((string) $data['president_name']) : null;
        $scheduledAt = array_key_exists('scheduled_at', $data) ? trim((string) $data['scheduled_at']) : null;
        $meetingType = array_key_exists('meeting_type', $data) ? trim((string) $data['meeting_type']) : null;
        if (array_key_exists('status', $data)) {
            throw new InvalidArgumentException('Les transitions de statut doivent passer par /api/v1/meeting_transition.php.');
        }
        if ($title !== null) {
            if (mb_strlen($title) === 0) { throw new InvalidArgumentException('Le titre de la séance est obligatoire.'); }
            if (mb_strlen($title) > 120) { throw new InvalidArgumentException('Titre trop long (120 max).'); }
        }
        if ($presidentName !== null && mb_strlen($presidentName) > 200) {
            throw new InvalidArgumentException('Nom du président trop long (200 max).');
        }
        $validTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];
        if ($meetingType !== null && !in_array($meetingType, $validTypes, true)) {
            throw new InvalidArgumentException('Type de séance invalide.');
        }
        $repo = $this->repos->meeting();
        $current = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$current) { throw new RuntimeException('meeting_not_found'); }
        if ((string) $current['status'] === 'archived') { throw new RuntimeException('Séance archivée : modification interdite.'); }
        $fields = [];
        if ($title !== null) { $fields['title'] = $title; }
        if ($presidentName !== null) { $fields['president_name'] = $presidentName; }
        if ($scheduledAt !== null) { $fields['scheduled_at'] = $scheduledAt ?: null; }
        if ($meetingType !== null) { $fields['meeting_type'] = $meetingType; }
        if (!$fields) { return ['updated' => false, 'meeting_id' => $meetingId]; }
        $updated = $repo->updateFields($meetingId, $tenantId, $fields);
        return ['updated' => $updated > 0, 'meeting_id' => $meetingId, 'fields' => array_keys($fields)];
    }

    /** @return array{meeting_id: string, title: string, members_created: int, members_linked: int, motions_created: int} */
    public function createFromWizard(array $data, string $tenantId): array {
        $title = trim((string) ($data['title'] ?? ''));
        if (mb_strlen($title) < 3) { throw new InvalidArgumentException('Le titre est obligatoire (3 caractères minimum).'); }
        $meetingType = trim((string) ($data['type'] ?? 'ag_ordinaire')) ?: 'ag_ordinaire';
        $validTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];
        if (!in_array($meetingType, $validTypes, true)) { $meetingType = 'ag_ordinaire'; }
        $description = trim((string) ($data['description'] ?? '')) ?: null;
        $location = trim((string) ($data['place'] ?? '')) ?: null;
        $dateStr = trim((string) ($data['date'] ?? ''));
        $timeStr = trim((string) ($data['time'] ?? ''));
        $scheduledAt = $dateStr !== '' ? $dateStr . ' ' . ($timeStr !== '' ? $timeStr : '00:00') . ':00' : null;
        $quorumPolicyId = trim((string) ($data['quorum'] ?? '')) ?: null;
        $votePolicyId = trim((string) ($data['defaultMaj'] ?? '')) ?: null;
        /** @var array<int, array<string, mixed>> $members */
        $members = is_array($data['members'] ?? null) ? $data['members'] : [];
        /** @var array<int, array<string, mixed>> $resolutions */
        $resolutions = is_array($data['resolutions'] ?? null) ? $data['resolutions'] : [];
        $repo = $this->repos->meeting();
        $memberRepo = $this->repos->member();
        $attendanceRepo = $this->repos->attendance();
        $motionRepo = $this->repos->motion();
        $policyRepo = $this->repos->policy();
        $meetingId = $repo->generateUuid();
        $repo->create($meetingId, $tenantId, $title, $description, $scheduledAt, $location, $meetingType);
        $defaults = [];
        if ($votePolicyId !== null && api_is_uuid($votePolicyId)) {
            $defaults['vote_policy_id'] = $votePolicyId;
        } else {
            $vp = $policyRepo->listVotePolicies($tenantId);
            if (!empty($vp)) { $defaults['vote_policy_id'] = $vp[0]['id']; }
        }
        if ($quorumPolicyId !== null && api_is_uuid($quorumPolicyId)) {
            $defaults['quorum_policy_id'] = $quorumPolicyId;
        } else {
            $qp = $policyRepo->listQuorumPolicies($tenantId);
            if (!empty($qp)) { $defaults['quorum_policy_id'] = $qp[0]['id']; }
        }
        if ($defaults) { $repo->updateFields($meetingId, $tenantId, $defaults); }
        $membersCreated = 0;
        $membersLinked = 0;
        foreach ($members as $member) {
            $nom = trim((string) ($member['nom'] ?? ''));
            $email = trim((string) ($member['email'] ?? ''));
            $voteWeight = (float) ($member['voix'] ?? 1);
            if ($nom === '') { throw new InvalidArgumentException('Le nom est obligatoire.'); }
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) { throw new InvalidArgumentException('Email invalide.'); }
            $existing = $memberRepo->findByEmail($tenantId, $email);
            if ($existing !== null) {
                $memberId = (string) $existing['id'];
                $membersLinked++;
            } else {
                $memberId = $memberRepo->generateUuid();
                $memberRepo->create($memberId, $tenantId, $nom, $email, $voteWeight, true);
                $membersCreated++;
            }
            $attendanceRepo->upsertMode($meetingId, $memberId, 'present', $tenantId);
        }
        $motionsCreated = 0;
        foreach ($resolutions as $resolution) {
            $resTitle = trim((string) ($resolution['title'] ?? ''));
            if ($resTitle === '') { throw new InvalidArgumentException('Le titre de la résolution est obligatoire.'); }
            $motionId = $motionRepo->generateUuid();
            $resDescription = trim((string) ($resolution['description'] ?? ''));
            $motionRepo->create($motionId, $tenantId, $meetingId, null, $resTitle, $resDescription, false, null, null);
            $motionsCreated++;
        }
        return [
            'meeting_id' => $meetingId, 'title' => $title,
            'members_created' => $membersCreated, 'members_linked' => $membersLinked,
            'motions_created' => $motionsCreated,
        ];
    }

    /** @return array{deleted: bool, meeting_id: string, delete_warning: array, title: string} */
    public function deleteDraft(string $meetingId, string $tenantId): array {
        $repo = $this->repos->meeting();
        $current = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$current) { throw new RuntimeException('meeting_not_found'); }
        if ((string) $current['status'] === 'live') { throw new RuntimeException('meeting_live_cannot_delete'); }
        if ((string) $current['status'] !== 'draft') { throw new RuntimeException('meeting_not_draft'); }
        $statsRepo = $this->repos->meetingStats();
        $deleteWarning = [
            'motions' => $statsRepo->countMotions($meetingId, $tenantId),
            'ballots' => $statsRepo->countBallots($meetingId, $tenantId),
            'attendances' => $this->repos->wizard()->countAttendances($meetingId, $tenantId),
        ];
        $deleted = $repo->deleteDraft($meetingId, $tenantId);
        return ['deleted' => $deleted > 0, 'meeting_id' => $meetingId, 'delete_warning' => $deleteWarning, 'title' => $current['title']];
    }

    /** @return array{meeting_id: string, status: string} */
    public function validateMeeting(string $meetingId, string $tenantId, string $presidentName): array {
        $repo = $this->repos->meeting();
        $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) { throw new RuntimeException('meeting_not_found'); }
        $pvHtml = (new MeetingReportService())->renderHtml($meetingId, true);
        $repo->markValidated($meetingId, $tenantId);
        $this->repos->meetingReport()->storeHtml($meetingId, $pvHtml, $tenantId);
        return ['meeting_id' => $meetingId, 'status' => 'validated'];
    }

    /** @return array Meeting status with sign readiness */
    public function getStatus(string $tenantId, string $currentRole): array {
        $repo = $this->repos->meeting();
        $meeting = $repo->findCurrentForTenant($tenantId);
        if (!$meeting) { throw new RuntimeException('no_live_meeting'); }
        $statsRepo = $this->repos->meetingStats();
        $counts = $statsRepo->countMotionStats((string) $meeting['meeting_id'], $tenantId);
        $openMotions = (int) ($counts['open_motions'] ?? 0);
        $closedWithoutTally = (int) ($counts['closed_without_tally'] ?? 0);
        $validation = (new MeetingValidator())->canBeValidated((string) $meeting['meeting_id'], $tenantId);
        $readyToSign = (bool) ($validation['can'] ?? false);
        (new NotificationsService())->emitReadinessTransitions((string) $meeting['meeting_id'], $validation, $tenantId);
        $signStatus = 'not_ready'; $signMessage = 'Séance en cours de traitement.';
        if ($meeting['meeting_status'] === 'archived') {
            $signStatus = 'archived'; $signMessage = 'Séance archivée le ' . ($meeting['archived_at'] ?? '—');
        } elseif ($readyToSign) {
            $signStatus = 'ready'; $signMessage = 'Tout est prêt à être signé.';
        } elseif ($openMotions > 0) {
            $signStatus = 'open_motions'; $signMessage = "{$openMotions} résolution(s) encore ouverte(s).";
        } elseif ($closedWithoutTally > 0) {
            $signStatus = 'missing_tally'; $signMessage = "{$closedWithoutTally} résolution(s) clôturée(s) sans comptage complet.";
        }
        return array_merge($meeting, [
            'total_motions' => (int) ($counts['total_motions'] ?? 0), 'open_motions' => $openMotions,
            'closed_without_tally' => $closedWithoutTally, 'ready_to_sign' => $readyToSign,
            'sign_status' => $signStatus, 'sign_message' => $signMessage,
            'can_current_user_validate' => in_array($currentRole, ['president', 'admin'], true),
        ]);
    }

    /** @return array Meeting status fields with sign readiness */
    public function getStatusForMeeting(string $meetingId, string $tenantId): array {
        $repo = $this->repos->meeting();
        $meeting = $repo->findStatusFields($meetingId, $tenantId);
        if (!$meeting) { throw new RuntimeException('meeting_not_found'); }
        $validation = (new MeetingValidator())->canBeValidated($meetingId, $tenantId);
        $readyToSign = (bool) ($validation['can'] ?? false);
        (new NotificationsService())->emitReadinessTransitions($meetingId, $validation, $tenantId);
        $signStatus = 'not_ready'; $signMessage = '';
        if (!empty($meeting['validated_at'])) {
            $signStatus = 'validated'; $signMessage = 'Séance validée.';
        } elseif ($readyToSign) {
            $signStatus = 'ready'; $signMessage = 'Tout est prêt à être signé.';
        } else { $signMessage = 'Préparation incomplète.'; }
        return [
            'meeting_id' => $meeting['meeting_id'], 'meeting_title' => $meeting['meeting_title'],
            'meeting_status' => $meeting['meeting_status'], 'started_at' => $meeting['started_at'],
            'ended_at' => $meeting['ended_at'], 'archived_at' => $meeting['archived_at'],
            'validated_at' => $meeting['validated_at'], 'president_name' => $meeting['president_name'],
            'ready_to_sign' => $readyToSign, 'sign_status' => $signStatus, 'sign_message' => $signMessage,
        ];
    }

    /** @return array Meeting summary with attendance and motion stats */
    public function getSummary(string $meetingId, string $tenantId): array {
        $repo = $this->repos->meeting();
        $s = $this->repos->meetingStats();
        $meeting = $repo->findSummaryFields($meetingId, $tenantId);
        if (!$meeting) { throw new RuntimeException('meeting_not_found'); }
        $totalMembers = $s->countActiveMembers($tenantId);
        $presentCount = $s->countPresent($meetingId, $tenantId);
        $proxyCount = $s->countProxy($meetingId, $tenantId);
        return [
            'meeting_id' => $meetingId, 'meeting_title' => $meeting['title'],
            'status' => $meeting['status'], 'validated_at' => $meeting['validated_at'],
            'president_name' => $meeting['president_name'],
            'data' => [
                'total_members' => $totalMembers, 'present_count' => $presentCount,
                'proxy_count' => $proxyCount, 'absent_count' => max(0, $totalMembers - $presentCount - $proxyCount),
                'motions_count' => $s->countMotions($meetingId, $tenantId),
                'closed_motions_count' => $s->countClosedMotions($meetingId, $tenantId),
                'open_motions_count' => $s->countOpenMotions($meetingId, $tenantId),
                'adopted_count' => $s->countAdoptedMotions($meetingId, $tenantId),
                'rejected_count' => $s->countRejectedMotions($meetingId, $tenantId),
                'ballots_count' => $s->countBallots($meetingId, $tenantId),
                'total_voted_weight' => round($s->sumBallotWeight($meetingId, $tenantId), 2),
                'proxies_count' => $s->countProxies($meetingId, $tenantId),
                'incidents_count' => $s->countIncidents($meetingId, $tenantId),
                'manual_votes_count' => $s->countManualVotes($meetingId, $tenantId),
            ],
        ];
    }

    /** @return array{meeting_id: string, motions_count: int, distinct_voters: int, items: array} */
    public function getStats(string $meetingId, string $tenantId): array {
        $meetingRepo = $this->repos->meeting();
        $statsRepo = $this->repos->meetingStats();
        $motionRepo = $this->repos->motion();
        if (!$meetingRepo->existsForTenant($meetingId, $tenantId)) { throw new RuntimeException('meeting_not_found'); }
        $motionsCount = $statsRepo->countMotions($meetingId, $tenantId);
        $rows = $motionRepo->listStatsForMeeting($meetingId, $tenantId);
        $motions = [];
        $totalBallotsAll = 0;
        foreach ($rows as $r) {
            $bt = (int) ($r['ballots_total'] ?? 0);
            if ($bt > 0) {
                $src = 'ballots'; $tot = $bt;
                $vf = (int) ($r['ballots_for'] ?? 0); $va = (int) ($r['ballots_against'] ?? 0);
                $vab = (int) ($r['ballots_abstain'] ?? 0); $vn = (int) ($r['ballots_nsp'] ?? 0);
                $totalBallotsAll += $bt;
            } else {
                $src = \AgVote\Core\BallotSource::MANUAL; $tot = (int) ($r['manual_total'] ?? 0);
                $vf = (int) ($r['manual_for'] ?? 0); $va = (int) ($r['manual_against'] ?? 0);
                $vab = (int) ($r['manual_abstain'] ?? 0); $vn = max(0, $tot - $vf - $va - $vab);
            }
            $motions[] = [
                'motion_id' => $r['motion_id'], 'title' => $r['title'],
                'total' => $tot, 'votes_for' => $vf, 'votes_against' => $va,
                'votes_abstain' => $vab, 'votes_nsp' => $vn, 'tally_source' => $src,
                'manual_total' => (int) ($r['manual_total'] ?? 0), 'manual_for' => (int) ($r['manual_for'] ?? 0),
                'manual_against' => (int) ($r['manual_against'] ?? 0), 'manual_abstain' => (int) ($r['manual_abstain'] ?? 0),
                'ballots_total' => $bt,
            ];
        }
        $dv = $totalBallotsAll > 0 ? $motionRepo->countDistinctVoters($meetingId, $tenantId) : $motionRepo->maxManualTotal($meetingId, $tenantId);
        return ['meeting_id' => $meetingId, 'motions_count' => (int) $motionsCount, 'distinct_voters' => (int) $dv, 'items' => $motions];
    }
}
