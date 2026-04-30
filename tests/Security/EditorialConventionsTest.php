<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * v2.3 (Phase 02 — Pages éditoriales) — Editorial conventions guard.
 *
 * Tests gardiens (lint) qui scannent les fichiers livrés par les plans 02.1-02.5
 * et garantissent les invariants éditoriaux. Filet permanent contre la régression :
 *
 *  1. text-align: center sur enfant direct de .ag-editorial → fail
 *     (EDITORIAL-01 : verrou anti-pattern centrage long-form Refactoring UI)
 *  2. Préambule pédagogique français manquant dans ag-integrity-modal.js → fail
 *     (EDITORIAL-05 : 3 phrases verbatim — preuve / sceau / virgule)
 *  3. padding/margin hardcodés dans audit/trust/archives/report.css → fail
 *     (EDITORIAL-09 + Schoger S-3 : "Si tu n'utilises pas le système partout,
 *      tu n'as pas un système")
 *  4. .ag-resolution-pill (mono) à l'intérieur d'un <p> serif → fail
 *     (EDITORIAL-04 + Schoger F-4 : pill mono UNIQUEMENT en headers/lists/tables,
 *      jamais en flux paragraphe)
 *
 * Note (N-3 dédup) : pas de test forbidden-words ici — tests/Security/CopyConventionsTest.php
 * couvre déjà copropriété/syndic sur tout le codebase. Pas de duplication.
 */
final class EditorialConventionsTest extends TestCase
{
    private const EDITORIAL_PAGES = [
        'audit.htmx.html',
        'trust.htmx.html',
        'archives.htmx.html',
        'report.htmx.html',
    ];

    private function publicPath(string $file): string
    {
        return dirname(__DIR__, 2) . '/public/' . $file;
    }

    private function rootPath(string $rel): string
    {
        return dirname(__DIR__, 2) . '/' . $rel;
    }

    public function testAgEditorialChildrenNotCentered(): void
    {
        foreach (self::EDITORIAL_PAGES as $page) {
            $path = $this->publicPath($page);
            $this->assertFileExists($path, "Page éditoriale manquante : $page");

            $html = file_get_contents($path);
            $this->assertNotFalse($html);

            // Charger le HTML en mode permissif (templates HTMX, pas toujours valides W3C)
            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            // Préfixer pour libxml en UTF-8
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            $xpath = new \DOMXPath($doc);
            // Tous les enfants directs d'un .ag-editorial dont l'attribut style contient text-align: center
            $query = "//*[contains(concat(' ', normalize-space(@class), ' '), ' ag-editorial ')]"
                   . "/*[contains(translate(@style, ' ', ''), 'text-align:center')]";
            $nodes = $xpath->query($query);

            $this->assertSame(
                0,
                $nodes->length,
                "$page : enfant direct de .ag-editorial avec text-align: center détecté (" . $nodes->length . " noeuds)."
            );
        }
    }

    public function testIntegrityModalContainsPedagogicalPreamble(): void
    {
        $path = $this->rootPath('public/assets/js/components/ag-integrity-modal.js');
        $this->assertFileExists($path);

        $js = file_get_contents($path);
        $this->assertNotFalse($js);

        $this->assertStringContainsString(
            "Voici la preuve que ce PV n'a pas été modifié depuis le",
            $js,
            'Préambule pédagogique EDITORIAL-05 manquant dans ag-integrity-modal.js'
        );
        $this->assertStringContainsString(
            'sceau cryptographique reliant la précédente',
            $js,
            'Métaphore "sceau cryptographique" manquante (EDITORIAL-05)'
        );
        $this->assertStringContainsString(
            'modifier une seule virgule briserait la chaîne',
            $js,
            'Phrase de fermeture du préambule manquante (EDITORIAL-05)'
        );
    }

    public function testEditorialCssHasNoHardcodedSpacing(): void
    {
        // EDITORIAL-09 + Schoger S-3 : tous les CSS touchés par .ag-editorial doivent
        // utiliser les tokens, pas de magic numbers. Baseline (2026-04-30) :
        //   audit.css: 1 — trust.css: 25 — archives.css: 0 — report.css: 4
        // Cible post-Phase 2 : 0/0/0/0.
        $files = [
            'public/assets/css/audit.css',
            'public/assets/css/trust.css',
            'public/assets/css/archives.css',
            'public/assets/css/report.css',
        ];
        foreach ($files as $rel) {
            $path = $this->rootPath($rel);
            $this->assertFileExists($path, "$rel manquant");

            $css = file_get_contents($path);
            $this->assertNotFalse($css);

            $matches = [];
            preg_match_all(
                '/(padding|margin)(-[a-z]+)?:\s+[0-9]+(\.[0-9]+)?(px|rem|em)/',
                $css,
                $matches
            );

            $this->assertCount(
                0,
                $matches[0],
                "$rel contient des padding/margin hardcodés (EDITORIAL-09) : " . implode(', ', array_slice($matches[0], 0, 5))
            );
        }
    }

    public function testResolutionPillNotInsideParagraph(): void
    {
        // EDITORIAL-04 + Zhuo critique F + Schoger F-4 :
        // .ag-resolution-pill UNIQUEMENT en headers/lists/tables, JAMAIS dans <p> serif.
        // Le pill casse le rythme de lecture en flux serif.
        foreach (self::EDITORIAL_PAGES as $page) {
            $path = $this->publicPath($page);
            $html = file_get_contents($path);
            $this->assertNotFalse($html);

            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            $xpath = new \DOMXPath($doc);
            // Tout descendant d'un <p> qui porte la classe ag-resolution-pill
            $query = "//p//*[contains(concat(' ', normalize-space(@class), ' '), ' ag-resolution-pill ')]";
            $nodes = $xpath->query($query);

            $this->assertSame(
                0,
                $nodes->length,
                "$page : .ag-resolution-pill détecté dans un <p> serif (interdit EDITORIAL-04). Pill mono uniquement en headers/lists/tables."
            );
        }
    }

    // NOTE (N-3 dédup) : pas de check forbidden-words ici — déjà couvert par
    // tests/Security/CopyConventionsTest.php sur tout le codebase. Pas de duplication.
}
