<?php

declare(strict_types=1);

namespace Tests\Unit\Fixtures;

/**
 * Synthese de fixtures riches pour produire un PV PDF >=10 pages via
 * MeetingReportsService::buildPdfBytes.
 *
 * Le shape des array retournes DOIT correspondre EXACTEMENT a celui des
 * helpers privates de MeetingReportsServiceTest (buildMeeting/buildMotion/
 * buildAttendance) : memes cles, memes types. Toute divergence ferait echouer
 * la generation HTML par MeetingReportsService.
 *
 * Heuristique de pagination :
 *   - 1 motion avec description ~600 chars + tableau resultat = ~1/3 page A4
 *   - 25 motions => ~9 pages
 *   - Table de presence avec 60 attendances => ~1.5 page
 *   - En-tete recap + footer => ~0.5 page
 *   - Total attendu : >=10 pages
 *
 * La description des motions inclut un panel d'accents francais
 * (e a e e o c u i), un em-dash UTF-8 (—, U+2014, octets E2 80 94) et une
 * apostrophe typographique (’) pour exercer l'encodage dompdf.
 */
final class LongPvFixtureBuilder
{
    public const MEETING_ID = '12345678-0000-0000-0000-0000000000aa';
    public const TENANT_ID = 'tenant-long-pv';
    public const MEETING_TITLE = 'AG Cloture v2.6';
    public const MEETING_DATE_ISO = '2026-06-15T10:00:00Z';
    public const MEETING_DATE_HUMAN = '15/06/2026';

    /** Description riche en accents francais + em-dash + apostrophe typographique. */
    private const RICH_DESCRIPTION_TEMPLATE =
        "Cette résolution n°%d propose l'adoption — à l'unanimité du conseil — du règlement"
        . " intérieur révisé pour l'exercice 2026. Les modifications portent notamment sur les"
        . " modalités de convocation, les règles de quorum aménagé pour les séances exceptionnelles,"
        . " ainsi que la procédure de procuration électronique. Le président rappelle qu’aucune"
        . " objection formelle n’a été déposée pendant la phase consultative. L’approbation"
        . " requiert la majorité absolue des présents et représentés. Les éventuelles abstentions"
        . " seront comptabilisées séparément conformément au règlement en vigueur.";

    /** Panel des prenoms varies pour stresser le rendu de la table de presence. */
    private const FIRST_NAMES = [
        'Pierre', 'Héloïse', 'Théo', 'François-Xavier', 'Amélie', 'Côme',
        'Aurélien', 'Bénédicte', 'Léa', 'Hervé', 'Mélanie', 'Cédric',
        'Eléonore', 'Joël', 'Naïma', 'Stéphane',
    ];

    private const LAST_NAMES = [
        'Dupont', 'Lefèvre', 'Müller', 'D’Arcy', 'Roché', 'Joffré',
        'Beauséjour', 'Charpentier', 'Hénault', 'Vásquez',
    ];

    /**
     * Meeting suffisamment "riche" pour produire un en-tete realiste.
     *
     * @return array<string,mixed>
     */
    public static function buildMeeting(): array
    {
        return [
            'id' => self::MEETING_ID,
            'tenant_id' => self::TENANT_ID,
            'title' => self::MEETING_TITLE,
            'status' => 'validated',
            'president_name' => 'Marie Dupont',
            'validated_at' => '2026-06-15T18:00:00Z',
            'created_at' => '2026-05-01T00:00:00Z',
            'archived_at' => null,
            'scheduled_at' => self::MEETING_DATE_ISO,
            'location' => 'Paris - Salle des assemblées',
        ];
    }

    /**
     * Genere $count motions avec description longue. Numero de motion incrémenté
     * pour garantir l'unicite de l'id et apporter de la variation visuelle.
     *
     * @return list<array<string,mixed>>
     */
    public static function buildMotions(int $count = 25): array
    {
        $motions = [];
        for ($i = 1; $i <= $count; $i++) {
            $idSuffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $motions[] = [
                'id' => 'motion-' . $idSuffix,
                'title' => sprintf('Résolution n°%d — adoption du règlement intérieur', $i),
                'description' => sprintf(self::RICH_DESCRIPTION_TEMPLATE, $i),
                'official_source' => 'manual',
                'official_total' => 100,
                'official_for' => 60,
                'official_against' => 30,
                'official_abstain' => 10,
                'decision' => $i % 5 === 0 ? 'rejected' : 'adopted',
                'decision_reason' => $i % 5 === 0
                    ? 'Majorité non atteinte — quorum aménagé'
                    : 'Adoptée à la majorité absolue',
                'closed_at' => '2026-06-15T11:00:00Z',
                'vote_policy_id' => null,
                'quorum_policy_id' => null,
                'secret' => $i % 7 === 0,
            ];
        }
        return $motions;
    }

    /**
     * Genere $count attendances avec full_name varie (accents + tirets + apostrophes).
     *
     * @return list<array<string,mixed>>
     */
    public static function buildAttendances(int $count = 60): array
    {
        $attendances = [];
        $firstCount = count(self::FIRST_NAMES);
        $lastCount = count(self::LAST_NAMES);
        $modes = ['present', 'remote', 'proxy'];
        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[$i % $firstCount];
            $last = self::LAST_NAMES[($i * 3) % $lastCount];
            $attendances[] = [
                'full_name' => $first . ' ' . $last,
                'mode' => $modes[$i % 3],
                'voting_power' => 1.0,
                'checked_in_at' => '2026-06-15T09:30:00Z',
                'checked_out_at' => null,
            ];
        }
        return $attendances;
    }

    /**
     * Aucune procuration — hors-scope pour les SC PDF-V26-01/02/03.
     *
     * @return list<array<string,mixed>>
     */
    public static function buildProxies(): array
    {
        return [];
    }
}
