#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * recette_demo.php - Scénario de recette automatisé
 * 
 * Usage: php scripts/recette_demo.php [--base-url=http://localhost:8080] [--api-key=xxx]
 * 
 * Exécute le scénario de démonstration complet décrit dans RECETTE_DEMO.md
 */

$baseUrl = 'http://localhost:8080';
$apiKey = 'dev-test-key';

// Parse arguments
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--base-url=')) {
        $baseUrl = substr($arg, strlen('--base-url='));
    }
    if (str_starts_with($arg, '--api-key=')) {
        $apiKey = substr($arg, strlen('--api-key='));
    }
}

$baseUrl = rtrim($baseUrl, '/');

// Variables globales pour le scénario
$meetingId = null;
$motionIds = [];
$memberIds = [];
$voteTokens = [];

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║              AG-VOTE - SCÉNARIO DE RECETTE AUTOMATISÉ                     ║\n";
echo "║                      (≈ 10 minutes)                                       ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Base URL: {$baseUrl}\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "\n";

function apiCall(string $method, string $url, array $data = []): array {
    global $baseUrl, $apiKey;
    
    $ch = curl_init("{$baseUrl}{$url}");
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Api-Key: ' . $apiKey,
        'X-CSRF-Token: recette-csrf-token'
    ];
    
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
    ];
    
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response ?: '{}', true) ?: [],
        'raw' => $response,
        'error' => $error,
    ];
}

function step(string $title, string $description = ''): void {
    echo "\n";
    echo "┌─────────────────────────────────────────────────────────────────────────┐\n";
    echo "│ 📋 " . str_pad($title, 71) . "│\n";
    if ($description) {
        echo "│    " . str_pad($description, 71) . "│\n";
    }
    echo "└─────────────────────────────────────────────────────────────────────────┘\n";
}

function check(string $name, bool $condition, string $detail = ''): bool {
    if ($condition) {
        echo "  ✅ {$name}";
        if ($detail) echo " ({$detail})";
        echo "\n";
        return true;
    } else {
        echo "  ❌ {$name}";
        if ($detail) echo " ({$detail})";
        echo "\n";
        return false;
    }
}

function info(string $message): void {
    echo "  ℹ️  {$message}\n";
}

function warn(string $message): void {
    echo "  ⚠️  {$message}\n";
}

$errors = 0;

// ============================================================================
// ÉTAPE 0: VÉRIFICATION DE L'ENVIRONNEMENT
// ============================================================================

step("ÉTAPE 0: Vérification de l'environnement");

$r = apiCall('GET', '/api/v1/ping.php');
if (!check("API accessible", $r['code'] === 200)) {
    echo "\n❌ ERREUR FATALE: L'API n'est pas accessible sur {$baseUrl}\n";
    echo "   Vérifiez que le serveur est démarré: php -S 0.0.0.0:8080 -t public\n\n";
    exit(1);
}

$r = apiCall('GET', '/api/v1/admin_system_status.php');
check("Statut système OK", $r['code'] === 200);

// ============================================================================
// ÉTAPE 1: PRÉPARATION DE LA SÉANCE
// ============================================================================

step("ÉTAPE 1: Préparation de la séance", "Création ou récupération d'une séance de test");

// Chercher une séance existante non validée
$r = apiCall('GET', '/api/v1/meetings.php');
if ($r['code'] === 200 && !empty($r['body']['data'])) {
    foreach ($r['body']['data'] as $m) {
        if (empty($m['validated_at']) && ($m['status'] ?? '') !== 'archived') {
            $meetingId = $m['id'];
            info("Séance existante trouvée: {$m['title']} ({$meetingId})");
            break;
        }
    }
}

// Si pas de séance, en créer une
if (!$meetingId) {
    $r = apiCall('POST', '/api/v1/meetings.php', [
        'title' => 'AG de recette - ' . date('Y-m-d H:i'),
        'meeting_type' => 'ag_ordinaire',
        'scheduled_at' => date('c'),
        'location' => 'Salle de test',
        'description' => 'Séance créée automatiquement pour la recette',
    ]);
    
    if ($r['code'] === 200 || $r['code'] === 201) {
        $meetingId = $r['body']['data']['id'] ?? $r['body']['id'] ?? null;
        check("Création de la séance", $meetingId !== null, $meetingId);
    } else {
        warn("Impossible de créer une séance: " . ($r['body']['error'] ?? 'erreur'));
        $errors++;
    }
}

