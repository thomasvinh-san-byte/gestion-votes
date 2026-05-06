<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Static checks for the motion.kind migration + repository hookup.
 *
 * No live DB required — these tests verify SQL artifacts and PHP signature
 * remain in sync. Live behaviour (DB default kicks in, CHECK constraint
 * rejects unknown kinds) is covered by integration runs in CI.
 *
 * Refs: M-INFRA-CLEANUP / CLEANUP-CHEMIN-MOTION-KIND.
 */
final class MotionKindMigrationTest extends TestCase {
    private const MIGRATION_PATH = __DIR__ . '/../../database/migrations/20260506_motion_kind.sql';
    private const TRAIT_PATH = __DIR__ . '/../../app/Repository/Traits/MotionWriterTrait.php';

    public function testMigrationFileExists(): void {
        $this->assertFileExists(self::MIGRATION_PATH);
    }

    public function testMigrationIsIdempotent(): void {
        $sql = file_get_contents(self::MIGRATION_PATH);
        $this->assertNotFalse($sql);

        // Re-running the migration must not error out — IF NOT EXISTS guards
        // every DDL touch.
        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS kind', $sql);
        $this->assertStringContainsString('CREATE INDEX IF NOT EXISTS idx_motions_kind', $sql);
        $this->assertMatchesRegularExpression(
            '/IF\s+NOT\s+EXISTS\s*\(\s*SELECT\s+1\s+FROM\s+pg_constraint\s+WHERE\s+conname\s*=\s*\'motions_kind_check\'/',
            $sql,
            'CHECK constraint must be wrapped in pg_constraint existence guard for idempotence.',
        );
    }

    public function testMigrationDeclaresDefaultResolution(): void {
        $sql = file_get_contents(self::MIGRATION_PATH);

        $this->assertMatchesRegularExpression(
            '/kind\s+TEXT\s+NOT\s+NULL\s+DEFAULT\s+\'resolution\'/i',
            $sql,
            "Existing motions must retrofit to 'resolution' via DEFAULT.",
        );
    }

    public function testMigrationRestrictsKindToResolution(): void {
        $sql = file_get_contents(self::MIGRATION_PATH);

        // Stage 1 / DECISION.md: only 'resolution' is implemented in the pivot
        // scope. The CHECK enforces this until a new kind ships.
        $this->assertMatchesRegularExpression(
            "/CHECK\s*\(\s*kind\s+IN\s*\(\s*'resolution'\s*\)\s*\)/i",
            $sql,
        );
    }

    public function testRepositoryCreateAcceptsOptionalKind(): void {
        $php = file_get_contents(self::TRAIT_PATH);
        $this->assertNotFalse($php);

        // Backwards-compatible: optional with default null. Existing callers
        // (MotionsService, XlsxImporter, MeetingLifecycleService) must keep
        // working without modification.
        $this->assertMatchesRegularExpression(
            '/\?string\s+\$kind\s*=\s*null/',
            $php,
            'create() must accept ?string $kind = null to remain non-breaking.',
        );

        // The SQL must coalesce to the DB default when kind is empty/null
        // so we never emit NULL into a NOT NULL column.
        $this->assertStringContainsString(
            "COALESCE(NULLIF(:kind,''), 'resolution')",
            $php,
            'INSERT must defer to the resolution default when no kind is supplied.',
        );
    }
}
