<?php

declare(strict_types=1);

namespace AgVote\Core\Http;

/**
 * RED skeleton — intentionally returns wrong values so tests fail for the
 * right reason during the RED phase. Replaced in GREEN phase.
 */
final class HttpCache {
    public static function etagFor(array $payload): string {
        return '';
    }

    public static function sendOk(array $payload): never {
        throw new \RuntimeException('not implemented');
    }
}
