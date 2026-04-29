<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Repository\MotionRepository;
use PHPUnit\Framework\TestCase;

/**
 * F08 regression: every MotionRepository method that took only a (motion_id,
 * meeting_id) pair was an IDOR primitive — a caller could pass any motion_id
 * with a meeting_id that happens to belong to ANOTHER tenant and the query
 * returned the row.
 *
 * This test verifies the API surface no longer exposes that footgun.
 *
 * It is a STATIC contract test: it asserts via Reflection that the dangerous
 * methods either no longer exist or now require a tenantId parameter. We
 * intentionally avoid spinning up a real DB — the goal is to prevent future
 * regressions at the type-signature level.
 */
final class MotionRepositoryTenantIsolationTest extends TestCase {
    public function testFindByIdAndMeetingIsRemoved(): void {
        $ref = new \ReflectionClass(MotionRepository::class);

        $this->assertFalse(
            $ref->hasMethod('findByIdAndMeeting'),
            'F08: findByIdAndMeeting must NOT exist — it had no tenant gate '
            . 'and no callers. Re-introducing it re-opens an IDOR primitive.',
        );
    }

    public function testFindByIdAndMeetingWithDatesRequiresTenantId(): void {
        $ref = new \ReflectionClass(MotionRepository::class);

        $this->assertTrue($ref->hasMethod('findByIdAndMeetingWithDates'));

        $method = $ref->getMethod('findByIdAndMeetingWithDates');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(
            3,
            count($params),
            'F08: findByIdAndMeetingWithDates must take at least 3 params '
            . '(motionId, meetingId, tenantId).',
        );

        $tenantParam = $params[2];
        $this->assertSame('tenantId', $tenantParam->getName());
        $this->assertFalse(
            $tenantParam->isOptional(),
            'F08: tenantId on findByIdAndMeetingWithDates must be REQUIRED '
            . '(no default) so callers cannot accidentally drop tenant scope.',
        );

        $type = $tenantParam->getType();
        $this->assertNotNull($type);
        $this->assertSame('string', (string) $type);
        $this->assertFalse(
            $type instanceof \ReflectionNamedType ? $type->allowsNull() : true,
            'F08: tenantId must be non-null string.',
        );
    }
}
