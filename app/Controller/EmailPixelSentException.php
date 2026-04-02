<?php

declare(strict_types=1);

namespace AgVote\Controller;

/**
 * Thrown by EmailTrackingController::outputPixel() instead of echo+exit() in test mode.
 *
 * Used instead of echo/exit() to keep outputPixel() testable without
 * sending binary output or terminating the test process.
 */
final class EmailPixelSentException extends \RuntimeException {
    public function __construct() {
        parent::__construct('Pixel GIF sent');
    }
}
