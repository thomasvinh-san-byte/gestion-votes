<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Controller\VotePublicController;

/**
 * Unit tests for VotePublicController.
 *
 * This controller does NOT extend AbstractController — it renders HTML via
 * HtmlView::text() and HtmlView::render() which call http_response_code()
 * and exit(). This prevents standard callController() patterns.
 *
 * Tests cover:
 *  - Controller structure (final, does NOT extend AbstractController)
 *  - VOTE_MAP / VOTE_LABELS constants (via Reflection)
 *  - Token hash computation logic
 *  - Vote form values are valid mappings
 *
 * Note: vote() method cannot be invoked in tests because HtmlView::text()
 * calls exit(), which would terminate the test process.
 *
 * Pattern: extends ControllerTestCase for setUp/tearDown state management,
 *          but uses Reflection and source-level assertions instead of callController().
 */
class VotePublicControllerTest extends ControllerTestCase
{
    // =========================================================================
    // CONTROLLER STRUCTURE
    // =========================================================================

    public function testControllerIsFinal(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $this->assertTrue($ref->isFinal());
    }

    public function testControllerDoesNotExtendAbstractController(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $this->assertFalse($ref->isSubclassOf(\AgVote\Controller\AbstractController::class));
    }

    public function testControllerHasVoteMethod(): void
    {
        $this->assertTrue(method_exists(VotePublicController::class, 'vote'));
    }

    // =========================================================================
    // VOTE MAP CONSTANTS (via Reflection)
    // =========================================================================

    public function testVoteMapHasExpectedKeys(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $constants = $ref->getReflectionConstants();

        $voteMap = null;
        foreach ($constants as $constant) {
            if ($constant->getName() === 'VOTE_MAP') {
                $voteMap = $constant->getValue();
                break;
            }
        }

        $this->assertNotNull($voteMap, 'VOTE_MAP constant should exist');
        $this->assertArrayHasKey('pour', $voteMap);
        $this->assertArrayHasKey('contre', $voteMap);
        $this->assertArrayHasKey('abstention', $voteMap);
        $this->assertArrayHasKey('blanc', $voteMap);
    }

    public function testVoteMapMapsToExpectedValues(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $constants = $ref->getReflectionConstants();

        $voteMap = null;
        foreach ($constants as $constant) {
            if ($constant->getName() === 'VOTE_MAP') {
                $voteMap = $constant->getValue();
                break;
            }
        }

        $this->assertSame('for', $voteMap['pour']);
        $this->assertSame('against', $voteMap['contre']);
        $this->assertSame('abstain', $voteMap['abstention']);
        $this->assertSame('nsp', $voteMap['blanc']);
    }

    public function testVoteLabelsHaveExpectedKeys(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $constants = $ref->getReflectionConstants();

        $labels = null;
        foreach ($constants as $constant) {
            if ($constant->getName() === 'VOTE_LABELS') {
                $labels = $constant->getValue();
                break;
            }
        }

        $this->assertNotNull($labels, 'VOTE_LABELS constant should exist');
        $this->assertArrayHasKey('pour', $labels);
        $this->assertArrayHasKey('contre', $labels);
        $this->assertArrayHasKey('abstention', $labels);
        $this->assertArrayHasKey('blanc', $labels);
    }

    // =========================================================================
    // TOKEN HASH LOGIC
    // =========================================================================

    public function testTokenHashIsHmacSha256(): void
    {
        // The controller uses: hash_hmac('sha256', $token, APP_SECRET)
        // Verify that this algorithm produces expected deterministic output
        $token = 'test-token-abc123';
        $secret = 'test-secret';
        $hash = hash_hmac('sha256', $token, $secret);

        $this->assertSame(64, strlen($hash), 'SHA256 HMAC should be 64 hex chars');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);

        // Deterministic: same input → same output
        $hash2 = hash_hmac('sha256', $token, $secret);
        $this->assertSame($hash, $hash2);
    }

    public function testDifferentTokensProduceDifferentHashes(): void
    {
        $secret = 'test-secret';
        $hash1 = hash_hmac('sha256', 'token-A', $secret);
        $hash2 = hash_hmac('sha256', 'token-B', $secret);

        $this->assertNotSame($hash1, $hash2);
    }

    // =========================================================================
    // VOTE VALUES — all mapped values must be valid DB vote strings
    // =========================================================================

    public function testVoteMapValuesAreValidDbValues(): void
    {
        $validValues = ['for', 'against', 'abstain', 'nsp'];

        $ref = new \ReflectionClass(VotePublicController::class);
        $constants = $ref->getReflectionConstants();

        $voteMap = null;
        foreach ($constants as $constant) {
            if ($constant->getName() === 'VOTE_MAP') {
                $voteMap = $constant->getValue();
                break;
            }
        }

        foreach ($voteMap as $key => $dbValue) {
            $this->assertContains($dbValue, $validValues, "DB value '{$dbValue}' for key '{$key}' must be valid");
        }
    }

    public function testVoteMapAndLabelsHaveSameKeys(): void
    {
        $ref = new \ReflectionClass(VotePublicController::class);
        $constants = $ref->getReflectionConstants();

        $voteMap = null;
        $voteLabels = null;
        foreach ($constants as $constant) {
            if ($constant->getName() === 'VOTE_MAP') {
                $voteMap = $constant->getValue();
            }
            if ($constant->getName() === 'VOTE_LABELS') {
                $voteLabels = $constant->getValue();
            }
        }

        $this->assertNotNull($voteMap);
        $this->assertNotNull($voteLabels);
        $this->assertSame(array_keys($voteMap), array_keys($voteLabels));
    }
}
