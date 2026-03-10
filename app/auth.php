<?php

// app/auth.php
declare(strict_types=1);

// Auth is handled by AuthMiddleware::requireRole() via the router.
// This file is kept as a security sentinel — see SecurityHardeningTest.
// It must NEVER read API keys from query string parameters.
