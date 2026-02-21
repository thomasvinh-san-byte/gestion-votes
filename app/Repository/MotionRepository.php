<?php

declare(strict_types=1);

namespace AgVote\Repository;

use AgVote\Repository\Traits\MotionAnalyticsTrait;
use AgVote\Repository\Traits\MotionFinderTrait;
use AgVote\Repository\Traits\MotionListTrait;
use AgVote\Repository\Traits\MotionWriterTrait;

/**
 * Data access for motions (resolutions).
 *
 * Centralizes all SQL queries for the motions table + associated ballots.
 *
 * Methods are organized into logical traits:
 * - MotionFinderTrait:    Single-record lookups (findBy*, findWith*, findFor*)
 * - MotionListTrait:      Multi-record queries (list*)
 * - MotionWriterTrait:    Mutations (create, update, delete, mark*, reset*)
 * - MotionAnalyticsTrait: Counting, tallying, stats (count*, getTally, listStats*)
 */
class MotionRepository extends AbstractRepository {
    use MotionFinderTrait;
    use MotionListTrait;
    use MotionWriterTrait;
    use MotionAnalyticsTrait;
}
