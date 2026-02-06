<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Security\PermissionChecker;
use AgVote\Service\MeetingWorkflowService;

/**
 * Tests d'intégration pour le chemin critique de l'administrateur.
 *
 * Ces tests valident le workflow complet de l'admin:
 * 1. Authentification et autorisation
 * 2. Gestion des utilisateurs
 * 3. Configuration des politiques
 * 4. Cycle de vie complet d'une réunion (draft -> archived)
 * 5. Génération de rapports
 */
class AdminCriticalPathTest extends TestCase
{
    private PermissionChecker $checker;
    private array $adminUser;
    private array $operatorUser;
    private array $presidentUser;
    private array $voterUser;

    protected function setUp(): void
    {
        $this->checker = new PermissionChecker();

        $this->adminUser = [
            'id' => 'admin-uuid-001',
            'role' => 'admin',
            'tenant_id' => 'tenant-001',
            'email' => 'admin@test.com',
            'name' => 'Admin Test',
        ];

        $this->operatorUser = [
            'id' => 'operator-uuid-001',
            'role' => 'operator',
            'tenant_id' => 'tenant-001',
            'email' => 'operator@test.com',
            'name' => 'Operator Test',
        ];

        $this->presidentUser = [
            'id' => 'president-uuid-001',
            'role' => 'president',
            'tenant_id' => 'tenant-001',
            'email' => 'president@test.com',
            'name' => 'President Test',
        ];

        $this->voterUser = [
            'id' => 'voter-uuid-001',
            'role' => 'voter',
            'tenant_id' => 'tenant-001',
            'email' => 'voter@test.com',
            'name' => 'Voter Test',
        ];

        AuthMiddleware::reset();
    }

    // =========================================================================
    // PHASE 1: AUTHENTIFICATION & AUTORISATION
    // =========================================================================

