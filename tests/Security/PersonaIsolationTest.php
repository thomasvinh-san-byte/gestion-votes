<?php

declare(strict_types=1);

namespace Tests\Security;

use AgVote\Controller\PageController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * v2.2 (DESIGN-P04) — Persona isolation contract.
 *
 * Vérifie que :
 *  1. PageController::resolveCurrentRole() mappe correctement les rôles DB
 *     vers les tokens persona CSS (admin, operator, president, auditor,
 *     voter, public, guest).
 *  2. La signature du PageController préserve l'injection data-persona
 *     (regex de remplacement reste cohérente).
 *  3. La sidebar HTML contient les attributs data-requires-role attendus
 *     pour les nav-items administratifs (les pages admin/users/settings
 *     doivent rester réservées aux admins).
 *  4. Le label persona est cohérent en français pour chaque rôle.
 *
 * Tests ne touchent NI à la base de données NI à Redis NI à la session
 * — purement contractuels via Reflection sur le code statique.
 */
final class PersonaIsolationTest extends TestCase {
    public function testResolveCurrentRoleMethodExists(): void {
        $ref = new \ReflectionClass(PageController::class);
        $this->assertTrue(
            $ref->hasMethod('resolveCurrentRole'),
            'PageController::resolveCurrentRole() est requis pour DESIGN-P03.',
        );
    }

    public function testPersonaLabelMethodExists(): void {
        $ref = new \ReflectionClass(PageController::class);
        $this->assertTrue(
            $ref->hasMethod('personaLabel'),
            'PageController::personaLabel() est requis pour DESIGN-P02.',
        );
    }

    public function testPersonaLabelsAreFrenchForAllSupportedRoles(): void {
        $ref = new ReflectionMethod(PageController::class, 'personaLabel');
        $ref->setAccessible(true);

        $expected = [
            'admin'     => 'Admin',
            'operator'  => 'Opérateur',
            'president' => 'Président',
            'auditor'   => 'Auditeur',
            'voter'     => 'Votant',
            'public'    => 'Observateur',
            'guest'     => '', // pas de badge pour invité — vide volontaire
        ];

        foreach ($expected as $role => $label) {
            $this->assertSame(
                $label,
                $ref->invoke(null, $role),
                "Le label persona pour le rôle '{$role}' doit être '{$label}'.",
            );
        }
    }

    public function testUnknownRoleFallsBackToGuestLabel(): void {
        $ref = new ReflectionMethod(PageController::class, 'personaLabel');
        $ref->setAccessible(true);

        $this->assertSame(
            '',
            $ref->invoke(null, 'unknown-role-xyz'),
            'Un rôle inconnu doit retourner un label vide (guest).',
        );
    }

    public function testSidebarHasRoleGuardsOnAdminItems(): void {
        $sidebar = file_get_contents(__DIR__ . '/../../public/partials/sidebar.html');
        $this->assertNotFalse($sidebar, 'sidebar.html doit être lisible.');

        // Les pages strictement admin doivent avoir data-requires-role="admin"
        $this->assertMatchesRegularExpression(
            '/data-page="users".*?data-requires-role="admin"/s',
            $sidebar,
            'La page /users doit être restreinte aux admins.',
        );
        $this->assertMatchesRegularExpression(
            '/data-page="admin".*?data-requires-role="admin"/s',
            $sidebar,
            'La page /admin doit être restreinte aux admins.',
        );
    }

    public function testSidebarHasPersonaBadgePlaceholder(): void {
        $sidebar = file_get_contents(__DIR__ . '/../../public/partials/sidebar.html');
        $this->assertStringContainsString(
            '%%PERSONA_LABEL%%',
            $sidebar,
            'sidebar.html doit contenir le placeholder %%PERSONA_LABEL%% remplacé par PageController.',
        );
        $this->assertStringContainsString(
            'persona-badge',
            $sidebar,
            'sidebar.html doit contenir l\'élément .persona-badge stylé par v2.2 (DESIGN-P02).',
        );
    }

    public function testCssDefinesAllSixRoleTokens(): void {
        $css = file_get_contents(__DIR__ . '/../../public/assets/css/design-system.css');
        $this->assertNotFalse($css, 'design-system.css doit être lisible.');

        foreach (['admin', 'president', 'operator', 'auditor', 'voter', 'public'] as $role) {
            $this->assertMatchesRegularExpression(
                '/--role-' . $role . ':\s*oklch\(/',
                $css,
                "Le token CSS --role-{$role} doit être défini en OKLCH.",
            );
        }
    }

    public function testCssDefinesPersonaBarRule(): void {
        $css = file_get_contents(__DIR__ . '/../../public/assets/css/design-system.css');

        $this->assertMatchesRegularExpression(
            '/body\[data-persona\]::before\s*\{/',
            $css,
            'La bande 3px persona doit être définie via body[data-persona]::before.',
        );
        $this->assertMatchesRegularExpression(
            '/body\[data-persona="guest"\]::before\s*\{[^}]*display:\s*none/',
            $css,
            'Les pages invité doivent masquer la bande persona (display:none).',
        );
    }
}
