<?php

declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
$c = new \AgVote\Controller\MeetingsController();
match (api_method()) {
    'GET' => $c->handle('index'),
    'POST' => $c->handle('createMeeting'),
    default => api_fail('method_not_allowed', 405),
};