    public function testAdminCanAccessAllAdminFunctions(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'admin:users'));
        $this->assertTrue($this->checker->check($this->adminUser, 'admin:policies'));
        $this->assertTrue($this->checker->check($this->adminUser, 'admin:system'));
        $this->assertTrue($this->checker->check($this->adminUser, 'admin:roles'));
    }

    public function testAdminCanManageUsers(): void
    {
        // Admin peut créer, lire, modifier, supprimer des utilisateurs
        $this->assertTrue($this->checker->check($this->adminUser, 'admin:users'));
    }

    public function testOperatorCannotAccessAdminFunctions(): void
    {
        $this->assertFalse($this->checker->check($this->operatorUser, 'admin:users'));
        $this->assertFalse($this->checker->check($this->operatorUser, 'admin:policies'));
        $this->assertFalse($this->checker->check($this->operatorUser, 'admin:system'));
    }

    // =========================================================================
    // PHASE 2: CRÉATION DE RÉUNION
    // =========================================================================

    public function testAdminCanCreateMeeting(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'meeting:create'));
    }

    public function testOperatorCanCreateMeeting(): void
    {
        $this->assertTrue($this->checker->check($this->operatorUser, 'meeting:create'));
    }

    public function testVoterCannotCreateMeeting(): void
    {
        $this->assertFalse($this->checker->check($this->voterUser, 'meeting:create'));
    }

    // =========================================================================
    // PHASE 3: PRÉPARATION DE RÉUNION
    // =========================================================================

    public function testAdminCanManageMembers(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'member:create'));
        $this->assertTrue($this->checker->check($this->adminUser, 'member:update'));
        $this->assertTrue($this->checker->check($this->adminUser, 'member:delete'));
        $this->assertTrue($this->checker->check($this->adminUser, 'member:import'));
    }

    public function testAdminCanManageMotions(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'motion:create'));
        $this->assertTrue($this->checker->check($this->adminUser, 'motion:update'));
        $this->assertTrue($this->checker->check($this->adminUser, 'motion:delete'));
    }

    public function testAdminCanManageAttendance(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'attendance:create'));
        $this->assertTrue($this->checker->check($this->adminUser, 'attendance:update'));
    }

    public function testAdminCanManageProxies(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'proxy:create'));
        $this->assertTrue($this->checker->check($this->adminUser, 'proxy:delete'));
    }

    // =========================================================================
    // PHASE 4: TRANSITIONS D'ÉTAT DE RÉUNION
    // =========================================================================

    public function testDraftToScheduledTransition(): void
    {
        // Operator peut passer de draft à scheduled
        $this->assertTrue($this->checker->canTransition($this->operatorUser, 'draft', 'scheduled'));
        // Admin aussi
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'draft', 'scheduled'));
    }

    public function testScheduledToFrozenTransition(): void
    {
        // President peut passer de scheduled à frozen
        $this->assertTrue($this->checker->canTransition($this->presidentUser, 'scheduled', 'frozen'));
        // Admin aussi
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'scheduled', 'frozen'));
        // Operator ne peut pas
        $this->assertFalse($this->checker->canTransition($this->operatorUser, 'scheduled', 'frozen'));
    }

    public function testFrozenToLiveTransition(): void
    {
        // President peut ouvrir la séance
        $this->assertTrue($this->checker->canTransition($this->presidentUser, 'frozen', 'live'));
        // Admin aussi
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'frozen', 'live'));
    }

    public function testLiveToClosedTransition(): void
    {
        // President peut clôturer la séance
        $this->assertTrue($this->checker->canTransition($this->presidentUser, 'live', 'closed'));
        // Admin aussi
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'live', 'closed'));
    }

    public function testClosedToValidatedTransition(): void
    {
        // President peut valider la séance
        $this->assertTrue($this->checker->canTransition($this->presidentUser, 'closed', 'validated'));
        // Admin aussi
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'closed', 'validated'));
    }

    public function testValidatedToArchivedTransition(): void
    {
        // Seul admin peut archiver
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'validated', 'archived'));
        // President ne peut pas archiver
        $this->assertFalse($this->checker->canTransition($this->presidentUser, 'validated', 'archived'));
    }

    public function testCompleteForwardLifecycle(): void
    {
        // Test du cycle complet: draft -> scheduled -> frozen -> live -> closed -> validated -> archived
        $states = ['draft', 'scheduled', 'frozen', 'live', 'closed', 'validated', 'archived'];

        for ($i = 0; $i < count($states) - 1; $i++) {
            $from = $states[$i];
            $to = $states[$i + 1];

            // Admin peut faire toutes les transitions
            $this->assertTrue(
                $this->checker->canTransition($this->adminUser, $from, $to),
                "Admin should be able to transition from $from to $to"
            );
        }
    }

    public function testCannotSkipStates(): void
    {
        // On ne peut pas sauter d'états
        $this->assertFalse($this->checker->canTransition($this->adminUser, 'draft', 'live'));
        $this->assertFalse($this->checker->canTransition($this->adminUser, 'draft', 'closed'));
        $this->assertFalse($this->checker->canTransition($this->adminUser, 'scheduled', 'live'));
        $this->assertFalse($this->checker->canTransition($this->adminUser, 'frozen', 'validated'));
    }

    // =========================================================================
    // PHASE 5: VOTES
    // =========================================================================

    public function testAdminCanOpenMotionVote(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'motion:open'));
    }

    public function testAdminCanCloseMotionVote(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'motion:close'));
    }

    public function testVoterCanCastVote(): void
    {
        $this->assertTrue($this->checker->check($this->voterUser, 'vote:cast'));
    }

    public function testAdminCanManualVote(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'vote:manual'));
    }

    // =========================================================================
    // PHASE 6: RAPPORTS ET EXPORTS
    // =========================================================================

    public function testAdminCanGenerateReports(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'report:generate'));
        $this->assertTrue($this->checker->check($this->adminUser, 'report:read'));
        $this->assertTrue($this->checker->check($this->adminUser, 'report:export'));
    }

    public function testAdminCanExportAuditLogs(): void
    {
        $this->assertTrue($this->checker->check($this->adminUser, 'audit:read'));
        $this->assertTrue($this->checker->check($this->adminUser, 'audit:export'));
    }

    // =========================================================================
    // PHASE 7: SÉCURITÉ POST-VALIDATION
    // =========================================================================

    public function testCannotModifyAfterArchived(): void
    {
        // Archived est un état terminal - aucune transition possible
        $transitions = $this->checker->availableTransitions($this->adminUser, 'archived');
        $this->assertEmpty($transitions, "No transitions should be available from archived state");
    }

    // =========================================================================
    // TESTS DE RÔLES DE SÉANCE
    // =========================================================================

    public function testMeetingRolesPermissions(): void
    {
        // President peut lire les audits
        $this->assertTrue($this->checker->check($this->presidentUser, 'audit:read'));

        // President peut générer les rapports
        $this->assertTrue($this->checker->check($this->presidentUser, 'report:generate'));

        // Voter peut seulement voter
        $this->assertTrue($this->checker->check($this->voterUser, 'vote:cast'));
        $this->assertFalse($this->checker->check($this->voterUser, 'motion:create'));
    }

    // =========================================================================
    // TESTS DE HIÉRARCHIE DE RÔLES
    // =========================================================================

    public function testRoleHierarchy(): void
    {
        // Admin > Operator > Auditor > Viewer
        $this->assertTrue($this->checker->hasRole($this->adminUser, ['admin']));
        $this->assertTrue($this->checker->hasRole($this->adminUser, ['operator']));
        $this->assertTrue($this->checker->hasRole($this->adminUser, ['auditor']));
        $this->assertTrue($this->checker->hasRole($this->adminUser, ['viewer']));
    }

    // =========================================================================
    // VALIDATION DU WORKFLOW COMPLET
    // =========================================================================

    public function testAdminWorkflowSummary(): void
    {
        // Ce test résume le chemin critique complet de l'admin

        // 1. Admin peut gérer les utilisateurs
        $this->assertTrue($this->checker->check($this->adminUser, 'admin:users'));

        // 2. Admin peut gérer les politiques
        $this->assertTrue($this->checker->check($this->adminUser, 'admin:policies'));

        // 3. Admin peut créer une réunion
        $this->assertTrue($this->checker->check($this->adminUser, 'meeting:create'));

        // 4. Admin peut gérer les membres
        $this->assertTrue($this->checker->check($this->adminUser, 'member:create'));

        // 5. Admin peut créer des résolutions
        $this->assertTrue($this->checker->check($this->adminUser, 'motion:create'));

        // 6. Admin peut gérer les présences
        $this->assertTrue($this->checker->check($this->adminUser, 'attendance:create'));

        // 7. Admin peut faire évoluer l'état de la réunion
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'draft', 'scheduled'));
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'scheduled', 'frozen'));
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'frozen', 'live'));
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'live', 'closed'));
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'closed', 'validated'));
        $this->assertTrue($this->checker->canTransition($this->adminUser, 'validated', 'archived'));

        // 8. Admin peut gérer les votes
        $this->assertTrue($this->checker->check($this->adminUser, 'vote:cast'));
        $this->assertTrue($this->checker->check($this->adminUser, 'vote:manual'));

        // 9. Admin peut générer les rapports
        $this->assertTrue($this->checker->check($this->adminUser, 'report:generate'));

        // 10. Admin peut exporter les audits
        $this->assertTrue($this->checker->check($this->adminUser, 'audit:export'));
    }
}
