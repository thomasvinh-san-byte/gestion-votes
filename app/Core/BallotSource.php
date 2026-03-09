<?php

declare(strict_types=1);

namespace AgVote\Core;

/**
 * Ballot source type constants.
 *
 * Centralises the magic strings used across controllers, repositories
 * and services when dealing with the `ballots.source` column.
 */
final class BallotSource
{
    public const MANUAL     = 'manual';
    public const ELECTRONIC = 'electronic';
    public const TABLET     = 'tablet';
    public const PAPER      = 'paper';
    public const DEGRADED   = 'degraded';

    /** Labels for human-readable display / export. */
    public const LABELS = [
        self::ELECTRONIC => 'Électronique',
        self::MANUAL     => 'Manuel',
        self::PAPER      => 'Papier',
        self::DEGRADED   => 'Mode dégradé',
        self::TABLET     => 'Tablette',
        ''               => 'Non spécifié',
    ];
}
