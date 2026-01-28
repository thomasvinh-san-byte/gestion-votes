<?php
declare(strict_types=1);

/**
 * NotificationsService (MVP)
 * - Stocke des notifications contextualisées par meeting
 * - Pensé pour du polling (since_id)
 * - Anti-spam best-effort: dédoublonnage sur une fenêtre courte
 */
final class NotificationsService
{
    public static function ensureSchema(): void
    {
        // Le schéma est créé dans setup_bdd_postgre.sql.
        // Ici: best-effort pour les environnements où le setup n'a pas été rejoué.
        db_execute("CREATE TABLE IF NOT EXISTS meeting_notifications (
            id bigserial PRIMARY KEY,
            tenant_id uuid NOT NULL,
            meeting_id uuid NOT NULL,
            severity text NOT NULL CHECK (severity IN ('blocking','warn','info')),
            code text NOT NULL,
            message text NOT NULL,
            audience text[] NOT NULL DEFAULT ARRAY['operator','trust'],
            data jsonb NOT NULL DEFAULT '{}'::jsonb,
            read_at timestamptz,
            created_at timestamptz NOT NULL DEFAULT now()
        )");
        db_execute("CREATE INDEX IF NOT EXISTS idx_meeting_notifications_meeting_id ON meeting_notifications(meeting_id, id DESC)");
        db_execute("CREATE INDEX IF NOT EXISTS idx_meeting_notifications_audience ON meeting_notifications USING gin(audience)");

        // Cache d'état "readiness" pour éviter le spam des notifications (détection de transitions).
        db_execute("CREATE TABLE IF NOT EXISTS meeting_validation_state (
            meeting_id uuid PRIMARY KEY,
            tenant_id uuid NOT NULL,
            ready boolean NOT NULL,
            codes jsonb NOT NULL DEFAULT '[]'::jsonb,
            updated_at timestamptz NOT NULL DEFAULT now()
        )");
        db_execute("CREATE INDEX IF NOT EXISTS idx_meeting_validation_state_tenant ON meeting_validation_state(tenant_id)");
    }

    /**
     * Émet des notifications quand l'état de readiness change (sans spam).
     * @param array<string,mixed> $validation Retour de MeetingValidator::canBeValidated
     */
    public static function emitReadinessTransitions(string $meetingId, array $validation): void
    {
        self::ensureSchema();

        $row = db_select_one("SELECT tenant_id FROM meetings WHERE id = ?", [$meetingId]);
        if (!$row) return;
        $tenantId = (string)$row['tenant_id'];

        $ready = (bool)($validation['can'] ?? false);
        $codes = $validation['codes'] ?? [];
        if (!is_array($codes)) $codes = [];
        $codes = array_values(array_unique(array_map('strval', $codes)));
        sort($codes);

        $prev = db_select_one(
            "SELECT ready, codes FROM meeting_validation_state WHERE meeting_id = ?",
            [$meetingId]
        );

        $prevReady = null;
        $prevCodes = [];
        if ($prev) {
            $prevReady = (bool)$prev['ready'];
            $prevCodes = json_decode((string)($prev['codes'] ?? '[]'), true);
            if (!is_array($prevCodes)) $prevCodes = [];
            $prevCodes = array_values(array_unique(array_map('strval', $prevCodes)));
            sort($prevCodes);
        }

        // Upsert d'état (avant d'émettre pour éviter doubles sur appels concurrents)
        db_execute(
            "INSERT INTO meeting_validation_state (meeting_id, tenant_id, ready, codes)
             VALUES (?, ?, ?, ?::jsonb)
             ON CONFLICT (meeting_id) DO UPDATE SET ready = EXCLUDED.ready, codes = EXCLUDED.codes, updated_at = now()",
            [$meetingId, $tenantId, $ready, json_encode($codes, JSON_UNESCAPED_UNICODE)]
        );

        // Premier passage: on initialise sans bruit.
        if ($prevReady === null) return;

        // Transition globale
        if ($prevReady === false && $ready === true) {
            self::emit($meetingId, 'info', 'readiness_ready', 'Séance prête à validation du Président.', ['operator','trust'], [
                'action_label' => 'Aller à la validation',
                'action_url' => '/trust.htmx.html',
            ]);
            return;
        }
        if ($prevReady === true && $ready === false) {
            // On ne spamme pas avec tout le détail ici: les "raisons" suivent via notifications par code (ci-dessous).
            self::emit($meetingId, 'warn', 'readiness_not_ready', 'Séance n’est plus prête à être validée.', ['operator','trust'], [
                'action_label' => 'Voir les blocages',
                'action_url' => '/operator_flow.htmx.html',
            ]);
        }

        // Diff des codes (blocages) : on notifie uniquement les ajouts / résolutions
        $added = array_values(array_diff($codes, $prevCodes));
        $removed = array_values(array_diff($prevCodes, $codes));

        foreach ($added as $code) {
            $tpl = self::readinessTemplate($code, true);
            self::emit($meetingId, $tpl['severity'], 'readiness_' . $code, $tpl['message'], $tpl['audience'], $tpl['data']);
        }
        foreach ($removed as $code) {
            $tpl = self::readinessTemplate($code, false);
            self::emit($meetingId, $tpl['severity'], 'readiness_' . $code . '_resolved', $tpl['message'], $tpl['audience'], $tpl['data']);
        }
    }

