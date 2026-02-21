<?php

function cfg(string $key, $default = null) {
    $v = getenv($key);
    return ($v !== false && $v !== '') ? $v : $default;
}

return [
  'app' => [
    'url' => cfg('APP_URL', 'http://localhost:8080'),
  ],

  'db' => [
    'dsn' => sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        cfg('DB_HOST', '127.0.0.1'),
        cfg('DB_PORT', '5432'),
        cfg('DB_DATABASE', 'vote_app'),
    ),
    'user' => cfg('DB_USERNAME', 'vote_app'),
    'pass' => cfg('DB_PASSWORD', 'vote_app_dev_2026'),
  ],

  'cors_allow' => cfg('CORS_ALLOW_ORIGINS', '*'),

  'vote_hash_salt' => cfg('VOTE_HASH_SALT', 'change-me-dev-salt'),

  'smtp' => [
    'host' => cfg('SMTP_HOST', null),
    'port' => (int) cfg('SMTP_PORT', 587),
    'user' => cfg('SMTP_USER', null),
    'pass' => cfg('SMTP_PASS', null),

    'from_email' => cfg('SMTP_FROM_EMAIL', 'no-reply@example.test'),
    'from_name' => cfg('SMTP_FROM_NAME', 'Gestion Votes'),

    // 'starttls' (587), 'ssl' (465), 'none'
    'tls' => cfg('SMTP_TLS', 'starttls'),

    'timeout' => (int) cfg('SMTP_TIMEOUT', 10),
  ],
];
