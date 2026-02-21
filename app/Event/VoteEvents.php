<?php

declare(strict_types=1);

namespace AgVote\Event;

/**
 * Event constants for vote-related domain events.
 */
final class VoteEvents {
    public const VOTE_CAST = 'vote.cast';
    public const VOTE_UPDATED = 'vote.updated';
    public const MOTION_OPENED = 'motion.opened';
    public const MOTION_CLOSED = 'motion.closed';
    public const MOTION_UPDATED = 'motion.updated';
    public const ATTENDANCE_UPDATED = 'attendance.updated';
    public const QUORUM_UPDATED = 'quorum.updated';
    public const MEETING_STATUS_CHANGED = 'meeting.status_changed';
    public const SPEECH_QUEUE_UPDATED = 'speech.queue_updated';
}
