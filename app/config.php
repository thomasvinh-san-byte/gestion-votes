<?php
// app/config.php
declare(strict_types=1);

$APP_ENV   = getenv('APP_ENV') ?: 'dev';   // dev|prod
$APP_DEBUG = getenv('APP_DEBUG') === '1';  // 1 => debug

return [
  'env'   => $APP_ENV,
  'debug' => $APP_DEBUG,

  // CORS
  'cors' => [
    'allowed_origins'    => array_filter(array_map('trim', explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: ''))),
    'allow_credentials'  => false,
  ],

  // Database
  'db' => [
    'dsn'  => getenv('DB_DSN')  ?: '',
    'user' => getenv('DB_USER') ?: '',
    'pass' => getenv('DB_PASS') ?: '',
  ],

  // API keys by role (MVP)
  'keys' => [
    'operator' => getenv('API_KEY_OPERATOR') ?: '',
    'trust'    => getenv('API_KEY_TRUST')    ?: '',
    'admin'    => getenv('API_KEY_ADMIN')    ?: '',
  ],

  // Tenant (mono-tenant MVP). Override via TENANT_ID if needed.
  'default_tenant_id' => getenv('TENANT_ID') ?: 'aaaaaaaa-1111-2222-3333-444444444444',
];