    /**
     * @return array{severity:string,message:string,audience:array<int,string>,data:array<string,mixed>}
     */
    private static function readinessTemplate(string $code, bool $added): array
    {
        // Par défaut: blocage opérateur + président (trust)
        $aud = ['operator','trust'];

        switch ($code) {
            case 'missing_president':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message'  => $added ? 'Blocage: Président non renseigné.' : 'Résolu: Président renseigné.',
                    'audience' => $aud,
                    'data'     => [
                        'action_label' => 'Renseigner le Président',
                        'action_url' => '/validate.htmx.html',
                    ],
                ];
            case 'open_motions':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message'  => $added ? 'Blocage: une ou plusieurs motions sont encore ouvertes.' : 'Résolu: toutes les motions sont clôturées.',
                    'audience' => $aud,
                    'data'     => [
                        'action_label' => 'Aller aux votes',
                        'action_url' => '/operator_flow.htmx.html',
                    ],
                ];
            case 'bad_closed_results':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message'  => $added ? 'Blocage: motion clôturée sans résultat exploitable (manuel ou e-vote).' : 'Résolu: résultats exploitables sur les motions clôturées.',
                    'audience' => $aud,
                    'data'     => [
                        'action_label' => 'Corriger le comptage',
                        'action_url' => '/operator_flow.htmx.html',
                    ],
                ];
            case 'consolidation_missing':
                return [
                    'severity' => $added ? 'blocking' : 'info',
                    'message'  => $added ? 'Blocage: consolidation non effectuée (résultats officiels manquants).' : 'Résolu: consolidation effectuée.',
                    'audience' => $aud,
                    'data'     => [
                        'action_label' => 'Consolider',
                        'action_url' => '/operator_flow.htmx.html',
                    ],
                ];
        }

        return [
            'severity' => $added ? 'warn' : 'info',
            'message'  => $added ? ('Blocage: ' . $code) : ('Résolu: ' . $code),
            'audience' => $aud,
            'data'     => [
                'action_label' => 'Ouvrir la séance',
                'action_url' => '/operator_flow.htmx.html',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string> $audience
     */
    public static function emit(
        string $meetingId,
        string $severity,
        string $code,
        string $message,
        array $audience = ['operator', 'trust'],
        array $data = []
    ): void {
        self::ensureSchema();

        $row = db_select_one("SELECT tenant_id FROM meetings WHERE id = ?", [$meetingId]);
        if (!$row) return;
        $tenantId = (string)$row['tenant_id'];

        // Dédoublonnage best-effort: même code + même message + même meeting dans les 10 dernières secondes
        $recent = (int)(db_scalar(
            "SELECT count(*) FROM meeting_notifications
             WHERE meeting_id = ? AND code = ? AND message = ?
               AND created_at > (now() - interval '10 seconds')",
            [$meetingId, $code, $message]
        ) ?? 0);
        if ($recent > 0) return;

        // Normaliser audience: pas de doublons / pas de vide
        $aud = array_values(array_unique(array_filter(array_map('strval', $audience), fn($x) => trim($x) !== '')));
        if (!$aud) $aud = ['operator', 'trust'];

        // Array literal robuste: {"operator","trust"}
        $audLiteral = '{' . implode(',', array_map(function(string $x): string {
            $x = str_replace('"', '""', $x);
            return '"' . $x . '"';
        }, $aud)) . '}';

        db_execute(
            "INSERT INTO meeting_notifications (tenant_id, meeting_id, severity, code, message, audience, data)
             VALUES (?, ?, ?, ?, ?, ?::text[], ?::jsonb)",
            [$tenantId, $meetingId, $severity, $code, $message, $audLiteral, json_encode($data, JSON_UNESCAPED_UNICODE)]
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function list(string $meetingId, string $audience = 'operator', int $sinceId = 0, int $limit = 30): array
    {
        self::ensureSchema();

        $limit = max(1, min(100, $limit));

        // audience: 'all' passe tout
        if ($audience === 'all') {
            return db_select_all(
                "SELECT id, severity, code, message, data, read_at, created_at
                 FROM meeting_notifications
                 WHERE meeting_id = ? AND id > ?
                 ORDER BY id ASC
                 LIMIT {$limit}",
                [$meetingId, $sinceId]
            );
        }

        return db_select_all(
            "SELECT id, severity, code, message, data, read_at, created_at
             FROM meeting_notifications
             WHERE meeting_id = ? AND id > ?
               AND (audience @> ARRAY[?]::text[])
             ORDER BY id ASC
             LIMIT {$limit}",
            [$meetingId, $sinceId, $audience]
        );
    }

    /**
     * Dernières notifications (pour initialiser l'UI) — ordre DESC (plus récent d'abord).
     * @return array<int,array<string,mixed>>
     */
    public static function recent(string $meetingId, string $audience = 'operator', int $limit = 80): array
    {
        self::ensureSchema();

        $limit = max(1, min(200, $limit));

        if ($audience === 'all') {
            return db_select_all(
                "SELECT id, severity, code, message, data, read_at, created_at
                 FROM meeting_notifications
                 WHERE meeting_id = ?
                 ORDER BY id DESC
                 LIMIT {$limit}",
                [$meetingId]
            );
        }

        return db_select_all(
            "SELECT id, severity, code, message, data, read_at, created_at
             FROM meeting_notifications
             WHERE meeting_id = ?
               AND (audience @> ARRAY[?]::text[])
             ORDER BY id DESC
             LIMIT {$limit}",
            [$meetingId, $audience]
        );
    }

    public static function markRead(string $meetingId, int $id): void
    {
        self::ensureSchema();
        if ($id <= 0) return;
        db_execute(
            "UPDATE meeting_notifications
             SET read_at = now()
             WHERE meeting_id = ? AND id = ? AND read_at IS NULL",
            [$meetingId, $id]
        );
    }

    public static function markAllRead(string $meetingId, string $audience = 'operator'): void
    {
        self::ensureSchema();
        if ($audience === 'all') {
            db_execute(
                "UPDATE meeting_notifications
                 SET read_at = now()
                 WHERE meeting_id = ? AND read_at IS NULL",
                [$meetingId]
            );
            return;
        }
        db_execute(
            "UPDATE meeting_notifications
             SET read_at = now()
             WHERE meeting_id = ? AND read_at IS NULL AND (audience @> ARRAY[?]::text[])",
            [$meetingId, $audience]
        );
    }

    public static function clear(string $meetingId, string $audience = 'operator'): void
    {
        self::ensureSchema();
        if ($audience === 'all') {
            db_execute("DELETE FROM meeting_notifications WHERE meeting_id = ?", [$meetingId]);
            return;
        }
        db_execute(
            "DELETE FROM meeting_notifications
             WHERE meeting_id = ? AND (audience @> ARRAY[?]::text[])",
            [$meetingId, $audience]
        );
    }
}
