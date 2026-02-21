<?php

declare(strict_types=1);

namespace AgVote\Event\Listener;

use AgVote\Event\AppEvent;
use AgVote\Event\VoteEvents;
use AgVote\WebSocket\EventBroadcaster;

/**
 * Listens to domain events and broadcasts them via WebSocket.
 *
 * This decouples controllers from the WebSocket layer:
 * controllers dispatch events, this listener handles the broadcast.
 */
final class WebSocketListener {
    public function onVoteCast(AppEvent $event): void {
        EventBroadcaster::voteCast(
            $event->meetingId,
            $event->data['motion_id'] ?? '',
            $event->data['tally'] ?? [],
        );
    }

    public function onVoteUpdated(AppEvent $event): void {
        EventBroadcaster::voteUpdated(
            $event->meetingId,
            $event->data['motion_id'] ?? '',
            $event->data['tally'] ?? [],
        );
    }

    public function onMotionOpened(AppEvent $event): void {
        EventBroadcaster::motionOpened(
            $event->meetingId,
            $event->data['motion_id'] ?? '',
            $event->data['motion'] ?? [],
        );
    }

    public function onMotionClosed(AppEvent $event): void {
        EventBroadcaster::motionClosed(
            $event->meetingId,
            $event->data['motion_id'] ?? '',
            $event->data['results'] ?? [],
        );
    }

    public function onMotionUpdated(AppEvent $event): void {
        EventBroadcaster::motionUpdated(
            $event->meetingId,
            $event->data['motion_id'] ?? '',
            $event->data['changes'] ?? [],
        );
    }

    public function onAttendanceUpdated(AppEvent $event): void {
        EventBroadcaster::attendanceUpdated(
            $event->meetingId,
            $event->data['stats'] ?? [],
        );
    }

    public function onQuorumUpdated(AppEvent $event): void {
        EventBroadcaster::quorumUpdated(
            $event->meetingId,
            $event->data['quorum'] ?? [],
        );
    }

    public function onMeetingStatusChanged(AppEvent $event): void {
        EventBroadcaster::meetingStatusChanged(
            $event->meetingId,
            $event->tenantId,
            $event->data['new_status'] ?? '',
            $event->data['old_status'] ?? '',
        );
    }

    public function onSpeechQueueUpdated(AppEvent $event): void {
        EventBroadcaster::speechQueueUpdated(
            $event->meetingId,
            $event->data['queue'] ?? [],
        );
    }

    /**
     * Register all listeners on the given dispatcher.
     */
    public static function subscribe(\Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher): void {
        $listener = new self();

        $dispatcher->addListener(VoteEvents::VOTE_CAST, [$listener, 'onVoteCast']);
        $dispatcher->addListener(VoteEvents::VOTE_UPDATED, [$listener, 'onVoteUpdated']);
        $dispatcher->addListener(VoteEvents::MOTION_OPENED, [$listener, 'onMotionOpened']);
        $dispatcher->addListener(VoteEvents::MOTION_CLOSED, [$listener, 'onMotionClosed']);
        $dispatcher->addListener(VoteEvents::MOTION_UPDATED, [$listener, 'onMotionUpdated']);
        $dispatcher->addListener(VoteEvents::ATTENDANCE_UPDATED, [$listener, 'onAttendanceUpdated']);
        $dispatcher->addListener(VoteEvents::QUORUM_UPDATED, [$listener, 'onQuorumUpdated']);
        $dispatcher->addListener(VoteEvents::MEETING_STATUS_CHANGED, [$listener, 'onMeetingStatusChanged']);
        $dispatcher->addListener(VoteEvents::SPEECH_QUEUE_UPDATED, [$listener, 'onSpeechQueueUpdated']);
    }
}
