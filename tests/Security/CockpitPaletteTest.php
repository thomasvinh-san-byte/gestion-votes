<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * v2.4 Phase 1 (COCKPIT-V24-02) — Garde permanente palette danger cockpit.
 *
 * Filet anti-régression qui scanne le CSS du cockpit opérateur et garantit
 * trois invariants design system issus du milestone v2.4 cockpit-polish :
 *
 *  1. testSidebarHasZeroDangerColor : aucune occurrence de --color-danger* sur
 *     les sélecteurs sidebar (.nav-item, .nav-group, .sidebar-*). Sidebar = 0
 *     rouge décoratif (D-08 explicite).
 *
 *  2. testForbiddenSelectorsHaveNoDanger : les sélecteurs migrés vers neutre
 *     ou warning (SSE state, indicateurs factuels "contre"/"rejected", pill
 *     no-quorum, health-dot.danger) ne doivent JAMAIS retomber sur
 *     --color-danger* en cas de régression.
 *
 *  3. testCriticalPathPreservesDanger : les états critical-path approuvés
 *     (D-07 strict) DOIVENT conserver --color-danger* :
 *       - .op-checklist-sse-banner (erreur bloquante D-07.3)
 *       - .op-checklist-row--alert (quorum perdu + sse offline)
 *       - .op-focus-mode .op-focus-quorum--alert (quorum perdu D-07.1)
 *       - .hero-card--live (B1 lock, héritage v2.3 P3 B1)
 *       - <ag-health-bar> état quorum-missed pulse (composant)
 *
 * Référence : .planning/phases/01-cockpit-polish-hygiene/01.2-AUDIT.md
 * Décisions  : 01-CONTEXT.md D-07 (strict) + D-08 (migrations cibles)
 *
 * Note (dédup) : ne duplique pas EditorialConventionsTest (lexique) ni
 * CopyConventionsTest (forbidden words). Ce test cible uniquement les
 * tokens couleur CSS dans le scope cockpit.
 */
final class CockpitPaletteTest extends TestCase
{
    /**
     * Fichiers CSS scopés cockpit. `pages.css` n'est PAS inclus parce qu'il est
     * partagé (cards, vote results, etc.) et que `.hero-card--live` y est un
     * cas critical-path explicite (B1 lock).
     *
     * @var list<string>
     */
    private const COCKPIT_CSS_FILES = [
        'public/assets/css/operator.css',
        'public/assets/css/app.css',
    ];

    /**
     * Sélecteurs migrés (D-08) — doivent rester libres de --color-danger*.
     * Match sur le sélecteur en début de règle (avant `{`).
     *
     * @var list<string>
     */
    private const FORBIDDEN_SELECTORS = [
        '.op-sse-dot',
        '[data-sse-state="offline"] .op-sse-dot',
        '[data-sse-state="reconnecting"] .op-sse-dot',
        '.op-agenda-item.rejected .op-agenda-status-dot',
        '.op-resolution-progress .segment-rejected',
        '.op-vote-against .op-vote-count',
        '.op-bar-label.against',
        '.op-bar-fill.against',
        '.exec-kpi.against .exec-kpi-value',
        '.vote-results-live .against',
        '.op-tag-no-quorum',
        '.health-dot.danger',
    ];

    /**
     * Sélecteurs critical-path (D-07) qui DOIVENT contenir --color-danger*.
     * Pour chaque sélecteur, on vérifie qu'au moins une règle CSS le ciblant
     * référence un token --color-danger* dans son corps.
     *
     * Format : selector => relative file path
     *
     * @var array<string, string>
     */
    private const CRITICAL_PATH_SELECTORS = [
        '.op-checklist-sse-banner'             => 'public/assets/css/operator.css',
        '.op-checklist-row--alert'             => 'public/assets/css/operator.css',
        '.op-focus-mode .op-focus-quorum--alert' => 'public/assets/css/operator.css',
        '.hero-card--live'                     => 'public/assets/css/pages.css',
        '#viewExec[data-quorum-state="missed"]' => 'public/assets/css/components/ag-health-bar.css',
    ];

    private static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function readCss(string $relativePath): string
    {
        $full = self::projectRoot() . DIRECTORY_SEPARATOR . $relativePath;
        $content = @file_get_contents($full);
        if ($content === false) {
            throw new \RuntimeException(sprintf('CSS file not readable: %s', $relativePath));
        }

        return $content;
    }

    /**
     * AC #3 : sidebar = 0 occurrence --color-danger* (D-08 explicite).
     *
     * On vérifie sur les fichiers réels où vivent les styles sidebar
     * (`app.css`) plus le wildcard littéral `sidebar*.css` (qui n'existe pas
     * aujourd'hui mais protège contre une réintroduction).
     */
    public function testSidebarHasZeroDangerColor(): void
    {
        $appCss = self::readCss('public/assets/css/app.css');

        // Match toute règle CSS dont le sélecteur contient sidebar/.nav-item/.nav-group
        // ET dont le corps utilise --color-danger*.
        $pattern = '/(?:^|\})\s*([^\{\}]*(?:sidebar|nav-item|nav-group|nav-brand)[^\{\}]*)\{([^\{\}]*var\(--color-danger[^\{\}]*)\}/m';

        $matches = [];
        $count = preg_match_all($pattern, $appCss, $matches);

        $this->assertSame(
            0,
            $count,
            sprintf(
                'Sidebar CSS rules MUST NOT use --color-danger* (D-08). Found %d violation(s) in app.css: %s',
                $count,
                $count > 0 ? implode(' | ', $matches[1] ?? []) : ''
            )
        );

        // Defensive : si un fichier sidebar*.css apparaît un jour, il doit aussi être propre.
        $sidebarFiles = glob(self::projectRoot() . '/public/assets/css/sidebar*.css') ?: [];
        foreach ($sidebarFiles as $file) {
            $content = (string) @file_get_contents($file);
            $hits = preg_match_all('/var\(--color-danger/', $content);
            $this->assertSame(
                0,
                $hits,
                sprintf('%s must not use --color-danger* (D-08).', basename($file))
            );
        }
    }

