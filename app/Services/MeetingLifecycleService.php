<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Core\Providers\RepositoryFactory;
use InvalidArgumentException;
use RuntimeException;

/**
 * Business logic extracted from MeetingsController.
 *
 * Handles meeting CRUD, status queries, and validation.
 * Controllers remain thin HTTP adapters that delegate here.
 */
final class MeetingLifecycleService {
    private RepositoryFactory $repos;

    public function __construct(?RepositoryFactory $repos = null) {
        $this->repos = $repos ?? RepositoryFactory::getInstance();
    }

    /**
     * Update meeting fields (title, president_name, scheduled_at, meeting_type).
     *
     * @return array{updated: bool, meeting_id: string}
     */
    public function updateMeeting(string $meetingId, string $tenantId, array $data): array {
        $title = array_key_exists('title', $data) ? trim((string) $data['title']) : null;
        $presidentName = array_key_exists('president_name', $data) ? trim((string) $data['president_name']) : null;
        $scheduledAt = array_key_exists('scheduled_at', $data) ? trim((string) $data['scheduled_at']) : null;
        $meetingType = array_key_exists('meeting_type', $data) ? trim((string) $data['meeting_type']) : null;

        if (array_key_exists('status', $data)) {
            throw new InvalidArgumentException('Les transitions de statut doivent passer par /api/v1/meeting_transition.php.');
        }

        if ($title !== null) {
            $len = mb_strlen($title);
            if ($len === 0) {
                throw new InvalidArgumentException('Le titre de la séance est obligatoire.');
            }
            if ($len > 120) {
                throw new InvalidArgumentException('Titre trop long (120 max).');
            }
        }

        if ($presidentName !== null && mb_strlen($presidentName) > 200) {
            throw new InvalidArgumentException('Nom du président trop long (200 max).');
        }

        $validMeetingTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];
        if ($meetingType !== null && !in_array($meetingType, $validMeetingTypes, true)) {
            throw new InvalidArgumentException('Type de séance invalide.');
        }

