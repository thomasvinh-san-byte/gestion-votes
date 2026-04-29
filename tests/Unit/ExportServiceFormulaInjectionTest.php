<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\ExportService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * F15: formula-injection prevention on CSV/XLSX exports.
 *
 * Each test calls the private sanitizeCsvCell helper via Reflection to
 * verify the prefix-with-' policy is applied to every dangerous lead char,
 * and only those.
 */
final class ExportServiceFormulaInjectionTest extends TestCase {
    private ExportService $service;
    private ReflectionMethod $sanitize;

    protected function setUp(): void {
        $this->service = new ExportService();
        $this->sanitize = new ReflectionMethod(ExportService::class, 'sanitizeCsvCell');
        $this->sanitize->setAccessible(true);
    }

    private function sanitize(mixed $value): string {
        return (string) $this->sanitize->invoke($this->service, $value);
    }

    public function testEqualsLeadIsEscaped(): void {
        $this->assertSame("'=cmd|/c calc", $this->sanitize('=cmd|/c calc'));
        $this->assertSame("'=SUM(A1:A2)", $this->sanitize('=SUM(A1:A2)'));
    }

    public function testPlusLeadIsEscaped(): void {
        $this->assertSame("'+1+1", $this->sanitize('+1+1'));
    }

    public function testMinusLeadIsEscaped(): void {
        $this->assertSame("'-2+3", $this->sanitize('-2+3'));
    }

    public function testAtLeadIsEscaped(): void {
        $this->assertSame("'@SUM(A1:A2)", $this->sanitize('@SUM(A1:A2)'));
    }

    public function testTabLeadIsEscaped(): void {
        $this->assertSame("'\tcmd", $this->sanitize("\tcmd"));
    }

    public function testCarriageReturnLeadIsEscaped(): void {
        $this->assertSame("'\rcmd", $this->sanitize("\rcmd"));
    }

    public function testSafeStringsPassThrough(): void {
        $this->assertSame('Alice Martin', $this->sanitize('Alice Martin'));
        $this->assertSame('alice@example.com', $this->sanitize('alice@example.com')); // @ in middle, safe
        $this->assertSame('100', $this->sanitize('100'));
        $this->assertSame('', $this->sanitize(''));
        $this->assertSame('', $this->sanitize(null)); // (string) null === ''
    }

    public function testEmptyStringIsLeftAlone(): void {
        $this->assertSame('', $this->sanitize(''));
        $this->assertSame('', $this->sanitize(null));
    }

    public function testSingleQuoteInsideValueIsNotEscapedTwice(): void {
        // The escape only fires on the FIRST char. An inner ' is fine.
        $this->assertSame("Alice's account", $this->sanitize("Alice's account"));
    }

    public function testNumberLeadingWithMinusSignIsEscaped(): void {
        // Real Excel parses -1 as a formula (= -1) → must escape.
        $this->assertSame("'-1", $this->sanitize('-1'));
        $this->assertSame("'-100.5", $this->sanitize('-100.5'));
    }
}
