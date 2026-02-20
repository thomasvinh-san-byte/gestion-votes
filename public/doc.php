<?php
declare(strict_types=1);
// Backward-compat stub — delegates to DocController via front controller.
// Parsedown loaded via Composer autoload (included in bootstrap.php).
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/api.php';
(new \AgVote\Controller\DocController())->view();