        $repo = $this->repos->meeting();
        $current = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$current) {
            throw new RuntimeException('meeting_not_found');
        }

        if ((string) $current['status'] === 'archived') {
            throw new RuntimeException('Séance archivée : modification interdite.');
        }

        $fields = [];
        if ($title !== null) {
            $fields['title'] = $title;
        }
        if ($presidentName !== null) {
            $fields['president_name'] = $presidentName;
        }
        if ($scheduledAt !== null) {
            $fields['scheduled_at'] = $scheduledAt ?: null;
        }
        if ($meetingType !== null) {
            $fields['meeting_type'] = $meetingType;
        }

        if (!$fields) {
            return ['updated' => false, 'meeting_id' => $meetingId];
        }

        $updated = $repo->updateFields($meetingId, $tenantId, $fields);

        return ['updated' => $updated > 0, 'meeting_id' => $meetingId, 'fields' => array_keys($fields)];
    }

    /**
     * Create a meeting with members and resolutions from wizard data.
     *
     * @return array{meeting_id: string, title: string, members_created: int, members_linked: int, motions_created: int}
     */
    public function createFromWizard(array $data, string $tenantId): array {
        $title = trim((string) ($data['title'] ?? ''));
        if (mb_strlen($title) < 3) {
            throw new InvalidArgumentException('Le titre est obligatoire (3 caractères minimum).');
        }

        $meetingType = trim((string) ($data['type'] ?? 'ag_ordinaire')) ?: 'ag_ordinaire';
        $validMeetingTypes = ['ag_ordinaire', 'ag_extraordinaire', 'conseil', 'bureau', 'autre'];
        if (!in_array($meetingType, $validMeetingTypes, true)) {
            $meetingType = 'ag_ordinaire';
        }

        $description = trim((string) ($data['description'] ?? '')) ?: null;
        $location = trim((string) ($data['place'] ?? '')) ?: null;

        $dateStr = trim((string) ($data['date'] ?? ''));
        $timeStr = trim((string) ($data['time'] ?? ''));
        $scheduledAt = null;
        if ($dateStr !== '') {
            $scheduledAt = $dateStr . ' ' . ($timeStr !== '' ? $timeStr : '00:00') . ':00';
        }

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
            $votePolicies = $policyRepo->listVotePolicies($tenantId);
            if (!empty($votePolicies)) {
                $defaults['vote_policy_id'] = $votePolicies[0]['id'];
            }
        }
        if ($quorumPolicyId !== null && api_is_uuid($quorumPolicyId)) {
            $defaults['quorum_policy_id'] = $quorumPolicyId;
        } else {
            $quorumPolicies = $policyRepo->listQuorumPolicies($tenantId);
            if (!empty($quorumPolicies)) {
                $defaults['quorum_policy_id'] = $quorumPolicies[0]['id'];
            }
        }
        if ($defaults) {
            $repo->updateFields($meetingId, $tenantId, $defaults);
        }

        $membersCreated = 0;
        $membersLinked = 0;

        foreach ($members as $member) {
            $nom = trim((string) ($member['nom'] ?? ''));
            $email = trim((string) ($member['email'] ?? ''));
            $voteWeight = (float) ($member['voix'] ?? 1);

            if ($nom === '') {
                throw new InvalidArgumentException('Le nom est obligatoire.');
            }
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Email invalide.');
            }

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
            if ($resTitle === '') {
                throw new InvalidArgumentException('Le titre de la résolution est obligatoire.');
            }

            $motionId = $motionRepo->generateUuid();
            $resDescription = trim((string) ($resolution['description'] ?? ''));
            $motionRepo->create(
                $motionId, $tenantId, $meetingId, null, $resTitle, $resDescription, false, null, null,
            );
            $motionsCreated++;
        }

        return [
            'meeting_id' => $meetingId,
            'title' => $title,
            'members_created' => $membersCreated,
            'members_linked' => $membersLinked,
            'motions_created' => $motionsCreated,
        ];
    }

    /**
     * Delete a draft meeting.
     *
     * @return array{deleted: bool, meeting_id: string, delete_warning: array}
     */
    public function deleteDraft(string $meetingId, string $tenantId): array {
        $repo = $this->repos->meeting();
        $current = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$current) {
            throw new RuntimeException('meeting_not_found');
        }

        if ((string) $current['status'] === 'live') {
            throw new RuntimeException('meeting_live_cannot_delete');
        }
        if ((string) $current['status'] !== 'draft') {
            throw new RuntimeException('meeting_not_draft');
        }

        $statsRepo = $this->repos->meetingStats();
        $deleteWarning = [
            'motions'     => $statsRepo->countMotions($meetingId, $tenantId),
            'ballots'     => $statsRepo->countBallots($meetingId, $tenantId),
            'attendances' => $this->repos->wizard()->countAttendances($meetingId, $tenantId),
        ];

        $deleted = $repo->deleteDraft($meetingId, $tenantId);

        return ['deleted' => $deleted > 0, 'meeting_id' => $meetingId, 'delete_warning' => $deleteWarning, 'title' => $current['title']];
    }

    /**
     * Validate a meeting (mark as validated with PV HTML).
     *
     * @return array{meeting_id: string, status: string}
     */
    public function validateMeeting(string $meetingId, string $tenantId, string $presidentName): array {
        $repo = $this->repos->meeting();
        $meeting = $repo->findByIdForTenant($meetingId, $tenantId);
        if (!$meeting) {
            throw new RuntimeException('meeting_not_found');
        }

        $pvHtml = (new MeetingReportService())->renderHtml($meetingId, true);
        $repo->markValidated($meetingId, $tenantId);
        $this->repos->meetingReport()->storeHtml($meetingId, $pvHtml, $tenantId);

        return ['meeting_id' => $meetingId, 'status' => 'validated'];
    }

}
