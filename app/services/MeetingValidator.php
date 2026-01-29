<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

/**
 * MeetingValidator
 *
 * Objectif: centraliser la logique "prêt à valider / signer".
 *
 * Règles (cahier des charges v1.0):
 * - aucune motion ouverte
 * - toutes les motions fermées ont un résultat exploitable:
 *    - soit un comptage manuel cohérent (manual_total > 0 et somme == total)
 *    - soit au moins un bulletin e-vote (ballots)
 * - le Président (nom) est renseigné
 * - (optionnel) consolidation effectuée: official_source présent sur toutes les motions fermées
 */

final class MeetingValidator
{
    public static function canBeValidated(string $meetingId, string $tenantId): array
    {
        $meeting = db_select_one(
            "SELECT id, status, president_name FROM meetings WHERE tenant_id = ? AND id = ?",
            [$tenantId, $meetingId]
        );

        if (!$meeting) {
            return [
                'can' => false,
                'reasons' => ['Séance introuvable.'],
                'metrics' => [],
            ];
        }

        $reasons = [];
        $codes = [];

        if (trim((string)($meeting['president_name'] ?? '')) === '') {
            $reasons[] = 'Président non renseigné.';
            $codes[] = 'missing_president';
        }

        $open = (int)(db_scalar(
            "SELECT count(*) FROM motions WHERE meeting_id = ? AND opened_at IS NOT NULL AND closed_at IS NULL",
            [$meetingId]
        ) ?? 0);
        if ($open > 0) {
            $reasons[] = "$open motion(s) encore ouverte(s).";
            $codes[] = 'open_motions';
        }

        $bad = (int)(db_scalar(
            "SELECT count(*) FROM motions mo
               WHERE mo.meeting_id = ?
                 AND mo.closed_at IS NOT NULL
                 AND NOT (
                   (mo.manual_total > 0 AND (coalesce(mo.manual_for,0)+coalesce(mo.manual_against,0)+coalesce(mo.manual_abstain,0)) = mo.manual_total)
                   OR EXISTS (SELECT 1 FROM ballots b WHERE b.motion_id = mo.id)
                 )",
            [$meetingId]
        ) ?? 0);
        if ($bad > 0) {
            $reasons[] = "$bad motion(s) fermée(s) sans résultat exploitable (manuel cohérent ou e-vote).";
            $codes[] = 'bad_closed_results';
        }

        $closed = (int)(db_scalar(
            "SELECT count(*) FROM motions WHERE meeting_id = ? AND closed_at IS NOT NULL",
            [$meetingId]
        ) ?? 0);
        $consolidated = (int)(db_scalar(
            "SELECT count(*) FROM motions WHERE meeting_id = ? AND closed_at IS NOT NULL AND official_source IS NOT NULL",
            [$meetingId]
        ) ?? 0);

        // On exige la consolidation dès qu'il y a au moins une motion fermée.
        $needsConsolidation = $closed > 0;
        $consolidationDone = (!$needsConsolidation) || ($consolidated >= $closed);
        if ($needsConsolidation && !$consolidationDone) {
            $reasons[] = 'Consolidation non effectuée (résultats officiels non persistés).';
            $codes[] = 'consolidation_missing';
        }

        return [
            'can' => count($reasons) === 0,
            'codes' => $codes,
            'reasons' => $reasons,
            'metrics' => [
                'open_motions' => $open,
                'bad_closed_motions' => $bad,
                'closed_motions' => $closed,
                'consolidated_motions' => $consolidated,
                'consolidation_done' => $consolidationDone,
            ],
        ];
    }
}
