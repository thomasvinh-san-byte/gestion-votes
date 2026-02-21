<?php

declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
$c = new \AgVote\Controller\MembersController();
match (api_method()) {
    'GET' => $c->handle('index'),
    'POST' => $c->handle('create'),
    'PATCH', 'PUT' => $c->handle('updateMember'),
    'DELETE' => $c->handle('delete'),
    default => api_fail('method_not_allowed', 405),
};
