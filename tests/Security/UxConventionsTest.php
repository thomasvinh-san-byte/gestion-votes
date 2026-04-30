<?php

declare(strict_types=1);

namespace Tests\Security;

use AgVote\Service\ErrorDictionary;
use PHPUnit\Framework\TestCase;

/**
 * v2.3 Phase 04 (ERR-02 + ERR-03) — UX conventions guard sur ErrorDictionary.
 *
 * Filet permanent qui scanne `app/Services/ErrorDictionary.php` et garantit
 * deux invariants éditoriaux issus du milestone v2.3 lexique-ux-critique :
 *
 *  1. testNextStepPresence (ERR-02) : chaque message des 50 codes les plus
 *     émis (ranking statique `api_fail()` cristallisé en Plan 04.4) doit
 *     contenir au moins une virgule ET un verbe d'action (impératif ou
 *     subjonctif 2e personne pluriel) issu d'une liste blanche.
 *
 *  2. testNoForbiddenPhrases (ERR-03) : aucun message du dictionnaire
 *     (top 50 ou non) ne matche une des phrases creuses interdites
 *     ("réessayer.", "contactez l'admin", "erreur survenue", etc.).
 *
 * Note (dédup) : ce test ne couvre PAS les forbidden words bannis par
 * CLAUDE.md — déjà scannés par tests/Security/CopyConventionsTest.php
 * sur tout le copy utilisateur (HTML + templates PHP). Pas de duplication.
 *
 * Maintenance : la liste TOP_50_CODES est un snapshot Plan 04.4 (ranking
 * `api_fail()` extrait par grep statique). À réviser ~1× par milestone si
 * le ranking évolue significativement (nouveaux endpoints, dépréciations).
 */
final class UxConventionsTest extends TestCase
{
    /**
     * Top 50 codes émis (snapshot Plan 04.4, voir 04.4-SUMMARY.md).
     * Ranking par occurrences `api_fail()` sur `app/` + `public/`.
     *
     * À réviser 1× par milestone si le ranking change.
     *
     * @var list<string>
     */
    private const TOP_50_CODES = [
        'meeting_not_found',          // 1 — 49 émissions
        'missing_meeting_id',         // 2 — 35
        'method_not_allowed',         // 3 — 20
        'invalid_meeting_id',         // 4 — 11
        'business_error',             // 5 — 11
        'invalid_motion_id',          // 6 — 9
        'invalid_template_id',        // 7 — 8
        'template_not_found',         // 8 — 7
        'motion_not_found',           // 9 — 7
        'upload_error',               // 10 — 6
        'missing_motion_id',          // 11 — 6
        'missing_id',                 // 12 — 6
        'member_not_found',           // 13 — 6
        'meeting_validated',          // 14 — 6
        'invalid_token',              // 15 — 5
        'group_not_found',            // 16 — 5
        'file_too_large',             // 17 — 5
        'not_found',                  // 18 — 4
        'missing_justification',      // 19 — 4
        'invalid_member_id',          // 20 — 4
        'smtp_not_configured',        // 21 — 3
        'motion_closed',              // 22 — 3
        'meeting_not_validated',      // 23 — 3
        'invalid_request',            // 24 — 3
        'invalid_name',               // 25 — 3
        'internal_error',             // 26 — 3
        'file_read_error',            // 27 — 3
        'delete_failed',              // 28 — 3
        'archived_immutable',         // 29 — 3
        'workflow_issues',            // 30 — 2
        'update_failed',              // 31 — 2
        'unknown_variables',          // 32 — 2
        'template_name_exists',       // 33 — 2
        'proxy_not_found',            // 34 — 2
        'no_members',                 // 35 — 2
        'no_live_meeting',            // 36 — 2
        'name_already_exists',        // 37 — 2
        'motion_not_open',            // 38 — 2
        'missing_proxy_id',           // 39 — 2
        'missing_member_id',          // 40 — 2
        'missing_body_html',          // 41 — 2
        'meeting_not_live',           // 42 — 2
        'meeting_archived',           // 43 — 2
        'invalid_uuid',               // 44 — 2
        'invalid_source_id',          // 45 — 2
        'invalid_mime_type',          // 46 — 2
        'invalid_member_ids',         // 47 — 2
        'invalid_id',                 // 48 — 2
        'invalid_file_type',          // 49 — 2
        'invalid_file',               // 50 — 2
    ];

