#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * generate_api_keys.php - Génère des API Keys pour le développement/test
 * 
 * Usage: php generate_api_keys.php [--sql] [--env]
 */

// Configuration
$appSecret = getenv('APP_SECRET') ?: 'your-app-secret-here-change-in-production';
$tenantId = getenv('TENANT_ID') ?: 'aaaaaaaa-1111-2222-3333-444444444444';

// Rôles à générer
$roles = [
    'admin' => [
        'name' => 'Admin Test',
        'email' => 'admin@ag-vote.local',
    ],
    'operator' => [
        'name' => 'Opérateur Test',
        'email' => 'operator@ag-vote.local',
    ],
    'president' => [
        'name' => 'Président Test',
        'email' => 'president@ag-vote.local',
    ],
    'trust' => [
        'name' => 'Trust Test',
        'email' => 'trust@ag-vote.local',
    ],
    'readonly' => [
        'name' => 'Lecture Seule',
        'email' => 'readonly@ag-vote.local',
    ],
];

// Génère les clés
$keys = [];
foreach ($roles as $role => $info) {
    $rawKey = bin2hex(random_bytes(32));
    $hash = hash_hmac('sha256', $rawKey, $appSecret);
    
    $keys[$role] = [
        'key' => $rawKey,
        'hash' => $hash,
        'name' => $info['name'],
        'email' => $info['email'],
    ];
}

// Options
$outputSql = in_array('--sql', $argv);
$outputEnv = in_array('--env', $argv);

// Sortie par défaut : affichage simple
if (!$outputSql && !$outputEnv) {
    echo "\n";
    echo "╔══════════════════════════════════════════════════════════════════════════╗\n";
    echo "║                        API KEYS DE DÉVELOPPEMENT                          ║\n";
    echo "╠══════════════════════════════════════════════════════════════════════════╣\n";
    echo "║ ⚠️  NE PAS UTILISER EN PRODUCTION - UNIQUEMENT POUR LE DEV/TEST          ║\n";
    echo "╚══════════════════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "APP_SECRET utilisé: " . substr($appSecret, 0, 20) . "...\n";
    echo "Tenant ID: $tenantId\n";
    echo "\n";
    
    foreach ($keys as $role => $data) {
        echo "┌─────────────────────────────────────────────────────────────────────────┐\n";
        echo "│ RÔLE: " . strtoupper($role) . str_repeat(' ', 65 - strlen($role)) . "│\n";
        echo "├─────────────────────────────────────────────────────────────────────────┤\n";
        echo "│ Nom   : {$data['name']}" . str_repeat(' ', 63 - strlen($data['name'])) . "│\n";
        echo "│ Email : {$data['email']}" . str_repeat(' ', 63 - strlen($data['email'])) . "│\n";
        echo "│ Key   : {$data['key']}" . str_repeat(' ', 63 - strlen($data['key'])) . "│\n";
        echo "│ Hash  : {$data['hash']}" . str_repeat(' ', 63 - strlen($data['hash'])) . "│\n";
        echo "└─────────────────────────────────────────────────────────────────────────┘\n";
        echo "\n";
    }
    
    echo "═══════════════════════════════════════════════════════════════════════════\n";
    echo "Pour générer le SQL d'insertion:  php generate_api_keys.php --sql\n";
    echo "Pour générer les variables .env:   php generate_api_keys.php --env\n";
    echo "═══════════════════════════════════════════════════════════════════════════\n";
}

// Sortie SQL
if ($outputSql) {
    echo "-- API Keys de test pour AG-VOTE\n";
    echo "-- Généré le " . date('Y-m-d H:i:s') . "\n";
    echo "-- ⚠️  NE PAS UTILISER EN PRODUCTION\n\n";
    echo "-- Tenant: $tenantId\n";
    echo "-- APP_SECRET: " . substr($appSecret, 0, 20) . "...\n\n";
    
    foreach ($keys as $role => $data) {
        $userId = sprintf('%s-0000-0000-0000-%012d', substr(md5($role), 0, 8), rand(1, 999999999999));
        
        echo "-- Utilisateur: {$data['name']} ({$role})\n";
        echo "INSERT INTO users (id, tenant_id, email, name, role, api_key_hash, is_active, created_at, updated_at)\n";
        echo "VALUES (\n";
        echo "  '$userId',\n";
        echo "  '$tenantId',\n";
        echo "  '{$data['email']}',\n";
        echo "  '{$data['name']}',\n";
        echo "  '$role',\n";
        echo "  '{$data['hash']}',\n";
        echo "  true,\n";
        echo "  NOW(),\n";
        echo "  NOW()\n";
        echo ")\n";
        echo "ON CONFLICT (email) DO UPDATE SET\n";
        echo "  api_key_hash = EXCLUDED.api_key_hash,\n";
        echo "  role = EXCLUDED.role,\n";
        echo "  updated_at = NOW();\n\n";
    }
    
    echo "-- Clés à utiliser (header X-Api-Key):\n";
    foreach ($keys as $role => $data) {
        echo "-- $role: {$data['key']}\n";
    }
}

// Sortie .env
if ($outputEnv) {
    echo "# API Keys de test pour AG-VOTE\n";
    echo "# Généré le " . date('Y-m-d H:i:s') . "\n";
    echo "# ⚠️  NE PAS UTILISER EN PRODUCTION\n\n";
    
    echo "# À ajouter dans votre fichier .env\n\n";
    
    foreach ($keys as $role => $data) {
        $envKey = 'API_KEY_' . strtoupper($role);
        echo "# {$data['name']}\n";
        echo "$envKey={$data['key']}\n\n";
    }
    
    echo "# Note: Ces clés ne fonctionnent qu'avec le APP_SECRET configuré\n";
    echo "# lors de la génération. Assurez-vous que APP_SECRET est identique.\n";
}
