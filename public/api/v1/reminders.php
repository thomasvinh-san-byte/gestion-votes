<?php
declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
$c = new \AgVote\Controller\ReminderController();
match (api_method()) {
    'GET' => $c->handle('listForMeeting'),
    'POST' => $c->handle('upsert'),
    'DELETE' => $c->handle('delete'),
    default => api_fail('method_not_allowed', 405),
};
