<?php

declare(strict_types=1);

namespace Tests\Integration;

use AgVote\Core\Security\CsrfMiddleware;
use AgVote\Core\Security\RateLimiter;
use AgVote\Core\Validation\InputValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests de validation du workflow et des composants de sécurité.
 *
 * Vérifie que les composants critiques fonctionnent correctement
 * pour le chemin critique de l'administrateur.
 */
class WorkflowValidationTest extends TestCase {
    protected function setUp(): void {
        // Nettoyer le rate limiter avant chaque test
        RateLimiter::configure(['storage_dir' => '/tmp/ag-vote-test-ratelimit']);
        RateLimiter::reset('test-context', 'test-user');
    }

    // =========================================================================
    // VALIDATION DES ENTRÉES
    // =========================================================================

    public function testUserCreationValidation(): void {
        $validator = InputValidator::schema()
            ->email('email')->required()
            ->string('name')->required()->minLength(2)->maxLength(100)
            ->enum('role', ['admin', 'operator', 'auditor', 'viewer'])->required()
            ->string('password')->required()->minLength(8);

        // Données valides
        $validInput = [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'role' => 'operator',
            'password' => 'SecurePass123!',
        ];

        $result = $validator->validate($validInput);
        $this->assertTrue($result->isValid());
        $this->assertEquals('newuser@example.com', $result->get('email'));
    }

    public function testUserCreationValidationFailsOnWeakPassword(): void {
        $validator = InputValidator::schema()
            ->string('password')->required()->minLength(8);

        $result = $validator->validate(['password' => 'short']);
        $this->assertFalse($result->isValid());
    }

    public function testUserCreationValidationFailsOnInvalidEmail(): void {
        $validator = InputValidator::schema()
            ->email('email')->required();

        $result = $validator->validate(['email' => 'not-an-email']);
        $this->assertFalse($result->isValid());
    }

    public function testUserCreationValidationFailsOnInvalidRole(): void {
        $validator = InputValidator::schema()
            ->enum('role', ['admin', 'operator', 'auditor', 'viewer'])->required();

        $result = $validator->validate(['role' => 'superadmin']);
        $this->assertFalse($result->isValid());
    }

    // =========================================================================
    // VALIDATION DE RÉUNION
    // =========================================================================

    public function testMeetingCreationValidation(): void {
        $validator = InputValidator::schema()
            ->string('title')->required()->minLength(3)->maxLength(255)
            ->string('description')->optional()->maxLength(5000)
            ->string('location')->optional()->maxLength(255)
            ->datetime('scheduled_at')->optional();

        $validInput = [
            'title' => 'Assemblée Générale 2026',
            'description' => 'AG annuelle des copropriétaires',
            'location' => 'Salle des fêtes',
            'scheduled_at' => '2026-03-15 14:00:00',
        ];

        $result = $validator->validate($validInput);
        $this->assertTrue($result->isValid());
    }

    public function testMeetingValidationFailsOnEmptyTitle(): void {
        $validator = InputValidator::schema()
            ->string('title')->required()->minLength(3);

        $result = $validator->validate(['title' => 'AB']);
        $this->assertFalse($result->isValid());
    }

    // =========================================================================
    // VALIDATION DE MOTION
    // =========================================================================

    public function testMotionCreationValidation(): void {
        $validator = InputValidator::schema()
            ->uuid('meeting_id')->required()
            ->string('title')->required()->minLength(3)->maxLength(500)
            ->string('description')->optional()
            ->boolean('secret')->optional()->default(false)
            ->integer('position')->optional()->min(1);

        $validInput = [
            'meeting_id' => '550e8400-e29b-41d4-a716-446655440000',
            'title' => 'Approbation des comptes 2025',
            'description' => 'Vote pour approuver les comptes de l\'exercice 2025',
            'secret' => false,
            'position' => 1,
        ];

        $result = $validator->validate($validInput);
        $this->assertTrue($result->isValid());
    }

    public function testMotionValidationRequiresValidUUID(): void {
        $validator = InputValidator::schema()
            ->uuid('meeting_id')->required();

        $result = $validator->validate(['meeting_id' => 'not-a-uuid']);
        $this->assertFalse($result->isValid());
    }

