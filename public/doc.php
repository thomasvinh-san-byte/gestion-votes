<?php

declare(strict_types=1);
// Backward-compat stub — delegates to DocController via front controller.
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/api.php';
(new \AgVote\Controller\DocController())->view();
