<?php

declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
// Authenticated PDF serve endpoint — routed via app/routes.php
$c = new \AgVote\Controller\ResolutionDocumentController();
$c->handle('serve');
