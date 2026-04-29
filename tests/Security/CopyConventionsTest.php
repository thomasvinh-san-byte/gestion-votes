<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * v2.2 (DESIGN-X04) — Copy conventions guard.
 *
 * Filet permanent qui empêche la dérive lexicale dans le copy utilisateur.
 * Scanne le HTML et les templates PHP pour vérifier qu'aucun terme
 * banni ou mélangé n'a regressé.
 *
 * Termes interdits par CLAUDE.md (cible associations + collectivités) :
 *  - "copropriété", "syndic" (terminologie syndicale immobilière)
 *
 * Conventions v2.2 :
 *  - "secrétaire de séance" → "opérateur" (un seul mot pour le rôle)
 *  - On garde "membre" (inscrit) / "participant" (présent) / "votant"
 *    (éligible au scrutin) — la migration des cas mélangés se fait au cas
 *    par cas dans les phases suivantes ; ce test les protège uniquement
 *    contre la ré-introduction des termes EXPLICITEMENT bannis.
 */
final class CopyConventionsTest extends TestCase {
    /** @var list<string> */
    private array $userFacingFiles = [];

    protected function setUp(): void {
        $root = dirname(__DIR__, 2);
        $patterns = [
            "{$root}/public/*.html",
            "{$root}/public/*.htmx.html",
            "{$root}/public/partials/*.html",
            "{$root}/app/Templates/*.php",
        ];
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $this->userFacingFiles[] = $file;
            }
        }
    }

    public function testNoForbiddenSyndicalTerms(): void {
        $forbidden = ['copropriété', 'copropriete', 'syndic'];

        foreach ($this->userFacingFiles as $file) {
            $content = mb_strtolower((string) file_get_contents($file));
            foreach ($forbidden as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $content,
                    "Le terme banni '{$term}' apparaît dans " . basename($file)
                    . ' — CLAUDE.md ligne 15 : l\'app cible associations + collectivités, pas la copropriété.',
                );
            }
        }
    }

    public function testNoSecretaireDeSeanceInUserCopy(): void {
        // Convention v2.2 : un seul mot "opérateur" pour le rôle.
        // "secrétaire de séance" doublonne avec "opérateur" et fragmente le copy.
        foreach ($this->userFacingFiles as $file) {
            $content = (string) file_get_contents($file);
            $this->assertStringNotContainsString(
                'secrétaire de séance',
                $content,
                "Le terme 'secrétaire de séance' apparaît dans " . basename($file)
                . ' — convention v2.2 : utiliser "opérateur" partout (DESIGN-X02).',
            );
        }
    }

    public function testNoLeftoverPlaceholders(): void {
        // Pendant la phase TOKEN-replace de DESIGN-X03, on a pu laisser des
        // %% placeholders. Ils doivent tous être remplacés par PageController
        // — sauf %%CSP_NONCE%%, %%APP_VERSION%%, %%PERSONA_LABEL%% qui sont
        // les seuls valides et explicitement traités.
        $allowedPlaceholders = ['%%CSP_NONCE%%', '%%APP_VERSION%%', '%%PERSONA_LABEL%%'];

        foreach ($this->userFacingFiles as $file) {
            $content = (string) file_get_contents($file);
            preg_match_all('/%%[A-Z_]+%%/', $content, $matches);
            foreach ($matches[0] as $placeholder) {
                $this->assertContains(
                    $placeholder,
                    $allowedPlaceholders,
                    "Placeholder inconnu '{$placeholder}' dans " . basename($file)
                    . ' — ajouter au handler dans PageController ou retirer du template.',
                );
            }
        }
    }
}
