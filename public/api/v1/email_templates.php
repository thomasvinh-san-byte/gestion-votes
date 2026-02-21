<?php

declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
$c = new \AgVote\Controller\EmailTemplatesController();
match (api_method()) {
    'GET' => $c->handle('list'),
    'POST' => $c->handle('create'),
    'PUT' => $c->handle('update'),
    'DELETE' => $c->handle('delete'),
    default => api_fail('method_not_allowed', 405),
};