    /**
     * AC #6 : aucun sélecteur migré ne doit retomber sur --color-danger*.
     *
     * Pour chaque sélecteur de FORBIDDEN_SELECTORS, on extrait toutes les
     * règles CSS qui le ciblent dans les fichiers cockpit, et on vérifie
     * qu'aucun corps de règle ne référence --color-danger*.
     */
    public function testForbiddenSelectorsHaveNoDanger(): void
    {
        foreach (self::COCKPIT_CSS_FILES as $relPath) {
            $content = self::readCss($relPath);

            foreach (self::FORBIDDEN_SELECTORS as $selector) {
                $rules = self::extractRulesForSelector($content, $selector);
                foreach ($rules as $body) {
                    $this->assertStringNotContainsString(
                        '--color-danger',
                        $body,
                        sprintf(
                            'Selector "%s" in %s must not reference --color-danger* (was migrated per D-08). Body: %s',
                            $selector,
                            $relPath,
                            trim(substr($body, 0, 120))
                        )
                    );
                }
            }
        }
    }

    /**
     * AC #4 + AC #5 : les sélecteurs critical-path (D-07) DOIVENT conserver
     * --color-danger*. Garantit la non-régression v2.3 P3 B1 (`.hero-card--live`)
     * et les 4 cas critical-path approuvés.
     */
    public function testCriticalPathPreservesDanger(): void
    {
        foreach (self::CRITICAL_PATH_SELECTORS as $selector => $relPath) {
            $content = self::readCss($relPath);
            $rules = self::extractRulesForSelector($content, $selector);

            $this->assertNotEmpty(
                $rules,
                sprintf(
                    'Critical-path selector "%s" not found in %s (was it removed?).',
                    $selector,
                    $relPath
                )
            );

            $found = false;
            foreach ($rules as $body) {
                if (str_contains($body, '--color-danger')) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue(
                $found,
                sprintf(
                    'Critical-path selector "%s" in %s MUST reference --color-danger* (D-07). None found across %d matching rule(s).',
                    $selector,
                    $relPath,
                    count($rules)
                )
            );
        }
    }

    /**
     * Extrait les corps `{ ... }` des règles CSS dont le sélecteur contient
     * `$selector` (match littéral exact dans la liste de sélecteurs séparés
     * par virgule). Tolère les espaces avant `{`.
     *
     * Limitation : ne supporte pas les règles imbriquées CSS Nesting (`@media`
     * blocks parsés correctement parce que `{}` matching reste équilibré au
     * niveau règle, mais nested `{}` dans valeurs `color-mix()` etc. sont
     * inoffensifs car parenthèses, pas accolades).
     *
     * @return list<string>
     */
    private static function extractRulesForSelector(string $css, string $selector): array
    {
        $bodies = [];
        $offset = 0;
        $len = strlen($css);

        while ($offset < $len) {
            $brace = strpos($css, '{', $offset);
            if ($brace === false) {
                break;
            }

            // Trouve le début du sélecteur : précédent `}` ou début de fichier,
            // en sautant les commentaires.
            $selectorStart = 0;
            for ($i = $brace - 1; $i >= $offset; $i--) {
                if ($css[$i] === '}') {
                    $selectorStart = $i + 1;
                    break;
                }
            }
            if ($selectorStart < $offset) {
                $selectorStart = $offset;
            }

            $selectorText = substr($css, $selectorStart, $brace - $selectorStart);
            // Ignore at-rules (@media, @keyframes, @supports) : leurs blocs
            // contiennent d'autres règles, pas un corps de déclarations directes.
            $trimmedSelector = ltrim($selectorText);
            $isAtRule = isset($trimmedSelector[0]) && $trimmedSelector[0] === '@';

            // Trouve l'accolade fermante équilibrée
            $depth = 1;
            $end = $brace + 1;
            while ($end < $len && $depth > 0) {
                $char = $css[$end];
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                }
                $end++;
            }
            $body = substr($css, $brace + 1, $end - $brace - 2);

            if (!$isAtRule && self::selectorListContains($selectorText, $selector)) {
                $bodies[] = $body;
            }

            $offset = $end;
        }

        return $bodies;
    }

    /**
     * Vérifie qu'un des sélecteurs (séparés par virgule) match exactement le
     * sélecteur cible. Match exact pour éviter qu'`.op-sse-dot` matche
     * `.op-sse-dot-foo`. On normalise les espaces et on compare token-list.
     */
    private static function selectorListContains(string $selectorList, string $target): bool
    {
        // Strip comments
        $clean = preg_replace('#/\*.*?\*/#s', '', $selectorList) ?? $selectorList;
        $parts = array_map('trim', explode(',', $clean));
        $normalizedTarget = self::normalizeSelector($target);
        foreach ($parts as $part) {
            if (self::normalizeSelector($part) === $normalizedTarget) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeSelector(string $sel): string
    {
        $sel = trim($sel);
        // Compresse les espaces multiples en un seul (les espaces autour de `>`,
        // `+`, `~` sont préservés via cette normalisation simple suffisante pour
        // notre liste).
        return (string) preg_replace('/\s+/', ' ', $sel);
    }
}
