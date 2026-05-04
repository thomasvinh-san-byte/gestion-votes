<?php

declare(strict_types=1);

namespace Tests\Security;

use AgVote\Service\ErrorDictionary;
use PHPUnit\Framework\TestCase;

/**
 * v2.4 Phase 02 (ERR-V24-01) — guard for the migration of `business_error`
 * to 3 specific codes.
 *
 * Locks three invariants so any regression on the v2.4 ERR-V24-01 work surfaces
 * as a test failure:
 *
 *  1. testNewCodesExist : the 3 new codes (meeting_transition_failed,
 *     meeting_operation_failed, meeting_state_read_failed) are present in
 *     ErrorDictionary::MESSAGES.
 *
 *  2. testNewCodesConformErr02Err03 : every new message obeys ERR-02
 *     (comma + action verb from the whitelist) and ERR-03 (none of the
 *     5 banned hollow phrases). Whitelists are reused from
 *     UxConventionsTest to avoid drift.
 *
 *  3. testBusinessErrorCallersBaseline : the count of api_fail('business_error',
 *     ...) call sites in app/ + public/api/ stays at or below the post-migration
 *     baseline (1 legitimate fallback in AbstractController::handle, with a
 *     safety margin of 3). Any new caller must be reviewed and either justified
 *     or migrated to a specific code.
 *
 * See:
 *   - .planning/phases/02-error-observability/02.1-AUDIT.md (caller audit + buckets)
 *   - .planning/phases/02-error-observability/02-CONTEXT.md (decisions D-01..D-04)
 *   - tests/Security/UxConventionsTest.php (ACTION_VERBS + FORBIDDEN_PATTERNS source)
 */
final class ErrorDictionaryMigrationTest extends TestCase
{
    /**
     * The 3 specific codes added by Plan 02.1 to replace business_error.
     *
     * @var list<string>
     */
    private const NEW_CODES = [
        'meeting_transition_failed',
        'meeting_operation_failed',
        'meeting_state_read_failed',
    ];

    /**
     * Banned hollow phrases (ERR-03). Reused verbatim from UxConventionsTest
     * to avoid drift — this guard MUST stay in sync with the v2.3 baseline.
     *
     * @var list<string>
     */
    private const FORBIDDEN_PATTERNS = [
        '/réessayer\.?$/i',
        '/contactez (le|l\')admin/i',
        '/erreur survenue/i',
        '/une erreur est survenue/i',
        '/veuillez réessayer plus tard/i',
    ];

    /**
     * Action-verb whitelist (ERR-02). Subset reused from UxConventionsTest::ACTION_VERBS,
     * sufficient for the 3 new codes — kept short on purpose so future codes
     * that introduce new verbs trigger the whitelist update explicitly.
     *
     * @var list<string>
     */
    private const ACTION_VERBS = [
        'vérifiez', 'verifiez', 'consultez', 'actualisez', 'demandez',
        'attendez', 'utilisez', 'reconnectez', 'rechargez', 'sélectionnez',
        'selectionnez', 'corrigez', 'modifiez', 'soumettez', 'relancez',
    ];

    /**
     * Post-migration baseline: 1 legitimate fallback caller in
     * AbstractController::handle (catch-all RuntimeException).
     * Margin set to 3 to absorb the occasional new legitimate fallback
     * without needing immediate test edits — but anything above 3 must be
     * audited.
     */
    private const BUSINESS_ERROR_CALLERS_MAX = 3;

    public function testNewCodesExist(): void
    {
        foreach (self::NEW_CODES as $code) {
            $this->assertTrue(
                ErrorDictionary::hasMessage($code),
                "Code '{$code}' missing from ErrorDictionary::MESSAGES — Plan 02.1 regression."
            );
        }
    }

    public function testNewCodesConformErr02Err03(): void
    {
        $errors = [];

        foreach (self::NEW_CODES as $code) {
            if (!ErrorDictionary::hasMessage($code)) {
                $errors[] = "{$code} (code absent — see testNewCodesExist)";
                continue;
            }

            $msg = ErrorDictionary::getMessage($code);

            // ERR-02 — must contain a comma (next-step separator)
            if (strpos($msg, ',') === false) {
                $errors[] = "{$code} ERR-02 fail (no comma) : « {$msg} »";
                continue;
            }

            // ERR-02 — must contain at least one action verb
            $msgLower = mb_strtolower($msg, 'UTF-8');
            $hasVerb = false;
            foreach (self::ACTION_VERBS as $verb) {
                if (strpos($msgLower, mb_strtolower($verb, 'UTF-8')) !== false) {
                    $hasVerb = true;
                    break;
                }
            }
            if (!$hasVerb) {
                $errors[] = "{$code} ERR-02 fail (no action verb) : « {$msg} »";
            }

            // ERR-03 — must not contain any banned hollow phrase
            foreach (self::FORBIDDEN_PATTERNS as $pattern) {
                if (preg_match($pattern, $msg) === 1) {
                    $errors[] = "{$code} ERR-03 fail (matches {$pattern}) : « {$msg} »";
                }
            }
        }

        $this->assertSame(
            [],
            $errors,
            "New codes (Plan 02.1) breaking ERR-02/ERR-03 :\n  - " . implode("\n  - ", $errors)
        );
    }

    public function testBusinessErrorCallersBaseline(): void
    {
        // Search api_fail('business_error', ...) callers in production code.
        // Excludes the dictionary entry itself (no api_fail( prefix).
        $cmd = "grep -rE \"api_fail\\(\\s*'business_error'\" "
             . escapeshellarg(__DIR__ . '/../../app')
             . ' '
             . escapeshellarg(__DIR__ . '/../../public/api')
             . ' 2>/dev/null | wc -l';

        $output = shell_exec($cmd);
        $count = (int) trim($output ?? '0');

        $this->assertLessThanOrEqual(
            self::BUSINESS_ERROR_CALLERS_MAX,
            $count,
            sprintf(
                'business_error callers above the post-migration baseline (%d found, max %d allowed). '
                . 'Migrate new callers to a specific code (see Plan 02.1 audit) or, if the call is a '
                . 'legitimate generic fallback, raise the baseline with a justification.',
                $count,
                self::BUSINESS_ERROR_CALLERS_MAX
            )
        );
    }
}
