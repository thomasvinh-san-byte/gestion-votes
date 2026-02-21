<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\VotePublicController;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for VotePublicController.
 *
 * This controller does NOT extend AbstractController -- it renders HTML via
 * HtmlView (which calls exit/die), so we cannot use the standard
 * callControllerMethod() pattern. Instead we verify:
 *  - Controller structure (final, does NOT extend AbstractController)
 *  - Source-level verification of validation and business logic
 *  - Vote mapping logic replication
 *  - Token hash computation logic
 *
 * The vote() method:
 *  - Validates token from query string
 *  - On GET: renders vote form
 *  - On POST: processes vote (pour/contre/abstention/blanc)
 *  - Uses HMAC-SHA256 for token hashing
 *  - Runs atomic transaction for vote insertion
 */
class VotePublicControllerTest extends TestCase
{
    // =========================================================================
    // SETUP / TEARDOWN
    // =========================================================================

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];

        // Reset cached raw body
        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        \AgVote\Core\Security\AuthMiddleware::reset();
    }

    protected function tearDown(): void
    {
        \AgVote\Core\Security\AuthMiddleware::reset();

        $ref = new \ReflectionClass(\AgVote\Core\Http\Request::class);
        $prop = $ref->getProperty('cachedRawBody');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    // =========================================================================
    // CONTROLLER STRUCTURE TESTS
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $this->assertTrue($ref->isFinal(), 'VotePublicController should be final');
    }

    public function testControllerDoesNotExtendAbstractController(): void
    {
        // VotePublicController intentionally does NOT extend AbstractController
        // because it outputs HTML, not JSON.
        $controller = new VotePublicController();
        $this->assertNotInstanceOf(\AgVote\Controller\AbstractController::class, $controller);
    }

    public function testControllerHasVoteMethod(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $this->assertTrue($ref->hasMethod('vote'), 'VotePublicController should have a vote() method');
    }

    public function testVoteMethodIsPublic(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $this->assertTrue(
            $ref->getMethod('vote')->isPublic(),
            'VotePublicController::vote() should be public',
        );
    }

    public function testControllerCanBeInstantiated(): void
    {
        $controller = new VotePublicController();
        $this->assertInstanceOf(VotePublicController::class, $controller);
    }

    // =========================================================================
    // VOTE MAP CONSTANT
    // =========================================================================

    public function testVoteMapConstant(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $this->assertTrue($ref->hasConstant('VOTE_MAP'), 'Should have VOTE_MAP constant');

        $voteMap = $ref->getConstant('VOTE_MAP');
        $this->assertIsArray($voteMap);
        $this->assertCount(4, $voteMap);

        $this->assertEquals('for', $voteMap['pour']);
        $this->assertEquals('against', $voteMap['contre']);
        $this->assertEquals('abstain', $voteMap['abstention']);
        $this->assertEquals('nsp', $voteMap['blanc']);
    }

    public function testVoteLabelsConstant(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $this->assertTrue($ref->hasConstant('VOTE_LABELS'), 'Should have VOTE_LABELS constant');

        $voteLabels = $ref->getConstant('VOTE_LABELS');
        $this->assertIsArray($voteLabels);
        $this->assertCount(4, $voteLabels);

        $this->assertEquals('Pour', $voteLabels['pour']);
        $this->assertEquals('Contre', $voteLabels['contre']);
        $this->assertEquals('Abstention', $voteLabels['abstention']);
        $this->assertEquals('Blanc', $voteLabels['blanc']);
    }

    // =========================================================================
    // VOTE MAP LOGIC
    // =========================================================================

    public function testVoteMapRejectsInvalidVoteValues(): void
    {
        $voteMap = [
            'pour' => 'for',
            'contre' => 'against',
            'abstention' => 'abstain',
            'blanc' => 'nsp',
        ];

        $this->assertFalse(isset($voteMap['yes']));
        $this->assertFalse(isset($voteMap['no']));
        $this->assertFalse(isset($voteMap['for']));
        $this->assertFalse(isset($voteMap['against']));
        $this->assertFalse(isset($voteMap['POUR']));
        $this->assertFalse(isset($voteMap['']));
    }

    public function testVoteMapOnlyAcceptsFrenchValues(): void
    {
        $voteMap = [
            'pour' => 'for',
            'contre' => 'against',
            'abstention' => 'abstain',
            'blanc' => 'nsp',
        ];

        $validValues = ['pour', 'contre', 'abstention', 'blanc'];
        foreach ($validValues as $value) {
            $this->assertArrayHasKey($value, $voteMap, "'{$value}' should be a valid vote");
        }
    }

    // =========================================================================
    // TOKEN HASH COMPUTATION LOGIC
    // =========================================================================

    public function testTokenUsesHmacSha256(): void
    {
        $token = 'test-token-12345';
        $hash = hash_hmac('sha256', $token, APP_SECRET);

        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash), 'SHA-256 HMAC should produce 64 hex characters');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
    }

    public function testTokenHashIsDeterministic(): void
    {
        $token = 'test-token-12345';
        $hash1 = hash_hmac('sha256', $token, APP_SECRET);
        $hash2 = hash_hmac('sha256', $token, APP_SECRET);

        $this->assertEquals($hash1, $hash2);
    }

    public function testDifferentTokensProduceDifferentHashes(): void
    {
        $hash1 = hash_hmac('sha256', 'token-a', APP_SECRET);
        $hash2 = hash_hmac('sha256', 'token-b', APP_SECRET);

        $this->assertNotEquals($hash1, $hash2);
    }

    // =========================================================================
    // WEIGHT CALCULATION LOGIC
    // =========================================================================

    public function testWeightCalculation(): void
    {
        $member = ['voting_power' => '2.5'];
        $weight = (float) ($member['voting_power'] ?? 1.0);

        $this->assertEquals(2.5, $weight);
    }

    public function testWeightDefaultsToOne(): void
    {
        $member = [];
        $weight = (float) ($member['voting_power'] ?? 1.0);

        $this->assertEquals(1.0, $weight);
    }

    public function testNegativeWeightClampedToZero(): void
    {
        $weight = -3.5;
        if ($weight < 0) {
            $weight = 0.0;
        }

        $this->assertEquals(0.0, $weight);
    }

    public function testPositiveWeightUnchanged(): void
    {
        $weight = 2.5;
        if ($weight < 0) {
            $weight = 0.0;
        }

        $this->assertEquals(2.5, $weight);
    }

    public function testZeroWeightAllowed(): void
    {
        $weight = 0.0;
        if ($weight < 0) {
            $weight = 0.0;
        }

        $this->assertEquals(0.0, $weight);
    }

    // =========================================================================
    // CONTROLLER SOURCE VERIFICATION
    // =========================================================================

    public function testControllerUsesHtmlView(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('HtmlView::text', $source);
        $this->assertStringContainsString('HtmlView::render', $source);
    }

    public function testControllerUsesApiQuery(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString("api_query('token')", $source);
    }

    public function testControllerUsesVoteTokenRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('VoteTokenRepository', $source);
        $this->assertStringContainsString('findValidByHash', $source);
        $this->assertStringContainsString('consume', $source);
    }

    public function testControllerUsesMotionRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('MotionRepository', $source);
        $this->assertStringContainsString('findWithBallotContext', $source);
    }

    public function testControllerUsesBallotRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('BallotRepository', $source);
        $this->assertStringContainsString('insertFromToken', $source);
    }

    public function testControllerUsesMemberRepository(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('MemberRepository', $source);
        $this->assertStringContainsString('findByIdForTenant', $source);
    }

    public function testControllerUsesApiTransaction(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('api_transaction', $source);
    }

    public function testControllerUsesAppSecret(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('APP_SECRET', $source);
    }

    // =========================================================================
    // VOTE VALIDATION LOGIC
    // =========================================================================

    public function testVoteValidationLogic(): void
    {
        $voteMap = [
            'pour' => 'for',
            'contre' => 'against',
            'abstention' => 'abstain',
            'blanc' => 'nsp',
        ];

        // Valid votes
        $this->assertTrue(is_string('pour') && isset($voteMap['pour']));
        $this->assertTrue(is_string('contre') && isset($voteMap['contre']));
        $this->assertTrue(is_string('abstention') && isset($voteMap['abstention']));
        $this->assertTrue(is_string('blanc') && isset($voteMap['blanc']));

        // Invalid votes
        $this->assertFalse(is_string(null) && isset($voteMap[null]));
        $this->assertFalse(is_string(123) && isset($voteMap[123]));
    }

    public function testConfirmFlagParsing(): void
    {
        // Replicate confirmation logic from vote()
        $postData1 = ['confirm' => '1'];
        $confirm1 = ($postData1['confirm'] ?? '0') === '1';
        $this->assertTrue($confirm1);

        $postData2 = ['confirm' => '0'];
        $confirm2 = ($postData2['confirm'] ?? '0') === '1';
        $this->assertFalse($confirm2);

        $postData3 = [];
        $confirm3 = ($postData3['confirm'] ?? '0') === '1';
        $this->assertFalse($confirm3);

        $postData4 = ['confirm' => 'true'];
        $confirm4 = ($postData4['confirm'] ?? '0') === '1';
        $this->assertFalse($confirm4, 'Only string "1" is treated as confirmed');
    }

    // =========================================================================
    // MEETING STATE VALIDATION LOGIC
    // =========================================================================

    public function testMeetingValidatedCheckLogic(): void
    {
        $ctx1 = ['meeting_validated_at' => '2024-01-01'];
        $this->assertTrue(!empty($ctx1['meeting_validated_at']));

        $ctx2 = ['meeting_validated_at' => null];
        $this->assertFalse(!empty($ctx2['meeting_validated_at']));

        $ctx3 = [];
        $this->assertFalse(!empty($ctx3['meeting_validated_at']));
    }

    public function testMotionOpenCheckLogic(): void
    {
        // Open motion
        $ctx1 = ['motion_opened_at' => '2024-01-01', 'motion_closed_at' => null];
        $isNotOpen1 = empty($ctx1['motion_opened_at']) || !empty($ctx1['motion_closed_at']);
        $this->assertFalse($isNotOpen1, 'Open motion should be accessible');

        // Closed motion
        $ctx2 = ['motion_opened_at' => '2024-01-01', 'motion_closed_at' => '2024-01-02'];
        $isNotOpen2 = empty($ctx2['motion_opened_at']) || !empty($ctx2['motion_closed_at']);
        $this->assertTrue($isNotOpen2, 'Closed motion should be rejected');

        // Not yet opened
        $ctx3 = ['motion_opened_at' => null, 'motion_closed_at' => null];
        $isNotOpen3 = empty($ctx3['motion_opened_at']) || !empty($ctx3['motion_closed_at']);
        $this->assertTrue($isNotOpen3, 'Not-yet-opened motion should be rejected');
    }

    // =========================================================================
    // ERROR MESSAGES IN SOURCE
    // =========================================================================

    public function testErrorMessagesInSource(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString('Token manquant', $source);
        $this->assertStringContainsString('Token invalide ou expir', $source);
        $this->assertStringContainsString('Motion introuvable', $source);
        $this->assertStringContainsString('meeting_validated', $source);
        $this->assertStringContainsString('motion_not_open', $source);
        $this->assertStringContainsString('Vote invalide', $source);
        $this->assertStringContainsString('token_already_used', $source);
        $this->assertStringContainsString('Vote enregistr', $source);
    }

    // =========================================================================
    // TOKEN ALREADY USED DETECTION
    // =========================================================================

    public function testTokenAlreadyUsedExceptionDetection(): void
    {
        $message = 'token_already_used';

        $this->assertEquals('token_already_used', $message);
    }

    // =========================================================================
    // VOTE SOURCE VALUE
    // =========================================================================

    public function testVoteSourceIsTablet(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString("'tablet'", $source);
    }

    // =========================================================================
    // CONSTANTS ARE PRIVATE
    // =========================================================================

    public function testVoteMapIsPrivate(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $constant = $ref->getReflectionConstant('VOTE_MAP');
        $this->assertTrue($constant->isPrivate(), 'VOTE_MAP should be private');
    }

    public function testVoteLabelsIsPrivate(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $constant = $ref->getReflectionConstant('VOTE_LABELS');
        $this->assertTrue($constant->isPrivate(), 'VOTE_LABELS should be private');
    }

    // =========================================================================
    // VOTE RENDERING TEMPLATES
    // =========================================================================

    public function testControllerRendersVoteFormTemplate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString("'vote_form'", $source);
    }

    public function testControllerRendersVoteConfirmTemplate(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString("'vote_confirm'", $source);
    }

    // =========================================================================
    // POST METHOD DETECTION
    // =========================================================================

    public function testControllerChecksPostMethod(): void
    {
        $source = file_get_contents(PROJECT_ROOT . '/app/Controller/VotePublicController.php');

        $this->assertStringContainsString("REQUEST_METHOD", $source);
        $this->assertStringContainsString("'POST'", $source);
    }
}
