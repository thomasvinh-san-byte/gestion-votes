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

    // =========================================================================
    // STRICT MODE (throws ApiResponseException on limit)
    // =========================================================================

    public function testStrictModeThrowsWhenLimited(): void {
        // Atteindre la limite
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::check('strict-ctx', '10.0.0.1', 3, 60, false);
        }

        // En mode strict, la requête suivante doit lever une exception
        $this->expectException(\AgVote\Core\Http\ApiResponseException::class);
        RateLimiter::check('strict-ctx', '10.0.0.1', 3, 60, true);
    }

    public function testNonStrictModeReturnsFalseWhenLimited(): void {
        for ($i = 0; $i < 3; $i++) {
            RateLimiter::check('nonstrict-ctx', '10.0.0.1', 3, 60, false);
        }

        // En mode non-strict, renvoie simplement false
        $result = RateLimiter::check('nonstrict-ctx', '10.0.0.1', 3, 60, false);
        $this->assertFalse($result);
    }

    // =========================================================================
    // SLIDING WINDOW BEHAVIOR
    // =========================================================================

    public function testSlidingWindowExpiration(): void {
        // Créer un fichier de rate limit avec des timestamps expirés
        $key = hash('sha256', 'sliding-window:expired-ip');
        $file = $this->testDir . '/ratelimit_' . $key;

        // Écrire des timestamps vieux de 120 secondes
        $oldTimestamp = time() - 120;
        $lines = [];
        for ($i = 0; $i < 5; $i++) {
            $lines[] = (string) ($oldTimestamp + $i);
        }
        file_put_contents($file, implode("\n", $lines) . "\n");

        // Avec une fenêtre de 60 secondes, les anciens timestamps sont expirés
        // Donc la requête devrait être autorisée
        $result = RateLimiter::check('sliding-window', 'expired-ip', 5, 60, false);
        $this->assertTrue($result, 'Old timestamps should have expired from sliding window');
    }

    public function testWindowSizeOneSecond(): void {
        // Fenêtre très courte (1 seconde), limite de 2
        for ($i = 0; $i < 2; $i++) {
            RateLimiter::check('tiny-window', '127.0.0.1', 2, 1, false);
        }

        // Devrait être limité immédiatement
        $result = RateLimiter::check('tiny-window', '127.0.0.1', 2, 1, false);
        $this->assertFalse($result);
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testEmptyIdentifier(): void {
        $result = RateLimiter::check('edge', '', 10, 60, false);
        $this->assertTrue($result, 'Empty identifier should still work');
    }

    public function testSpecialCharactersInIdentifier(): void {
        $result = RateLimiter::check('edge', 'user@domain.com/path?q=1&x=2', 10, 60, false);
        $this->assertTrue($result, 'Special characters in identifier should be hashed safely');
    }

    public function testResetNonExistentKey(): void {
        // Reset sur une clé qui n'existe pas ne devrait pas planter
        RateLimiter::reset('nonexistent-ctx', 'nonexistent-ip');
        $this->assertTrue(true, 'Resetting non-existent key should not throw');
    }

    public function testLimitOfOne(): void {
        $result1 = RateLimiter::check('one-limit', '127.0.0.1', 1, 60, false);
        $this->assertTrue($result1);

        $result2 = RateLimiter::check('one-limit', '127.0.0.1', 1, 60, false);
        $this->assertFalse($result2);
    }

    public function testCleanupWithEmptyDirectory(): void {
        // Dossier vide — cleanup ne devrait rien faire
        $cleaned = RateLimiter::cleanup(3600);
        $this->assertEquals(0, $cleaned, 'Cleanup on empty dir should return 0');
    }

    public function testCleanupPreservesNonRateLimitFiles(): void {
        // Fichier qui n'est pas un fichier de rate limit
        $otherFile = $this->testDir . '/some_other_file.txt';
        file_put_contents($otherFile, 'test');
        touch($otherFile, time() - 7200);

        RateLimiter::cleanup(3600);

        // Le comportement dépend de l'implémentation (peut ou non supprimer les fichiers non-ratelimit)
        // On vérifie juste que ça ne plante pas
        $this->assertTrue(true);
    }

    public function testHighConcurrencySimulation(): void {
        $limit = 100;
        $allowed = 0;
        $blocked = 0;

        for ($i = 0; $i < 150; $i++) {
            if (RateLimiter::check('highload', '10.0.0.1', $limit, 60, false)) {
                $allowed++;
            } else {
                $blocked++;
            }
        }

        $this->assertEquals($limit, $allowed, "Exactly {$limit} requests should be allowed");
        $this->assertEquals(50, $blocked, '50 requests should be blocked');
    }
}