    /**
     * Verbes d'action acceptables comme "next-step" actionnable (ERR-02).
     * Liste blanche en français, impératif ou subjonctif 2e personne pluriel.
     * Détection insensible à la casse.
     *
     * @var list<string>
     */
    private const ACTION_VERBS = [
        // Impératif (2e personne du pluriel — forme prescriptive)
        'demandez', 'contactez', 'vérifiez', 'verifiez', 'essayez',
        'recommencez', 'revenez', 'reconnectez', 'attendez', 'consultez',
        'accédez', 'accedez', 'sélectionnez', 'selectionnez', 'cliquez',
        'corrigez', 'complétez', 'completez', 'fermez', 'rechargez',
        'rafraîchissez', 'rafraichissez', 'patientez', 'choisissez',
        'modifiez', 'mettez', 'saisissez', 'utilisez', 'téléchargez',
        'telechargez', 'créez', 'creez', 'soumettez', 'actualisez',
        'relancez', 'renvoyez', 'réessayez', 'reessayez', 'résoudre',
        'résolvez', 'resolvez', 'retournez', 'sauvegardez', 'compressez',
        'découpez', 'decoupez', 'supprimez', 'rédigez', 'redigez',
        'réinitialisez', 'reinitialisez',
        // Subjonctif / pivot
        'que ', // ex: "demandez à l'administrateur que..."
    ];

    /**
     * Phrases creuses interdites (ERR-03). Aucun message du dictionnaire ne
     * doit matcher une de ces regex — même si la forme « virgule + verbe »
     * est respectée par ailleurs.
     *
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        '/réessayer\.?$/i',
        '/contactez (le|l\')admin/i',
        '/erreur survenue/i',
        '/une erreur est survenue/i',
        '/veuillez réessayer plus tard/i',
    ];

    /**
     * Garde-fou F2 : la liste TOP_50_CODES doit contenir au moins 50 codes.
     * Sans cette assertion, testNextStepPresence pourrait passer vert avec
     * une couverture <50 — ERR-02 partiellement satisfait sans détection.
     */
    public function testTop50CodesContainsAtLeast50Entries(): void
    {
        $this->assertGreaterThanOrEqual(
            50,
            count(self::TOP_50_CODES),
            'TOP_50_CODES doit contenir au moins 50 codes (ERR-02 couverture). '
            . 'Si le ranking a changé, mettre à jour la liste depuis 04.4-SUMMARY.md ranking.'
        );
    }

    /**
     * ERR-02 : chaque message du top 50 doit contenir une virgule ET un
     * verbe d'action de la liste blanche. Échec explicite citant chaque
     * code défaillant pour faciliter le diagnostic.
     */
    public function testNextStepPresence(): void
    {
        $missing = [];

        foreach (self::TOP_50_CODES as $code) {
            if (!ErrorDictionary::hasMessage($code)) {
                $missing[] = "{$code} (code absent du dictionnaire ErrorDictionary::MESSAGES)";
                continue;
            }

            $msg = ErrorDictionary::getMessage($code);

            // Règle 1 : doit contenir au moins une virgule (séparateur next-step)
            if (strpos($msg, ',') === false) {
                $missing[] = "{$code} (pas de virgule) : « {$msg} »";
                continue;
            }

            // Règle 2 : doit contenir au moins un verbe d'action de la liste blanche
            $msgLower = mb_strtolower($msg, 'UTF-8');
            $hasVerb = false;
            foreach (self::ACTION_VERBS as $verb) {
                if (strpos($msgLower, mb_strtolower($verb, 'UTF-8')) !== false) {
                    $hasVerb = true;
                    break;
                }
            }
            if (!$hasVerb) {
                $missing[] = "{$code} (pas de verbe d'action) : « {$msg} »";
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Codes du top 50 sans next-step actionnable (ERR-02 fail) :\n  - "
            . implode("\n  - ", $missing)
            . "\n\nChaque message du top 50 doit contenir : virgule + verbe d'action "
            . '(demandez/contactez/vérifiez/essayez/...). Voir 04.4-SUMMARY.md pour '
            . 'le ranking complet et tests/Security/UxConventionsTest::ACTION_VERBS '
            . 'pour la liste blanche.'
        );
    }

    /**
     * ERR-03 : aucun message du dictionnaire (codes top 50 ou non) ne doit
     * contenir une phrase creuse interdite. Scan exhaustif sur toutes les
     * paires code => message exposées par ErrorDictionary::getCodes().
     */
    public function testNoForbiddenPhrases(): void
    {
        $hits = [];

        foreach (ErrorDictionary::getCodes() as $code) {
            $msg = ErrorDictionary::getMessage($code);

            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $msg) === 1) {
                    $hits[] = "{$code} matche {$pattern} : « {$msg} »";
                }
            }
        }

        $this->assertSame(
            [],
            $hits,
            "Messages contenant une phrase creuse interdite (ERR-03 fail) :\n  - "
            . implode("\n  - ", $hits)
            . "\n\nLes phrases creuses (réessayer., contactez l'admin, erreur survenue, "
            . 'etc.) doivent être remplacées par un next-step concret (action + cible).'
        );
    }
}
