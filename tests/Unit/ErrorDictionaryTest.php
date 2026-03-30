<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\ErrorDictionary;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ErrorDictionary static methods.
 *
 * No mocks needed — all methods are static, pure lookups.
 */
class ErrorDictionaryTest extends TestCase
{
    // =========================================================================
    // getMessage() — known codes
    // =========================================================================

    public function testGetMessageReturnsKnownMessageForUnauthorized(): void
    {
        $this->assertSame(
            'Vous devez être connecté pour accéder à cette ressource.',
            ErrorDictionary::getMessage('unauthorized'),
        );
    }

    public function testGetMessageReturnsKnownMessageForMeetingNotFound(): void
    {
        $this->assertSame(
            'Séance introuvable.',
            ErrorDictionary::getMessage('meeting_not_found'),
        );
    }

    public function testGetMessageReturnsKnownMessageForInvalidVoteChoice(): void
    {
        $this->assertSame(
            'Choix de vote invalide.',
            ErrorDictionary::getMessage('invalid_vote_choice'),
        );
    }

    public function testGetMessageReturnsKnownMessageForServerError(): void
    {
        $this->assertSame(
            'Erreur serveur. Consultez les logs pour plus de détails.',
            ErrorDictionary::getMessage('server_error'),
        );
    }

    // =========================================================================
    // getMessage() — unknown codes (fallback)
    // =========================================================================

    public function testGetMessageReturnsFormattedFallbackForUnknownCode(): void
    {
        $result = ErrorDictionary::getMessage('some_unknown_xyz');
        $this->assertSame('Erreur: Some unknown xyz.', $result);
    }

    public function testGetMessageFallbackCapitalizesFirstWord(): void
    {
        $result = ErrorDictionary::getMessage('test_error_code');
        $this->assertSame('Erreur: Test error code.', $result);
    }

    public function testGetMessageFallbackWithSingleWordCode(): void
    {
        $result = ErrorDictionary::getMessage('myerror');
        $this->assertSame('Erreur: Myerror.', $result);
    }

    // =========================================================================
    // hasMessage()
    // =========================================================================

    public function testHasMessageReturnsTrueForKnownCodes(): void
    {
        $this->assertTrue(ErrorDictionary::hasMessage('unauthorized'));
        $this->assertTrue(ErrorDictionary::hasMessage('forbidden'));
        $this->assertTrue(ErrorDictionary::hasMessage('server_error'));
    }

    public function testHasMessageReturnsFalseForUnknownCodes(): void
    {
        $this->assertFalse(ErrorDictionary::hasMessage('nonexistent_code'));
        $this->assertFalse(ErrorDictionary::hasMessage('xyz_abc'));
    }

    // =========================================================================
    // getCodes()
    // =========================================================================

    public function testGetCodesReturnsNonEmptyArray(): void
    {
        $codes = ErrorDictionary::getCodes();
        $this->assertIsArray($codes);
        $this->assertGreaterThan(100, count($codes), 'Expected more than 100 codes defined');
    }

    public function testGetCodesContainsExpectedCodes(): void
    {
        $codes = ErrorDictionary::getCodes();
        $this->assertContains('unauthorized', $codes);
        $this->assertContains('meeting_not_found', $codes);
        $this->assertContains('vote_not_allowed', $codes);
    }

    // =========================================================================
    // enrichError()
    // =========================================================================

    public function testEnrichErrorAddsMessageKey(): void
    {
        $result = ErrorDictionary::enrichError('unauthorized');
        $this->assertArrayHasKey('message', $result);
        $this->assertSame(ErrorDictionary::getMessage('unauthorized'), $result['message']);
    }

    public function testEnrichErrorAppendsDetailToMessage(): void
    {
        $result = ErrorDictionary::enrichError('server_error', ['detail' => 'Connection reset']);
        $this->assertStringEndsWith(' Connection reset', $result['message']);
    }

    public function testEnrichErrorPreservesExtraKeys(): void
    {
        $result = ErrorDictionary::enrichError('unauthorized', ['request_id' => '123']);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('request_id', $result);
        $this->assertSame('123', $result['request_id']);
    }

    public function testEnrichErrorWithEmptyDetailDoesNotAppend(): void
    {
        $result = ErrorDictionary::enrichError('unauthorized', ['detail' => '']);
        $this->assertSame(ErrorDictionary::getMessage('unauthorized'), $result['message']);
    }

    public function testEnrichErrorWithNoExtraReturnsOnlyMessage(): void
    {
        $result = ErrorDictionary::enrichError('forbidden');
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('message', $result);
    }
}
