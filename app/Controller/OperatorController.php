<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\AttendanceRepository;
use AgVote\Repository\BallotRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Repository\MotionRepository;
use AgVote\Repository\PolicyRepository;
use AgVote\Repository\VoteTokenRepository;
use AgVote\Service\MeetingValidator;
use AgVote\Service\NotificationsService;

/**
 * Consolidates 3 operator endpoints.
 *
 * Shared pattern: operator role, meeting/motion/member/ballot repos, complex state queries.
 */
final class OperatorController extends AbstractController
{
    public function workflowState(): void
    {
        api_require_role('operator');

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('missing_meeting_id', 400);
        }

        $minOpen = (int)($_GET['min_open'] ?? 900);
        $minParticipation = (float)($_GET['min_participation'] ?? 0.5);

        $tenant = api_current_tenant_id();

        $meetingRepo = new MeetingRepository();
        $memberRepo = new MemberRepository();
        $motionRepo = new MotionRepository();
        $ballotRepo = new BallotRepository();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, $tenant);
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        $eligibleMembers = $memberRepo->countActive($tenant);

        $attRows = $memberRepo->listWithAttendanceForMeeting($meetingId, $tenant);

        $presentCount = 0;
        $presentWeight = 0.0;
        $totalCount = count($attRows);
        $totalWeight = 0.0;
        $absentIds = [];
        $absentNames = [];

        foreach ($attRows as $r) {
            $vp = (float)($r['voting_power'] ?? 0);
            $totalWeight += $vp;
            $mode = (string)($r['attendance_mode'] ?? '');
            if ($mode === 'present' || $mode === 'remote' || $mode === 'proxy') {
                $presentCount++;
                $presentWeight += $vp;
            } else {
                $mid = (string)$r['member_id'];
                $absentIds[] = $mid;
                $absentNames[$mid] = (string)($r['full_name'] ?? '');
            }
        }

        // Load quorum threshold from configured policy (fallback to 50%)
        $quorumThreshold = 0.5;
        $quorumPolicyId = $meeting['quorum_policy_id'] ?? null;
        if ($quorumPolicyId) {
            $policyRepo = new PolicyRepository();
            $quorumPolicy = $policyRepo->findQuorumPolicyForTenant($quorumPolicyId, $tenant);
            if ($quorumPolicy && isset($quorumPolicy['threshold'])) {
                $quorumThreshold = (float)$quorumPolicy['threshold'];
            }
        }
        $quorumRatio = $eligibleMembers > 0 ? ($presentCount / $eligibleMembers) : 0.0;
        $quorumOk = $presentCount > 0 && $quorumRatio >= $quorumThreshold;

        $coveredRows = $meetingRepo->listDistinctProxyGivers($meetingId);
        $coveredSet = [];
        foreach ($coveredRows as $x) {
            $coveredSet[(string)$x['giver_member_id']] = true;
        }

        $missing = [];
        foreach ($absentIds as $mid) {
            if (!isset($coveredSet[$mid])) {
                $missing[] = $mid;
            }
        }
        $missingNames = array_values(array_filter(array_map(fn($id) => $absentNames[$id] ?? '', $missing)));

        $proxyActive = $meetingRepo->countActiveProxies($tenant, $meetingId);

        $motions = $motionRepo->countWorkflowSummary($meetingId);

        $openMotion = $motionRepo->findCurrentOpen($meetingId, $tenant);
        $nextMotion = $motionRepo->findNextNotOpened($meetingId);
        $lastClosedMotion = $motionRepo->findLastClosedForProjector($meetingId);

        $hasAnyMotion = ((int)($motions['total'] ?? 0)) > 0;

        $openBallots = 0;
        $openAgeSeconds = 0;
        $participationRatio = null;
        $potentialVoters = $presentCount + count($coveredSet);
        $closeBlockers = [];
        $canCloseOpen = false;

        if ($openMotion) {
            $openBallots = $ballotRepo->countByMotionId($openMotion['id']);
            if (!empty($openMotion['opened_at'])) {
                $openAgeSeconds = max(0, time() - strtotime($openMotion['opened_at']));
            }
            $participationRatio = $potentialVoters > 0 ? ($openBallots / $potentialVoters) : 0.0;

            if ($openAgeSeconds < $minOpen) {
                $closeBlockers[] = "Délai minimum non atteint ({$openAgeSeconds}s / {$minOpen}s).";
            }
            if ($participationRatio < $minParticipation) {
                $closeBlockers[] = "Participation insuffisante (" . round($participationRatio * 100) . "%, min " . round($minParticipation * 100) . "%).";
            }
            $canCloseOpen = count($closeBlockers) === 0;
        }

        $canOpenNext = $quorumOk && (count($missing) === 0) && ($openMotion === null) && ($nextMotion !== null);

        $hasClosed = $meetingRepo->countClosedMotions($meetingId);
        $canConsolidate = ((int)($motions['open'] ?? 0)) === 0 && $hasClosed > 0;
        $consolidatedCount = $motionRepo->countConsolidatedMotions($meetingId);
        $consolidationDone = ($hasClosed > 0) && ($consolidatedCount >= $hasClosed);

        $consolidateDetail = $canConsolidate
            ? "Motions fermées: $hasClosed. Vous pouvez consolider."
            : (((int)($motions['open'] ?? 0)) > 0
                ? "Fermez toutes les motions ouvertes avant consolidation."
                : "Aucune motion fermée à consolider.");

        $validation = MeetingValidator::canBeValidated($meetingId, $tenant);
        $ready = (bool)($validation['can'] ?? false);
        $reasons = (array)($validation['reasons'] ?? []);

        NotificationsService::emitReadinessTransitions($meetingId, $validation);

        api_ok([
            'meeting' => [
                'id' => $meeting['id'],
                'title' => $meeting['title'] ?? '',
                'status' => $meeting['status'] ?? '',
                'president_name' => $meeting['president_name'] ?? '',
            ],
            'motions' => [
                'total' => (int)($motions['total'] ?? 0),
                'open' => (int)($motions['open'] ?? 0),
            ],
            'attendance' => [
                'ok' => $presentCount > 0,
                'present_count' => $presentCount,
                'present_weight' => $presentWeight,
                'total_count' => $totalCount,
                'total_weight' => $totalWeight,
                'quorum_threshold' => $quorumThreshold,
                'quorum_ratio' => round($quorumRatio, 4),
                'quorum_ok' => $quorumOk,
            ],
            'proxies' => [
                'ok' => $quorumOk && (count($missing) === 0),
                'active_count' => $proxyActive,
                'missing_absent_without_proxy' => count($missing),
                'missing_names' => $missingNames,
            ],
            'tokens' => ['disabled' => true],
            'motion' => [
                'has_any_motion' => $hasAnyMotion,
                'open_motion_id' => $openMotion['id'] ?? null,
                'open_title' => $openMotion['title'] ?? null,
                'open_ballots' => $openBallots,
                'open_age_seconds' => $openAgeSeconds,
                'potential_voters' => $potentialVoters,
                'participation_ratio' => $participationRatio !== null ? round($participationRatio, 4) : null,
                'close_blockers' => $closeBlockers,
                'next_motion_id' => $nextMotion['id'] ?? null,
                'next_title' => $nextMotion['title'] ?? null,
                'can_open_next' => $canOpenNext,
                'can_close_open' => $canCloseOpen,
                'last_closed_motion_id' => $lastClosedMotion['id'] ?? null,
                'last_closed_title' => $lastClosedMotion['title'] ?? null,
            ],
            'consolidation' => [
                'can' => $canConsolidate,
                'done' => $consolidationDone,
                'detail' => $consolidateDetail,
                'closed_motions' => $hasClosed,
                'consolidated_motions' => $consolidatedCount,
            ],
            'validation' => [
                'ready' => $ready,
                'reasons' => $reasons,
            ],
        ]);
    }

    public function openVote(): void
    {
        api_require_role('operator');
        $input = api_request('POST');

        $meetingId = trim((string)($input['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 422);
        }

        api_guard_meeting_not_validated($meetingId);

        $motionId = trim((string)($input['motion_id'] ?? ''));
        if ($motionId !== '' && !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }

        $listTokens = (string)($input['list'] ?? '') === '1';
        $expiresMinutes = (int)($input['expires_minutes'] ?? 120);
        if ($expiresMinutes < 10) {
            $expiresMinutes = 10;
        }
        if ($expiresMinutes > 24 * 60) {
            $expiresMinutes = 24 * 60;
        }

        $secret = (defined('APP_SECRET') && (string)APP_SECRET !== '')
            ? (string)APP_SECRET
            : (getenv('APP_SECRET') ?: 'change-me-in-prod');

        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();
        $memberRepo = new MemberRepository();
        $attendanceRepo = new AttendanceRepository();
        $tokenRepo = new VoteTokenRepository();

        db()->beginTransaction();
        try {
            $meeting = $meetingRepo->lockForUpdate($meetingId, api_current_tenant_id());
            if (!$meeting) {
                db()->rollBack();
                api_fail('meeting_not_found', 404);
            }
            if (!empty($meeting['validated_at'])) {
                db()->rollBack();
                api_fail('meeting_validated_locked', 409, ['detail' => "Séance validée : action interdite."]);
            }

            $status = (string)($meeting['status'] ?? '');
            if ($status !== 'live') {
                $meetingRepo->updateFields($meetingId, api_current_tenant_id(), ['status' => 'live']);
            }

            if ($motionId === '') {
                $next = $motionRepo->findNextNotOpenedForUpdate(api_current_tenant_id(), $meetingId);
                if (!$next) {
                    db()->rollBack();
                    api_fail('no_motion_to_open', 409, ['detail' => "Aucune résolution disponible à ouvrir."]);
                }
                $motionId = (string)$next['id'];
            } else {
                $row = $motionRepo->findByIdAndMeetingForUpdate(api_current_tenant_id(), $meetingId, $motionId);
                if (!$row) {
                    db()->rollBack();
                    api_fail('motion_not_found', 404);
                }
            }

            $open = $motionRepo->findCurrentOpen($meetingId, api_current_tenant_id());
            if ($open && (string)$open['id'] !== $motionId) {
                db()->rollBack();
                api_fail('another_motion_active', 409, [
                    'detail' => "Une résolution est déjà ouverte : clôturez-la avant d'en ouvrir une autre.",
                    'open_motion_id' => (string)$open['id'],
                ]);
            }

            $motionRepo->markOpenedInMeeting(api_current_tenant_id(), $motionId, $meetingId);
            $meetingRepo->updateCurrentMotion($meetingId, api_current_tenant_id(), $motionId);

            $eligible = $attendanceRepo->listEligibleMemberIds(api_current_tenant_id(), $meetingId);
            if (!$eligible) {
                $eligible = $memberRepo->listByMeetingFallback(api_current_tenant_id(), $meetingId);
            }

            $inserted = 0;
            $tokensOut = [];

            foreach ($eligible as $e) {
                $memberId = (string)$e['member_id'];
                if ($memberId === '') {
                    continue;
                }

                $raw = sprintf(
                    '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    random_int(0, 0xffff), random_int(0, 0xffff),
                    random_int(0, 0xffff),
                    random_int(0, 0x0fff) | 0x4000,
                    random_int(0, 0x3fff) | 0x8000,
                    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
                );

                $hash = hash_hmac('sha256', $raw, $secret);

                $active = $tokenRepo->findActiveForMember(api_current_tenant_id(), $meetingId, $motionId, $memberId);
                if ($active) {
                    continue;
                }

                $ok = $tokenRepo->insertWithExpiry($hash, api_current_tenant_id(), $meetingId, $memberId, $motionId, $expiresMinutes);

                if ($ok > 0) {
                    $inserted++;
                    if ($listTokens) {
                        $tokensOut[] = [
                            'member_id' => $memberId,
                            'token' => $raw,
                            'url' => "/vote.php?token=" . $raw,
                        ];
                    }
                }
            }

            db()->commit();

            audit_log('vote_tokens_generated', 'motion', $motionId, [
                'meeting_id' => $meetingId,
                'inserted' => $inserted,
                'expires_minutes' => $expiresMinutes,
            ]);

            api_ok([
                'meeting_id' => $meetingId,
                'motion_id' => $motionId,
                'generated' => $inserted,
                'tokens' => $listTokens ? $tokensOut : null,
            ]);
        } catch (\Throwable $e) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            api_fail('operator_open_vote_failed', 500, ['detail' => $e->getMessage()]);
        }
    }

    public function anomalies(): void
    {
        api_require_role('operator');
        api_request('GET');

        $meetingId = trim((string)($_GET['meeting_id'] ?? ''));
        if ($meetingId === '' || !api_is_uuid($meetingId)) {
            api_fail('invalid_meeting_id', 422);
        }

        $motionId = trim((string)($_GET['motion_id'] ?? ''));
        if ($motionId !== '' && !api_is_uuid($motionId)) {
            api_fail('invalid_motion_id', 422);
        }

        $meetingRepo = new MeetingRepository();
        $motionRepo = new MotionRepository();
        $memberRepo = new MemberRepository();
        $ballotRepo = new BallotRepository();
        $tokenRepo = new VoteTokenRepository();

        $meeting = $meetingRepo->findByIdForTenant($meetingId, api_current_tenant_id());
        if (!$meeting) {
            api_fail('meeting_not_found', 404);
        }

        if ($motionId === '') {
            $open = $motionRepo->findCurrentOpen($meetingId, api_current_tenant_id());
            $motionId = $open ? (string)$open['id'] : '';
        }

        $motion = null;
        if ($motionId !== '') {
            $motion = $motionRepo->findByMeetingWithDates(api_current_tenant_id(), $meetingId, $motionId);
            if (!$motion) {
                api_fail('motion_not_found', 404);
            }
        }

        $eligibleRows = $memberRepo->listEligibleForMeeting(api_current_tenant_id(), $meetingId);
        if (!$eligibleRows) {
            $eligibleRows = $memberRepo->listActiveFallbackByMeeting(api_current_tenant_id(), $meetingId);
        }

        $eligibleIds = [];
        $eligibleNames = [];
        foreach ($eligibleRows as $r) {
            $id = (string)($r['member_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $eligibleIds[] = $id;
            $eligibleNames[$id] = (string)($r['full_name'] ?? '');
        }

        $eligibleCount = count($eligibleIds);

        $proxyMax = (int)($_ENV['PROXY_MAX_PER_RECEIVER'] ?? getenv('PROXY_MAX_PER_RECEIVER') ?? 99);
        $proxyCeilings = [];
        try {
            $rows = $meetingRepo->listProxyCeilingViolations(api_current_tenant_id(), $meetingId, $proxyMax);
            foreach ($rows as $r) {
                $pid = (string)$r['proxy_id'];
                $proxyCeilings[] = [
                    'proxy_id' => $pid,
                    'proxy_name' => $eligibleNames[$pid] ?? null,
                    'count' => (int)$r['c'],
                    'max' => $proxyMax,
                ];
            }
        } catch (\Throwable $e) {
            $proxyCeilings = [];
        }

        $stats = [
            'tokens_active_unused' => 0,
            'tokens_expired_unused' => 0,
            'tokens_used' => 0,
            'ballots_total' => 0,
            'ballots_from_eligible' => 0,
            'eligible_expected' => $eligibleCount,
            'missing_ballots_from_eligible' => 0,
        ];

        $missingNames = [];
        $ballotsNotEligible = [];
        $duplicates = [];

        if ($motionId !== '') {
            $stats['tokens_active_unused'] = $tokenRepo->countActiveUnused(api_current_tenant_id(), $meetingId, $motionId);
            $stats['tokens_expired_unused'] = $tokenRepo->countExpiredUnused(api_current_tenant_id(), $meetingId, $motionId);
            $stats['tokens_used'] = $tokenRepo->countUsed(api_current_tenant_id(), $meetingId, $motionId);

            $ballots = $ballotRepo->listForMotionWithSource(api_current_tenant_id(), $meetingId, $motionId);
            $stats['ballots_total'] = count($ballots);

            $votedSet = [];
            foreach ($ballots as $b) {
                $mid = (string)($b['member_id'] ?? '');
                if ($mid === '') {
                    continue;
                }

                if (isset($votedSet[$mid])) {
                    $duplicates[] = [
                        'member_id' => $mid,
                        'name' => $eligibleNames[$mid] ?? null,
                        'detail' => 'duplicate_ballot_for_member',
                    ];
                }
                $votedSet[$mid] = true;

                if (!in_array($mid, $eligibleIds, true)) {
                    $ballotsNotEligible[] = [
                        'member_id' => $mid,
                        'value' => (string)($b['value'] ?? ''),
                        'source' => (string)($b['source'] ?? ''),
                        'cast_at' => $b['cast_at'],
                    ];
                }
            }

            $eligibleVoted = 0;
            foreach ($eligibleIds as $id) {
                if (isset($votedSet[$id])) {
                    $eligibleVoted++;
                }
            }
            $stats['ballots_from_eligible'] = $eligibleVoted;
            $stats['missing_ballots_from_eligible'] = max(0, $eligibleCount - $eligibleVoted);

            if ($stats['missing_ballots_from_eligible'] > 0) {
                foreach ($eligibleIds as $id) {
                    if (!isset($votedSet[$id])) {
                        $missingNames[] = $eligibleNames[$id] ?? $id;
                        if (count($missingNames) >= 30) {
                            break;
                        }
                    }
                }
            }
        }

        api_ok([
            'meeting' => [
                'id' => $meetingId,
                'status' => (string)($meeting['status'] ?? ''),
                'validated_at' => $meeting['validated_at'],
            ],
            'motion' => $motion ? [
                'id' => (string)$motion['id'],
                'title' => (string)($motion['title'] ?? ''),
                'opened_at' => $motion['opened_at'],
                'closed_at' => $motion['closed_at'],
            ] : null,
            'eligibility' => [
                'expected_count' => $eligibleCount,
            ],
            'stats' => $stats,
            'anomalies' => [
                'missing_voters_sample' => $missingNames,
                'ballots_not_eligible' => $ballotsNotEligible,
                'duplicates' => $duplicates,
            ],
        ]);
    }
}
