<?php

declare(strict_types=1);

namespace AgVote\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Base event class for AG-VOTE domain events.
 *
 * All domain events carry a meeting context (meetingId + tenantId)
 * and optional payload data.
 */
class AppEvent extends Event {
    public function __construct(
        public readonly string $meetingId,
        public readonly string $tenantId,
        public readonly array $data = [],
    ) {
    }
}
