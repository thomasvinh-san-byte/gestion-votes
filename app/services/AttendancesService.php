<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\MeetingRepository;

/**
 * Gestion des présences (check-in) par séance.
 *
 * Objectif MVP:
 * - Un membre est "présent" si une ligne attendances existe (mode present/remote/proxy)
 * - Un membre est "absent" si aucune ligne n'existe
 * - effective_power est figé au moment du check-in (voting_power du membre)
 */
final class AttendancesService
{
    /**
     * Indique si un membre est "présent" (mode present/remote/proxy, non checked_out) pour une séance.
     *
     * Utilisé pour l'éligibilité au vote (public).
     */
    public static function isPresent(string $meetingId, string $memberId, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        if ($meetingId === '' || $memberId === '') {
            return false;
        }

        $repo = new AttendanceRepository();
        return $repo->isPresent($meetingId, $memberId, $tenantId);
    }

    /**
     * Indique si un membre est présent "directement" (present/remote uniquement).
     *
     * Recommandé pour contrôler l'éligibilité au vote afin d'éviter les chaînes de procuration.
     */
    public static function isPresentDirect(string $meetingId, string $memberId, ?string $tenantId = null): bool
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        if ($meetingId === '' || $memberId === '') {
            return false;
        }

        $repo = new AttendanceRepository();
        return $repo->isPresentDirect($meetingId, $memberId, $tenantId);
    }

    /**
     * Liste les présences d'une séance avec infos membre.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function listForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id est obligatoire');
        }

        $repo = new AttendanceRepository();
        return $repo->listForMeeting($meetingId, $tenantId);
    }

    /**
     * Résumé (nb + poids) de la séance pour les modes présents.
     *
     * @return array{present_count:int,present_weight:float}
     */
    public static function summaryForMeeting(string $meetingId, ?string $tenantId = null): array
    {
        $tenantId = $tenantId ?: (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);
        $meetingId = trim($meetingId);
        if ($meetingId === '') {
            throw new InvalidArgumentException('meeting_id est obligatoire');
        }

        $repo = new AttendanceRepository();
        return $repo->summaryForMeeting($meetingId, $tenantId);
    }

    /**
     * Upsert présence.
     * - mode: present|remote|proxy => upsert actif (checked_out_at NULL)
     * - mode: absent => suppression (MVP)
     *
     * @return array<string,mixed> ligne présence (ou {deleted:true})
     */
    public static function upsert(string $meetingId, string $memberId, string $mode, ?string $notes = null): array
    {
        $meetingId = trim($meetingId);
        $memberId = trim($memberId);
        $mode = trim($mode);

        if ($meetingId === '' || $memberId === '') {
            throw new InvalidArgumentException('meeting_id et member_id sont obligatoires');
        }

        $allowed = ['present','remote','proxy','absent'];
        if (!in_array($mode, $allowed, true)) {
            throw new InvalidArgumentException("mode invalide (present/remote/proxy/absent)");
        }

        $tenantId = (string)($GLOBALS['APP_TENANT_ID'] ?? DEFAULT_TENANT_ID);

        // Vérifier meeting appartient au tenant
        $meetingRepo = new MeetingRepository();
        $meeting = $meetingRepo->findById($meetingId);
        if (!$meeting) {
            throw new RuntimeException('Séance introuvable');
        }
        if ((string)$meeting['tenant_id'] !== $tenantId) {
            throw new RuntimeException('Séance hors tenant');
        }
        if ((string)$meeting['status'] === 'archived') {
            throw new RuntimeException('Séance archivée : présence non modifiable');
        }

        // Charger membre + pouvoir de vote
        $member = MembersService::getMember($memberId);
        if ((string)$member['tenant_id'] !== $tenantId) {
            throw new RuntimeException('Membre hors tenant');
        }

        $attendanceRepo = new AttendanceRepository();

        if ($mode === 'absent') {
            $attendanceRepo->deleteByMeetingAndMember($meetingId, $memberId);
            return ['deleted' => true, 'meeting_id' => $meetingId, 'member_id' => $memberId];
        }

        $effective = (float)($member['voting_power'] ?? $member['vote_weight'] ?? 1.0);

        $row = $attendanceRepo->upsert($meetingId, $memberId, $mode, $effective, $notes);
        if (!$row) {
            throw new RuntimeException('Erreur upsert présence');
        }
        return $row;
    }
}
