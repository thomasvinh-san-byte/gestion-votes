<?php

declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
$c = new \AgVote\Controller\AgendaController();
match (api_method()) {
    'GET' => $c->handle('listForMeeting'),
    'POST' => $c->handle('create'),
    default => api_fail('method_not_allowed', 405),
};
