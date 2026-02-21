<?php
declare(strict_types=1);

/**
 * Bootstrap pour les tests PHPUnit
 */

// Chemin racine du projet
define('PROJECT_ROOT', dirname(__DIR__));

// Autoload Composer si disponible
$autoload = PROJECT_ROOT . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// Définir les constantes de test
if (!defined('APP_SECRET')) {
    define('APP_SECRET', 'test-secret-for-unit-tests-only-32chars!');
}

if (!defined('DEFAULT_TENANT_ID')) {
    define('DEFAULT_TENANT_ID', 'aaaaaaaa-1111-2222-3333-444444444444');
}

// Variables d'environnement de test
putenv('APP_ENV=testing');
putenv('APP_DEBUG=1');
putenv('APP_AUTH_ENABLED=0');

// Stub db() for tests — prevents "Call to undefined function db()" errors
// when repositories are instantiated without explicit PDO injection.
if (!function_exists('db')) {
    function db(): PDO {
        throw new \RuntimeException('No database connection available in test environment. Inject a PDO mock via constructor.');
    }
}

// Use namespaced classes
use AgVote\Core\Security\RateLimiter;

// Configure RateLimiter for tests
RateLimiter::configure([
    'storage_dir' => sys_get_temp_dir() . '/ag-vote-test-ratelimit',
]);
