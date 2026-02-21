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
    'allow_credentials'  => true,
  ],

  // Database (defaults = dev local PostgreSQL via TCP with dedicated user)
  'db' => [
    'dsn'  => getenv('DB_DSN')  ?: 'pgsql:host=localhost;port=5432;dbname=vote_app',
    'user' => getenv('DB_USER') ?: 'vote_app',
    'pass' => getenv('DB_PASS') ?: '',
  ],

  // API keys by role (MVP)
  'keys' => [
    'operator' => getenv('API_KEY_OPERATOR') ?: '',
    'trust'    => getenv('API_KEY_TRUST')    ?: '',
    'admin'    => getenv('API_KEY_ADMIN')    ?: '',
  ],

  // SMTP (for MailerService)
  'smtp' => [
    'host'       => getenv('MAIL_HOST') ?: '',
    'port'       => (int)(getenv('MAIL_PORT') ?: 587),
    'tls'        => getenv('MAIL_TLS') ?: 'starttls',
    'user'       => getenv('MAIL_USER') ?: '',
    'pass'       => getenv('MAIL_PASS') ?: '',
    'from_email' => getenv('MAIL_FROM') ?: 'noreply@example.com',
    'from_name'  => getenv('MAIL_FROM_NAME') ?: 'AG-VOTE',
    'timeout'    => (int)(getenv('MAIL_TIMEOUT') ?: 10),
  ],

  // Redis
  'redis' => [
    'host'     => getenv('REDIS_HOST') ?: '127.0.0.1',
    'port'     => (int)(getenv('REDIS_PORT') ?: 6379),
    'password' => getenv('REDIS_PASSWORD') ?: '',
    'database' => (int)(getenv('REDIS_DATABASE') ?: 0),
    'prefix'   => getenv('REDIS_PREFIX') ?: 'agvote:',
  ],

  // Application
  'app_url' => getenv('APP_URL') ?: 'http://localhost:8080',
  'app_secret' => getenv('APP_SECRET') ?: 'change-me-in-prod',

  // Email tracking
  'email_tracking_enabled' => getenv('EMAIL_TRACKING_ENABLED') !== '0',

  // Proxies
  'proxy_max_per_receiver' => (int)(getenv('PROXY_MAX_PER_RECEIVER') ?: 3),

  // Tenant (mono-tenant MVP). Override via TENANT_ID if needed.
  'default_tenant_id' => getenv('TENANT_ID') ?: 'aaaaaaaa-1111-2222-3333-444444444444',
];
