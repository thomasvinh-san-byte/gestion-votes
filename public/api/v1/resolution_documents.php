<?php

declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
$c = new \AgVote\Controller\ResolutionDocumentController();
match (api_method()) {
    'GET' => $c->handle('listForMotion'),
    'POST' => $c->handle('upload'),
    'DELETE' => $c->handle('delete'),
    default => api_fail('method_not_allowed', 405),
};
