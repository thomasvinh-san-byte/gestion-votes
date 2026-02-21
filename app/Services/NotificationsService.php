<?php

declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\MeetingRepository;
use AgVote\Repository\NotificationRepository;

/**
 * NotificationsService (MVP)
 * - Stores contextual notifications per meeting
 * - Designed for polling (since_id)
 * - Best-effort anti-spam: deduplication within a short window
 */
final class NotificationsService {
    private MeetingRepository $meetingRepo;
    private NotificationRepository $notifRepo;

    public function __construct(
        ?MeetingRepository $meetingRepo = null,
        ?NotificationRepository $notifRepo = null,
    ) {
        $this->meetingRepo = $meetingRepo ?? new MeetingRepository();
        $this->notifRepo = $notifRepo ?? new NotificationRepository();
    }

    /**
     * Emits notifications when readiness state changes (without spam).
     *
     * @param array<string,mixed> $validation Return from MeetingValidator::canBeValidated
     */
    public function emitReadinessTransitions(string $meetingId, array $validation, string $tenantId): void {
        $row = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$row) {
            return;
        }

        $ready = (bool) ($validation['can'] ?? false);
        $codes = $validation['codes'] ?? [];
        if (!is_array($codes)) {
            $codes = [];
        }
        $codes = array_values(array_unique(array_map('strval', $codes)));
        sort($codes);

        $prev = $this->notifRepo->findValidationState($meetingId);

        $prevReady = null;
        $prevCodes = [];
        if ($prev) {
            $prevReady = (bool) $prev['ready'];
            $prevCodes = json_decode((string) ($prev['codes'] ?? '[]'), true);
            if (!is_array($prevCodes)) {
                $prevCodes = [];
            }
            $prevCodes = array_values(array_unique(array_map('strval', $prevCodes)));
            sort($prevCodes);
        }

        // State upsert (before emitting to avoid duplicates on concurrent calls)
        $this->notifRepo->upsertValidationState($meetingId, $tenantId, $ready, json_encode($codes, JSON_UNESCAPED_UNICODE));

        // First pass: initialize silently.
        if ($prevReady === null) {
            return;
        }

        // Global transition
        if ($prevReady === false && $ready === true) {
            $this->emit($meetingId, 'info', 'readiness_ready', 'Séance prête à validation du Président.', ['operator', 'trust'], [
                'action_label' => 'Aller à la validation',
                'action_url' => '/trust.htmx.html',
            ], $tenantId);
            return;
        }
        if ($prevReady === true && $ready === false) {
            // Don't spam with all details here: "reasons" follow via per-code notifications (below).
            $this->emit($meetingId, 'warn', 'readiness_not_ready', 'Séance n\'est plus prête à être validée.', ['operator', 'trust'], [
                'action_label' => 'Voir les blocages',
                'action_url' => '/operator.htmx.html',
            ], $tenantId);
        }

        // Code diff (blockers): only notify additions / resolutions
        $added = array_values(array_diff($codes, $prevCodes));
        $removed = array_values(array_diff($prevCodes, $codes));

        foreach ($added as $code) {
            $tpl = $this->readinessTemplate($code, true);
            $this->emit($meetingId, $tpl['severity'], 'readiness_' . $code, $tpl['message'], $tpl['audience'], $tpl['data'], $tenantId);
        }
        foreach ($removed as $code) {
            $tpl = $this->readinessTemplate($code, false);
            $this->emit($meetingId, $tpl['severity'], 'readiness_' . $code . '_resolved', $tpl['message'], $tpl['audience'], $tpl['data'], $tenantId);
        }
    }

    /**
     * @return array{severity:string,message:string,audience:array<int,string>,data:array<string,mixed>}
     */
    private function readinessTemplate(string $code, bool $added): array {
        // Default: blocker for operator + president (trust)
        $aud = ['operator', 'trust'];

        switch ($code) {
            case 'missing_president':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message' => $added ? 'Blocage: Président non renseigné.' : 'Résolu: Président renseigné.',
                    'audience' => $aud,
                    'data' => [
                        'action_label' => 'Renseigner le Président',
                        'action_url' => '/validate.htmx.html',
                    ],
                ];
            case 'open_motions':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message' => $added ? 'Blocage: une ou plusieurs motions sont encore ouvertes.' : 'Résolu: toutes les motions sont clôturées.',
                    'audience' => $aud,
                    'data' => [
                        'action_label' => 'Aller aux votes',
                        'action_url' => '/operator.htmx.html',
                    ],
                ];
            case 'bad_closed_results':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message' => $added ? 'Blocage: motion clôturée sans résultat exploitable (manuel ou e-vote).' : 'Résolu: résultats exploitables sur les motions clôturées.',
                    'audience' => $aud,
                    'data' => [
                        'action_label' => 'Corriger le comptage',
                        'action_url' => '/operator.htmx.html',
                    ],
                ];
            case 'consolidation_missing':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message' => $added ? 'Blocage: consolidation non effectuée (résultats officiels manquants).' : 'Résolu: consolidation effectuée.',
                    'audience' => $aud,
                    'data' => [
                        'action_label' => 'Consolider',
                        'action_url' => '/operator.htmx.html',
                    ],
                ];
        }

        return [
            'severity' => $added ? 'warn' : 'info',
            'message' => $added ? ('Blocage: ' . $code) : ('Résolu: ' . $code),
            'audience' => $aud,
            'data' => [
                'action_label' => 'Ouvrir la séance',
                'action_url' => '/operator.htmx.html',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string> $audience
     */
    public function emit(
        string $meetingId,
        string $severity,
        string $code,
        string $message,
        array $audience = ['operator', 'trust'],
        array $data = [],
        string $tenantId,
    ): void {
        $row = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);
        if (!$row) {
            return;
        }

        // Best-effort deduplication: same code + message + meeting in last 10 seconds
        $recent = $this->notifRepo->countRecentDuplicates($meetingId, $code, $message);
        if ($recent > 0) {
            return;
        }

        // Normalize audience: no duplicates / no empty values
        $aud = array_values(array_unique(array_filter(array_map('strval', $audience), fn ($x) => trim($x) !== '')));
        if (!$aud) {
            $aud = ['operator', 'trust'];
        }

        // Robust array literal: {"operator","trust"}
        $audLiteral = '{' . implode(',', array_map(function (string $x): string {
            $x = str_replace('"', '""', $x);
            return '"' . $x . '"';
        }, $aud)) . '}';

        $this->notifRepo->insert($tenantId, $meetingId, $severity, $code, $message, $audLiteral, json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function list(string $meetingId, string $audience = 'operator', int $sinceId = 0, int $limit = 30): array {
        return $this->notifRepo->listSinceId($meetingId, $sinceId, $limit, $audience);
    }

    /**
     * Recent notifications (for UI initialization) - DESC order (most recent first).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recent(string $meetingId, string $audience = 'operator', int $limit = 80): array {
        return $this->notifRepo->listRecent($meetingId, $limit, $audience);
    }

    public function markRead(string $meetingId, int $id, string $tenantId): void {
        $this->notifRepo->markRead($meetingId, $id, $tenantId);
    }

    public function markAllRead(string $meetingId, string $audience, string $tenantId): void {
        $this->notifRepo->markAllRead($meetingId, $audience, $tenantId);
    }

    public function clear(string $meetingId, string $audience, string $tenantId): void {
        $this->notifRepo->clear($meetingId, $audience, $tenantId);
    }
}