    // =========================================================================
    // VALIDATION DE MEMBRE
    // =========================================================================

    public function testMemberCreationValidation(): void {
        $validator = InputValidator::schema()
            ->string('full_name')->required()->minLength(2)->maxLength(200)
            ->email('email')->optional()
            ->number('voting_power')->optional()->min(0)->default(1.0)
            ->boolean('is_active')->optional()->default(true);

        $validInput = [
            'full_name' => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
            'voting_power' => 1.5,
            'is_active' => true,
        ];

        $result = $validator->validate($validInput);
        $this->assertTrue($result->isValid());
    }

    public function testMemberVotingPowerMustBePositive(): void {
        $validator = InputValidator::schema()
            ->number('voting_power')->optional()->min(0);

        $result = $validator->validate(['voting_power' => -1]);
        $this->assertFalse($result->isValid());
    }

    // =========================================================================
    // VALIDATION DE VOTE
    // =========================================================================

    public function testBallotCastValidation(): void {
        $validator = InputValidator::schema()
            ->uuid('motion_id')->required()
            ->uuid('member_id')->required()
            ->enum('value', ['for', 'against', 'abstain', 'nsp'])->required()
            ->boolean('is_proxy_vote')->optional()->default(false);

        $validInput = [
            'motion_id' => '550e8400-e29b-41d4-a716-446655440001',
            'member_id' => '550e8400-e29b-41d4-a716-446655440002',
            'value' => 'for',
            'is_proxy_vote' => false,
        ];

        $result = $validator->validate($validInput);
        $this->assertTrue($result->isValid());
    }

    public function testBallotValueMustBeValid(): void {
        $validator = InputValidator::schema()
            ->enum('value', ['for', 'against', 'abstain', 'nsp'])->required();

        $result = $validator->validate(['value' => 'maybe']);
        $this->assertFalse($result->isValid());
    }

    // =========================================================================
    // VALIDATION DES POLITIQUES
    // =========================================================================

    public function testQuorumPolicyValidation(): void {
        $validator = InputValidator::schema()
            ->string('name')->required()->minLength(3)->maxLength(100)
            ->enum('mode', ['single', 'evolving', 'double'])->required()
            ->enum('denominator', ['eligible_members', 'eligible_weight'])->required()
            ->number('threshold')->required()->min(0)->max(1)
            ->boolean('include_proxies')->optional()->default(true)
            ->boolean('count_remote')->optional()->default(true);

        $validInput = [
            'name' => 'Quorum standard',
            'mode' => 'single',
            'denominator' => 'eligible_members',
            'threshold' => 0.5,
            'include_proxies' => true,
            'count_remote' => true,
        ];

        $result = $validator->validate($validInput);
        $this->assertTrue($result->isValid());
    }

    public function testVotePolicyValidation(): void {
        $validator = InputValidator::schema()
            ->string('name')->required()->minLength(3)->maxLength(100)
            ->enum('base', ['expressed', 'total_eligible'])->required()
            ->number('threshold')->required()->min(0)->max(1)
            ->boolean('abstention_as_against')->optional()->default(false);

        $validInput = [
            'name' => 'Majorité simple',
            'base' => 'expressed',
            'threshold' => 0.5,
            'abstention_as_against' => false,
        ];

        $result = $validator->validate($validInput);
        $this->assertTrue($result->isValid());
    }

    // =========================================================================
    // PROTECTION XSS
    // =========================================================================

    public function testXssSanitization(): void {
        $validator = InputValidator::schema()
            ->string('title')->required();

        $input = ['title' => '<script>alert("XSS")</script>'];
        $result = $validator->validate($input);

        $this->assertTrue($result->isValid());
        $sanitized = $result->get('title');

        // Le script doit être échappé
        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('&lt;script&gt;', $sanitized);
    }