if (!$meetingId) {
    echo "\n❌ ERREUR: Aucune séance disponible pour la recette.\n";
    exit(1);
}

// Récupérer les membres
$r = apiCall('GET', '/api/v1/members.php');
if ($r['code'] === 200) {
    $members = $r['body']['data'] ?? $r['body']['members'] ?? [];
    $memberIds = array_column($members, 'id');
    check("Membres disponibles", count($memberIds) > 0, count($memberIds) . " membres");
} else {
    warn("Impossible de récupérer les membres");
    $errors++;
}

// ============================================================================
// ÉTAPE 1.1: VÉRIFICATION DES PRÉSENCES
// ============================================================================

step("ÉTAPE 1.1: Vérification des présences");

$r = apiCall('GET', "/api/v1/attendances.php?meeting_id={$meetingId}");
$attendances = $r['body']['data'] ?? [];

if (count($attendances) === 0 && count($memberIds) > 0) {
    // Marquer quelques membres comme présents
    $toMark = array_slice($memberIds, 0, min(5, count($memberIds)));
    $r = apiCall('POST', '/api/v1/attendances_bulk.php', [
        'meeting_id' => $meetingId,
        'status' => 'present',
        'member_ids' => $toMark,
    ]);
    check("Marquage des présences", $r['code'] === 200, count($toMark) . " membres");
}

$r = apiCall('GET', "/api/v1/quorum_status.php?meeting_id={$meetingId}");
if ($r['code'] === 200) {
    $quorum = $r['body']['data'] ?? $r['body'];
    $ratio = round(($quorum['ratio'] ?? 0) * 100);
    $met = $quorum['met'] ?? false;
    check("Quorum calculé", true, "{$ratio}% - " . ($met ? "ATTEINT" : "non atteint"));
}

// ============================================================================
// ÉTAPE 1.2: PROCURATIONS
// ============================================================================

step("ÉTAPE 1.2: Gestion des procurations");

$r = apiCall('GET', "/api/v1/proxies.php?meeting_id={$meetingId}");
$proxies = $r['body']['data'] ?? $r['body']['proxies'] ?? [];
info(count($proxies) . " procuration(s) existante(s)");

// ============================================================================
// ÉTAPE 2: RÉSOLUTIONS
// ============================================================================

step("ÉTAPE 2: Préparation des résolutions");

$r = apiCall('GET', "/api/v1/motions_for_meeting.php?meeting_id={$meetingId}");
$motions = $r['body']['items'] ?? $r['body']['motions'] ?? $r['body']['data'] ?? [];

if (count($motions) === 0) {
    // Créer des résolutions de test
    $motionTitles = [
        'Approbation des comptes 2024',
        'Budget prévisionnel 2025',
        'Renouvellement du conseil',
    ];
    
    foreach ($motionTitles as $title) {
        $r = apiCall('POST', '/api/v1/motions.php', [
            'meeting_id' => $meetingId,
            'title' => $title,
            'description' => "Résolution de test: {$title}",
        ]);
        if ($r['code'] === 200 || $r['code'] === 201) {
            $motionIds[] = $r['body']['data']['id'] ?? $r['body']['id'] ?? null;
        }
    }
    check("Création des résolutions", count($motionIds) > 0, count($motionIds) . " résolutions");
} else {
    $motionIds = array_column($motions, 'id');
    check("Résolutions existantes", true, count($motionIds) . " résolutions");
}

// ============================================================================
// ÉTAPE 2.1: VOTE ÉLECTRONIQUE - RÉSOLUTION 1
// ============================================================================

step("ÉTAPE 2.1: Vote électronique - Résolution 1");

if (!empty($motionIds[0])) {
    $motionId = $motionIds[0];
    
    // Ouvrir le vote
    $r = apiCall('POST', '/api/v1/motions_open.php', [
        'meeting_id' => $meetingId,
        'motion_id' => $motionId,
    ]);
    
    if ($r['code'] === 200) {
        check("Ouverture du vote", true);
        
        // Récupérer les tokens
        $r = apiCall('GET', "/api/v1/current_motion.php?meeting_id={$meetingId}");
        info("Motion ouverte, tokens générés");
        
        // Simuler quelques votes
        sleep(1); // Attendre un peu
        
        // Clôturer le vote
        $r = apiCall('POST', '/api/v1/motions_close.php', [
            'meeting_id' => $meetingId,
            'motion_id' => $motionId,
        ]);
        check("Clôture du vote", $r['code'] === 200);
        
        // Vérifier les résultats
        $r = apiCall('GET', "/api/v1/ballots_result.php?motion_id={$motionId}");
        if ($r['code'] === 200) {
            $result = $r['body']['data'] ?? $r['body'];
            $decision = $result['decision']['status'] ?? '—';
            check("Résultat calculé", true, "Décision: {$decision}");
        }
    } else {
        warn("Impossible d'ouvrir le vote: " . ($r['body']['error'] ?? 'erreur'));
        $errors++;
    }
}

