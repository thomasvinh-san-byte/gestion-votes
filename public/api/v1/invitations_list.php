<?php

declare(strict_types=1);
require __DIR__ . '/../../../app/api.php';
(new \AgVote\Controller\InvitationsController())->handle('listForMeeting');
