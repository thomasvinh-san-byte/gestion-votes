<?php
declare(strict_types=1);

namespace AgVote\Service;

/**
 * URL slug generation service for URL obfuscation.
 *
 * Generates short and opaque identifiers to replace UUIDs
 * in public URLs, improving readability and security.
 */
class UrlSlugService
{
    /**
     * Characters used for base62 encoding.
     * Avoids ambiguous characters (0/O, 1/l/I) and URL special characters.
     */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    /**
     * Default length of generated slugs.
     */
    private const DEFAULT_LENGTH = 8;

    /**
     * Generates a short and opaque slug from a UUID.
     * The slug is deterministic: same UUID = same slug.
     */
    public static function fromUuid(string $uuid): string
    {
        // Remove dashes and convert to bytes
        $hex = str_replace('-', '', $uuid);
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return self::generateRandom();
        }
        return self::encodeBytes($bytes, self::DEFAULT_LENGTH);
    }

    /**
     * Generates a cryptographically secure random slug.
     */
    public static function generateRandom(int $length = self::DEFAULT_LENGTH): string
    {
        $bytes = random_bytes($length);
        return self::encodeBytes($bytes, $length);
    }

    /**
     * Generates a readable slug from a title.
     * Format: "title-slug-xxxx" where xxxx is a unique suffix.
     */
    public static function fromTitle(string $title, string $uuid): string
    {
        // Normalize the title
        $slug = self::normalizeForSlug($title);

        // Add a unique suffix based on the UUID
        $suffix = self::fromUuid($uuid);
        $suffix = substr($suffix, 0, 4);

        // Limit total length
        if (strlen($slug) > 40) {
            $slug = substr($slug, 0, 40);
        }

        return $slug . '-' . strtolower($suffix);
    }

    /**
     * Generates an opaque token for invitations.
     * Shorter and more readable than a 32-character hex.
     */
    public static function generateInvitationToken(): string
    {
        // 12 characters in base62 = ~71 bits of entropy (sufficient for invitations)
        return self::generateRandom(12);
    }

    /**
     * Generates a token for voting URLs.
     * Short format but with hash for verification.
     */
    public static function generateVoteUrlToken(string $meetingId, string $motionId, string $memberId): array
    {
        // Short public token (displayed in URL)
        $publicToken = self::generateRandom(10);

        // Verification token (stored hashed in database)
        $verificationToken = bin2hex(random_bytes(16));

        // Hash for storage
        $hash = hash('sha256', $verificationToken);

        return [
            'url_token' => $publicToken,
            'verification_token' => $verificationToken,
            'token_hash' => $hash,
        ];
    }

    /**
     * Encodes bytes to a base62 string.
     */
    private static function encodeBytes(string $bytes, int $maxLength): string
    {
        $alphabet = self::ALPHABET;
        $base = strlen($alphabet);

        // Convert bytes to integer (big int)
        $num = gmp_import($bytes);

        $result = '';
        while (gmp_cmp($num, 0) > 0) {
            $remainder = gmp_intval(gmp_mod($num, $base));
            $result = $alphabet[$remainder] . $result;
            $num = gmp_div($num, $base);

            if (strlen($result) >= $maxLength) {
                break;
            }
        }

        // Padding if necessary
        while (strlen($result) < $maxLength) {
            $result = $alphabet[0] . $result;
        }

        return substr($result, 0, $maxLength);
    }

    /**
     * Normalizes a string to create a readable slug.
     */
    private static function normalizeForSlug(string $text): string
    {
        // Transliterate accented characters
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($text === false) {
            $text = '';
        }

        // Convert to lowercase
        $text = strtolower($text);

        // Replace non-alphanumeric characters with dashes
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Remove leading/trailing dashes and multiple dashes
        $text = trim($text, '-');
        $text = preg_replace('/-+/', '-', $text);

        return $text ?: 'item';
    }

    /**
     * Checks if a string looks like a UUID.
     */
    public static function isUuid(string $str): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $str) === 1;
    }

    /**
     * Checks if a string looks like a slug.
     */
    public static function isSlug(string $str): bool
    {
        // A slug contains letters/numbers and optionally dashes
        return preg_match('/^[a-zA-Z0-9-]{4,50}$/', $str) === 1 && !self::isUuid($str);
    }
}
