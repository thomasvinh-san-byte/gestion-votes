<?php

declare(strict_types=1);

namespace Tests\Unit\Controller;

use AgVote\Controller\AbstractController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * ERR-V26-01 — validates extractBusinessErrorCode() regex.
 *
 * AbstractController::handle() catches RuntimeException and, if the message
 * matches a snake_case pattern, surfaces it as the api_fail() error_code
 * instead of the generic 'business_error'. This test exercises the regex
 * directly via ReflectionMethod (avoids mocking the api_fail() global which
 * orchestrates capture + throw).
 */
final class AbstractControllerBusinessErrorTest extends TestCase {
    private ReflectionMethod $extractor;

    protected function setUp(): void {
        $this->extractor = new ReflectionMethod(AbstractController::class, 'extractBusinessErrorCode');
        $this->extractor->setAccessible(true);
    }

    private function extract(string $message): ?string {
        return $this->extractor->invoke(null, $message);
    }

    public function test_extracts_archived_meeting_locked(): void {
        $this->assertSame('archived_meeting_locked', $this->extract('archived_meeting_locked'));
    }

    public function test_extracts_validated_meeting_locked(): void {
        $this->assertSame('validated_meeting_locked', $this->extract('validated_meeting_locked'));
    }

    public function test_extracts_meeting_not_found_existing_pattern(): void {
        // regression: les 49 sites existants RuntimeException('meeting_not_found')
        // doivent désormais surface ce code (au lieu de business_error)
        $this->assertSame('meeting_not_found', $this->extract('meeting_not_found'));
    }

    public function test_falls_back_on_french_message(): void {
        $this->assertNull($this->extract('Séance introuvable'));
    }

    public function test_falls_back_on_empty_message(): void {
        $this->assertNull($this->extract(''));
        $this->assertNull($this->extract('   '));
    }

    public function test_falls_back_on_message_with_spaces(): void {
        $this->assertNull($this->extract('meeting not found'));
    }

    public function test_falls_back_on_message_with_punctuation(): void {
        $this->assertNull($this->extract('meeting_not_found.'));
        $this->assertNull($this->extract('meeting:not:found'));
    }

    public function test_falls_back_on_oversized_message(): void {
        $this->assertNull($this->extract(str_repeat('a', 50)));
    }

    public function test_falls_back_on_message_with_double_underscore(): void {
        $this->assertNull($this->extract('meeting__not_found'));
    }

    public function test_falls_back_on_message_starting_with_digit(): void {
        $this->assertNull($this->extract('1_meeting_not_found'));
    }
}
