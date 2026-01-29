#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * test_api_integration.php - Tests d'intégration API automatisés
 * 
 * Usage: php scripts/test_api_integration.php [--base-url=http://localhost:8080]
 * 
 * Teste les principaux endpoints de l'API AG-VOTE.
 */

$baseUrl = 'http://localhost:8080';

// Parse arguments
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = substr($arg, strlen('--base-url='));
    }
}

$baseUrl = rtrim($baseUrl, '/');

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║           AG-VOTE - Tests d'intégration API                       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Base URL: {$baseUrl}\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('─', 70) . "\n\n";

$passed = 0;
$failed = 0;
$errors = [];

function test(string $name, callable $testFn): void {
    global $passed, $failed, $errors;
    
    echo "🧪 {$name}... ";
    
    try {
        $result = $testFn();
        if ($result === true || $result === null) {
            echo "✅ PASS\n";
            $passed++;
        } else {
            echo "❌ FAIL: {$result}\n";
            $failed++;
            $errors[] = "{$name}: {$result}";
        }
    } catch (Throwable $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $failed++;
        $errors[] = "{$name}: " . $e->getMessage();
    }
}

function apiGet(string $url): array {
    global $baseUrl;
    $ch = curl_init("{$baseUrl}{$url}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'X-Api-Key: dev-test-key'
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response ?: '{}', true) ?: [],
        'raw' => $response,
    ];
}

function apiPost(string $url, array $data): array {
    global $baseUrl;
    $ch = curl_init("{$baseUrl}{$url}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Api-Key: dev-test-key',
            'X-CSRF-Token: test-csrf-token'
        ],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response ?: '{}', true) ?: [],
        'raw' => $response,
    ];
}

// ============================================================================
// TESTS DE BASE
// ============================================================================

echo "📋 Tests de base\n";
echo str_repeat('─', 70) . "\n";

test("Ping API", function() {
    $r = apiGet('/api/v1/ping.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    if (!isset($r['body']['ok'])) return "Missing 'ok' field";
    return true;
});

test("Whoami endpoint", function() {
    $r = apiGet('/api/v1/whoami.php');
    if ($r['code'] !== 200 && $r['code'] !== 401) return "HTTP {$r['code']}";
    return true;
});

// ============================================================================
// TESTS MEETINGS
// ============================================================================

echo "\n📋 Tests Séances (Meetings)\n";
echo str_repeat('─', 70) . "\n";

test("Liste des séances", function() {
    $r = apiGet('/api/v1/meetings.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    if (!isset($r['body']['ok'])) return "Missing 'ok' field";
    return true;
});

test("Dashboard", function() {
    $r = apiGet('/api/v1/dashboard.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    return true;
});

// ============================================================================
// TESTS MEMBERS
// ============================================================================

echo "\n📋 Tests Membres\n";
echo str_repeat('─', 70) . "\n";

test("Liste des membres", function() {
    $r = apiGet('/api/v1/members.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    return true;
});

// ============================================================================
// TESTS POLICIES
// ============================================================================

echo "\n📋 Tests Politiques\n";
echo str_repeat('─', 70) . "\n";

test("Politiques de quorum", function() {
    $r = apiGet('/api/v1/quorum_policies.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    return true;
});

test("Politiques de vote", function() {
    $r = apiGet('/api/v1/vote_policies.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    return true;
});

// ============================================================================
// TESTS ADMIN
// ============================================================================

echo "\n📋 Tests Administration\n";
echo str_repeat('─', 70) . "\n";

test("Statut système", function() {
    $r = apiGet('/api/v1/admin_system_status.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    return true;
});

// ============================================================================
// TESTS ARCHIVES
// ============================================================================

echo "\n📋 Tests Archives\n";
echo str_repeat('─', 70) . "\n";

test("Liste archives", function() {
    $r = apiGet('/api/v1/archives_list.php');
    if ($r['code'] !== 200) return "HTTP {$r['code']}";
    return true;
});

// ============================================================================
// TESTS SÉCURITÉ
// ============================================================================

echo "\n📋 Tests Sécurité\n";
echo str_repeat('─', 70) . "\n";

test("Headers de sécurité présents", function() {
    global $baseUrl;
    $ch = curl_init("{$baseUrl}/api/v1/ping.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_NOBODY => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $hasCSP = stripos($response, 'Content-Security-Policy') !== false;
    $hasXFrame = stripos($response, 'X-Frame-Options') !== false;
    $hasXContent = stripos($response, 'X-Content-Type-Options') !== false;
    
    if (!$hasXContent) return "Missing X-Content-Type-Options";
    return true;
});

test("Méthode OPTIONS (CORS preflight)", function() {
    global $baseUrl;
    $ch = curl_init("{$baseUrl}/api/v1/meetings.php");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'OPTIONS',
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 204 && $httpCode !== 200) return "HTTP {$httpCode}";
    return true;
});

// ============================================================================
// TESTS ENDPOINTS HTMX
// ============================================================================

echo "\n📋 Tests Pages HTMX\n";
echo str_repeat('─', 70) . "\n";

$pages = [
    '/index.html' => 'Page accueil',
    '/meetings.htmx.html' => 'Dashboard séances',
    '/members.htmx.html' => 'Gestion membres',
    '/admin.htmx.html' => 'Administration',
    '/trust.htmx.html' => 'Contrôle & audit',
    '/archives.htmx.html' => 'Archives',
];

foreach ($pages as $path => $name) {
    test("Page {$name}", function() use ($baseUrl, $path) {
        $ch = curl_init("{$baseUrl}{$path}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) return "HTTP {$httpCode}";
        if (stripos($response, '<!DOCTYPE html>') === false && stripos($response, '<!doctype html>') === false) {
            return "Not HTML";
        }
        return true;
    });
}

// ============================================================================
// RÉSUMÉ
// ============================================================================

echo "\n" . str_repeat('═', 70) . "\n";
echo "📊 RÉSUMÉ DES TESTS\n";
echo str_repeat('═', 70) . "\n\n";

$total = $passed + $failed;
$percentage = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

echo "  Total:    {$total} tests\n";
echo "  Réussis:  {$passed} ✅\n";
echo "  Échoués:  {$failed} ❌\n";
echo "  Taux:     {$percentage}%\n\n";

if (count($errors) > 0) {
    echo "❌ Erreurs détaillées:\n";
    foreach ($errors as $error) {
        echo "   • {$error}\n";
    }
    echo "\n";
}

if ($failed === 0) {
    echo "🎉 Tous les tests sont passés !\n\n";
    exit(0);
} else {
    echo "⚠️  Certains tests ont échoué. Vérifiez les erreurs ci-dessus.\n\n";
    exit(1);
}
