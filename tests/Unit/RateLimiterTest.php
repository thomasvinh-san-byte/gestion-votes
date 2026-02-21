<?php

declare(strict_types=1);

use AgVote\Core\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../app/Core/Security/RateLimiter.php';

/**
 * Unit tests for RateLimiter.
 */
class RateLimiterTest extends TestCase {
    private string $testDir;

    protected function setUp(): void {
        $this->testDir = sys_get_temp_dir() . '/ag-vote-ratelimit-test-' . uniqid();
        mkdir($this->testDir, 0o755, true);

        RateLimiter::configure([
            'storage_dir' => $this->testDir,
        ]);
    }

    protected function tearDown(): void {
        // Nettoyer le répertoire de test
        if (is_dir($this->testDir)) {
            $files = glob($this->testDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testDir);
        }
    }

    public function testCheckAllowsFirstRequest(): void {
        $result = RateLimiter::check('test', '127.0.0.1', 10, 60, false);

        $this->assertTrue($result);
    }

    public function testCheckAllowsRequestsUnderLimit(): void {
        for ($i = 0; $i < 5; $i++) {
            $result = RateLimiter::check('test', '127.0.0.1', 10, 60, false);
            $this->assertTrue($result, "Request {$i} should be allowed");
        }
    }

    public function testCheckBlocksAfterLimit(): void {
        // Atteindre la limite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('limit-test', '127.0.0.1', 5, 60, false);
        }

        // La 6ème requête devrait être bloquée
        $result = RateLimiter::check('limit-test', '127.0.0.1', 5, 60, false);

        $this->assertFalse($result);
    }

    public function testIsLimitedReturnsFalseInitially(): void {
        $result = RateLimiter::isLimited('new-context', '192.168.1.1', 10, 60);

        $this->assertFalse($result);
    }

    public function testIsLimitedReturnsTrueAtLimit(): void {
        // Atteindre la limite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('limited-context', '192.168.1.1', 5, 60, false);
        }

        $result = RateLimiter::isLimited('limited-context', '192.168.1.1', 5, 60);

        $this->assertTrue($result);
    }

    public function testResetClearsLimit(): void {
        // Atteindre la limite
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('reset-context', '10.0.0.1', 5, 60, false);
        }

        // Vérifier qu'on est limité
        $this->assertTrue(RateLimiter::isLimited('reset-context', '10.0.0.1', 5, 60));

        // Reset
        RateLimiter::reset('reset-context', '10.0.0.1');

        // Vérifier qu'on n'est plus limité
        $this->assertFalse(RateLimiter::isLimited('reset-context', '10.0.0.1', 5, 60));
    }

    public function testDifferentContextsAreSeparate(): void {
        // Remplir context1
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('context1', '127.0.0.1', 5, 60, false);
        }

        // context1 devrait être limité
        $this->assertTrue(RateLimiter::isLimited('context1', '127.0.0.1', 5, 60));

        // context2 devrait être libre
        $this->assertFalse(RateLimiter::isLimited('context2', '127.0.0.1', 5, 60));
    }

    public function testDifferentIdentifiersAreSeparate(): void {
        // Remplir pour IP1
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::check('shared-context', 'ip1', 5, 60, false);
        }

        // IP1 devrait être limitée
        $this->assertTrue(RateLimiter::isLimited('shared-context', 'ip1', 5, 60));

        // IP2 devrait être libre
        $this->assertFalse(RateLimiter::isLimited('shared-context', 'ip2', 5, 60));
    }

    public function testCleanupRemovesOldFiles(): void {
        // Créer un fichier "ancien"
        $oldFile = $this->testDir . '/old_file';
        file_put_contents($oldFile, 'test');
        touch($oldFile, time() - 7200); // 2 heures

        // Exécuter cleanup
        $cleaned = RateLimiter::cleanup(3600); // 1 heure max

        $this->assertGreaterThan(0, $cleaned);
        $this->assertFileDoesNotExist($oldFile);
    }

    public function testCleanupKeepsRecentFiles(): void {
        // Créer un fichier récent
        $recentFile = $this->testDir . '/recent_file';
        file_put_contents($recentFile, 'test');
        touch($recentFile, time() - 300); // 5 minutes

        // Exécuter cleanup
        RateLimiter::cleanup(3600); // 1 heure max

        $this->assertFileExists($recentFile);
    }

    public function testConcurrentAccessHandled(): void {
        // Simuler des accès concurrents (simplifié)
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = RateLimiter::check('concurrent', 'user1', 10, 60, false);
        }

        // Toutes les requêtes devraient avoir été traitées
        $this->assertCount(10, $results);
        $this->assertContainsOnly('bool', $results);
    }
}