    public function testRawOptionBypassesSanitization(): void {
        $validator = InputValidator::schema()
            ->string('html_content')->required()->raw();

        $input = ['html_content' => '<strong>Bold text</strong>'];
        $result = $validator->validate($input);

        $this->assertTrue($result->isValid());
        // Avec raw(), le HTML n'est pas échappé
        $this->assertStringContainsString('<strong>', $result->get('html_content'));
    }

    // =========================================================================
    // RATE LIMITING
    // =========================================================================

    public function testRateLimiterAllowsNormalUsage(): void {
        $allowed = RateLimiter::check('test-context', 'test-user', 10, 60, false);
        $this->assertTrue($allowed);
    }

    public function testRateLimiterBlocksExcessiveRequests(): void {
        // Faire 10 requêtes (limite)
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::check('rate-test', 'user-' . $i, 10, 60, false);
        }

        // La 11ème requête du même utilisateur devrait être bloquée
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::check('rate-test', 'single-user', 10, 60, false);
        }
        $isLimited = RateLimiter::isLimited('rate-test', 'single-user', 10, 60);
        $this->assertTrue($isLimited);
    }

    // =========================================================================
    // CSRF PROTECTION
    // =========================================================================

    public function testCsrfTokenGeneration(): void {
        $token1 = CsrfMiddleware::getToken();
        $this->assertNotEmpty($token1);
        $this->assertEquals(64, strlen($token1)); // 32 bytes en hex = 64 caractères
    }

    public function testCsrfTokenConsistency(): void {
        $token1 = CsrfMiddleware::getToken();
        $token2 = CsrfMiddleware::getToken();
        $this->assertEquals($token1, $token2);
    }

    public function testCsrfFieldGeneration(): void {
        $field = CsrfMiddleware::field();
        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
    }

    // =========================================================================
    // RÉSUMÉ DU CHEMIN CRITIQUE
    // =========================================================================

    public function testCriticalPathValidationsSummary(): void {
        // Ce test résume toutes les validations critiques qui doivent passer

        // 1. Création utilisateur
        $userValidator = InputValidator::schema()
            ->email('email')->required()
            ->string('name')->required()->minLength(2)
            ->enum('role', ['admin', 'operator', 'auditor', 'viewer'])->required()
            ->string('password')->required()->minLength(8);

        $userResult = $userValidator->validate([
            'email' => 'admin@test.com',
            'name' => 'Test Admin',
            'role' => 'admin',
            'password' => 'StrongPass123!',
        ]);
        $this->assertTrue($userResult->isValid(), 'User creation validation should pass');

        // 2. Création réunion
        $meetingValidator = InputValidator::schema()
            ->string('title')->required()->minLength(3);

        $meetingResult = $meetingValidator->validate(['title' => 'Test Meeting']);
        $this->assertTrue($meetingResult->isValid(), 'Meeting creation validation should pass');

        // 3. Création motion
        $motionValidator = InputValidator::schema()
            ->uuid('meeting_id')->required()
            ->string('title')->required()->minLength(3);

        $motionResult = $motionValidator->validate([
            'meeting_id' => '550e8400-e29b-41d4-a716-446655440000',
            'title' => 'Test Motion',
        ]);
        $this->assertTrue($motionResult->isValid(), 'Motion creation validation should pass');

        // 4. Vote
        $voteValidator = InputValidator::schema()
            ->uuid('motion_id')->required()
            ->uuid('member_id')->required()
            ->enum('value', ['for', 'against', 'abstain', 'nsp'])->required();

        $voteResult = $voteValidator->validate([
            'motion_id' => '550e8400-e29b-41d4-a716-446655440001',
            'member_id' => '550e8400-e29b-41d4-a716-446655440002',
            'value' => 'for',
        ]);
        $this->assertTrue($voteResult->isValid(), 'Vote casting validation should pass');

        // 5. Protection CSRF active
        $token = CsrfMiddleware::getToken();
        $this->assertNotEmpty($token, 'CSRF token should be generated');

        // 6. Rate limiting fonctionnel
        $allowed = RateLimiter::check('critical-path-test', 'test-user', 100, 60, false);
        $this->assertTrue($allowed, 'Rate limiter should allow normal requests');
    }
}