// ============================================================================
// ÉTAPE 3: CONTRÔLES ET ANOMALIES
// ============================================================================

step("ÉTAPE 3: Contrôles et anomalies (Trust)");

$r = apiCall('GET', "/api/v1/trust_checks.php?meeting_id={$meetingId}");
if ($r['code'] === 200) {
    $checks = $r['body']['checks'] ?? [];
    $passed = count(array_filter($checks, fn($c) => $c['passed']));
    $failed = count($checks) - $passed;
    check("Contrôles de cohérence", true, "{$passed} OK, {$failed} KO");
}

$r = apiCall('GET', "/api/v1/trust_anomalies.php?meeting_id={$meetingId}");
if ($r['code'] === 200) {
    $anomalies = $r['body']['anomalies'] ?? [];
    $count = count($anomalies);
    check("Détection anomalies", true, "{$count} anomalie(s) détectée(s)");
}

// ============================================================================
// ÉTAPE 4: AUDIT
// ============================================================================

step("ÉTAPE 4: Journal d'audit");

$r = apiCall('GET', "/api/v1/audit_log.php?meeting_id={$meetingId}&limit=10");
if ($r['code'] === 200) {
    $events = $r['body']['events'] ?? [];
    check("Événements d'audit", count($events) > 0, count($events) . " événements");
}

// ============================================================================
// ÉTAPE 5: VÉRIFICATION PRÉ-VALIDATION
// ============================================================================

step("ÉTAPE 5: Vérification pré-validation");

$r = apiCall('GET', "/api/v1/meeting_ready_check.php?meeting_id={$meetingId}");
if ($r['code'] === 200) {
    $ready = $r['body']['data']['ready'] ?? false;
    $checks = $r['body']['data']['checks'] ?? [];
    
    check("Ready-check exécuté", true);
    
    foreach ($checks as $c) {
        $icon = $c['passed'] ? '✓' : '✗';
        info("{$icon} {$c['label']}");
    }
    
    if ($ready) {
        info("✅ La séance est PRÊTE pour validation");
    } else {
        info("⚠️ La séance n'est PAS PRÊTE (corrigez les points ci-dessus)");
    }
}

// ============================================================================
// RÉSUMÉ FINAL
// ============================================================================

step("RÉSUMÉ DE LA RECETTE");

$r = apiCall('GET', "/api/v1/meeting_summary.php?meeting_id={$meetingId}");
if ($r['code'] === 200) {
    $data = $r['body']['data'] ?? [];
    echo "\n";
    echo "  📊 Statistiques de la séance:\n";
    echo "     • Membres:        {$data['total_members']} total, {$data['present_count']} présents\n";
    echo "     • Résolutions:    {$data['motions_count']} total, {$data['closed_motions_count']} closes\n";
    echo "     • Adoptées:       {$data['adopted_count']}\n";
    echo "     • Rejetées:       {$data['rejected_count']}\n";
    echo "     • Votes:          {$data['ballots_count']} bulletins\n";
    echo "     • Procurations:   {$data['proxies_count']}\n";
}

echo "\n";
echo "╔═══════════════════════════════════════════════════════════════════════════╗\n";
echo "║                        RECETTE TERMINÉE                                   ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($errors === 0) {
    echo "🎉 Tous les tests de recette sont passés avec succès !\n";
    echo "\n";
    echo "📋 Prochaines étapes manuelles:\n";
    echo "   1. Ouvrir /validate.htmx.html?meeting_id={$meetingId}\n";
    echo "   2. Vérifier la checklist et valider la séance\n";
    echo "   3. Télécharger le PV et les exports\n";
    echo "   4. Vérifier le verrouillage post-validation\n";
    echo "\n";
    exit(0);
} else {
    echo "⚠️  {$errors} erreur(s) détectée(s) pendant la recette.\n";
    echo "   Vérifiez les messages ci-dessus et corrigez les problèmes.\n";
    echo "\n";
    exit(1);
}
