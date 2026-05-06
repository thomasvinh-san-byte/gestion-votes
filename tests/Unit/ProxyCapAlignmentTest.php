<?php

declare(strict_types=1);

namespace Tests\Unit;

use AgVote\Service\ProxiesService;
use PHPUnit\Framework\TestCase;

/**
 * Guards the alignment between the API upsert path and the import path
 * for the per-receiver proxy cap.
 *
 * Stage 1 audit (CRITICAL-PATH-AUDIT.md étape 08, AUDIT-CHEMIN-08) caught
 * a latent inconsistency:
 * - ProxiesService::upsert defaulted to 99
 * - ImportController defaulted to 3
 *
 * Result: when `proxy_max_per_receiver` was not set in tenant_settings,
 * the API accepted up to 99 but the import capped at 3 — silent
 * divergence depending on the entry point.
 *
 * The fix exposes ProxiesService::DEFAULT_MAX_PER_RECEIVER and routes
 * both paths through it. This test prevents regression by:
 * 1. Asserting the constant exists and equals 3 (French association
 *    practice: 1-3 proxies max).
 * 2. Asserting the constant is referenced by ImportController so a
 *    future edit can't drift again.
 *
 * Refs: M-INFRA-CLEANUP / CLEANUP-CHEMIN-PROCURATION.
 */
final class ProxyCapAlignmentTest extends TestCase {
    public function testDefaultCapIsAlignedAt3(): void {
        $this->assertSame(
            3,
            ProxiesService::DEFAULT_MAX_PER_RECEIVER,
            'Default proxy cap must remain 3 (French asso practice).',
        );
    }

    public function testImportControllerReferencesTheServiceConstant(): void {
        $controller = file_get_contents(__DIR__ . '/../../app/Controller/ImportController.php');
        $this->assertNotFalse($controller);

        // Both proxy import paths (CSV + XLSX) must call the constant.
        $occurrences = substr_count(
            $controller,
            'ProxiesService::DEFAULT_MAX_PER_RECEIVER',
        );
        $this->assertGreaterThanOrEqual(
            2,
            $occurrences,
            'ImportController must reference DEFAULT_MAX_PER_RECEIVER for both CSV and XLSX proxy import paths.',
        );

        // Hard-coded 3 or 99 fallbacks would re-introduce the divergence.
        $this->assertDoesNotMatchRegularExpression(
            "/config\(\s*'proxy_max_per_receiver'\s*,\s*(?:3|99)\s*\)/",
            $controller,
            'Hard-coded fallback for proxy_max_per_receiver must not return — use the service constant.',
        );
    }

    public function testProxiesServiceUsesItsOwnConstant(): void {
        $service = file_get_contents(__DIR__ . '/../../app/Services/ProxiesService.php');
        $this->assertNotFalse($service);

        $this->assertStringContainsString(
            "config('proxy_max_per_receiver', self::DEFAULT_MAX_PER_RECEIVER)",
            $service,
            'ProxiesService::upsert must read the cap via the shared constant.',
        );
        $this->assertDoesNotMatchRegularExpression(
            "/config\(\s*'proxy_max_per_receiver'\s*,\s*99\s*\)/",
            $service,
            'Hard-coded 99 default has been the historical source of the divergence — must be removed.',
        );
    }
}
