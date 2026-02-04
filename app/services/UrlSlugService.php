<?php
declare(strict_types=1);

namespace AgVote\Service;

/**
 * Service de génération de slugs pour obfuscation des URLs.
 *
 * Génère des identifiants courts et opaques pour remplacer les UUIDs
 * dans les URLs publiques, améliorant la lisibilité et la sécurité.
 */
class UrlSlugService
{
    /**
     * Caractères utilisés pour l'encodage base62.
     * Évite les caractères ambigus (0/O, 1/l/I) et les caractères spéciaux URL.
     */
    private const ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';

    /**
     * Longueur par défaut des slugs générés.
     */
    private const DEFAULT_LENGTH = 8;

    /**
     * Génère un slug court et opaque à partir d'un UUID.
     * Le slug est déterministe : même UUID = même slug.
     */
    public static function fromUuid(string $uuid): string
    {
        // Supprimer les tirets et convertir en bytes
        $hex = str_replace('-', '', $uuid);
        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return self::generateRandom();
        }
        return self::encodeBytes($bytes, self::DEFAULT_LENGTH);
    }

    /**
     * Génère un slug aléatoire cryptographiquement sécurisé.
     */
    public static function generateRandom(int $length = self::DEFAULT_LENGTH): string
    {
        $bytes = random_bytes($length);
        return self::encodeBytes($bytes, $length);
    }

    /**
     * Génère un slug lisible à partir d'un titre.
     * Format: "titre-slug-xxxx" où xxxx est un suffixe unique.
     */
    public static function fromTitle(string $title, string $uuid): string
    {
        // Normaliser le titre
        $slug = self::normalizeForSlug($title);

        // Ajouter un suffixe unique basé sur l'UUID
        $suffix = self::fromUuid($uuid);
        $suffix = substr($suffix, 0, 4);

        // Limiter la longueur totale
        if (strlen($slug) > 40) {
            $slug = substr($slug, 0, 40);
        }

        return $slug . '-' . strtolower($suffix);
    }

    /**
     * Génère un token opaque pour les invitations.
     * Plus court et lisible qu'un hex de 32 caractères.
     */
    public static function generateInvitationToken(): string
    {
        // 12 caractères en base62 = ~71 bits d'entropie (suffisant pour invitations)
        return self::generateRandom(12);
    }

    /**
     * Génère un token pour les URLs de vote.
     * Format court mais avec hash pour vérification.
     */
    public static function generateVoteUrlToken(string $meetingId, string $motionId, string $memberId): array
    {
        // Token public court (affiché dans l'URL)
        $publicToken = self::generateRandom(10);

        // Token de vérification (stocké hashé en base)
        $verificationToken = bin2hex(random_bytes(16));

        // Hash pour stockage
        $hash = hash('sha256', $verificationToken);

        return [
            'url_token' => $publicToken,
            'verification_token' => $verificationToken,
            'token_hash' => $hash,
        ];
    }

    /**
     * Encode des bytes en chaîne base62.
     */
    private static function encodeBytes(string $bytes, int $maxLength): string
    {
        $alphabet = self::ALPHABET;
        $base = strlen($alphabet);

        // Convertir les bytes en nombre entier (big int)
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

        // Padding si nécessaire
        while (strlen($result) < $maxLength) {
            $result = $alphabet[0] . $result;
        }

        return substr($result, 0, $maxLength);
    }

    /**
     * Normalise une chaîne pour créer un slug lisible.
     */
    private static function normalizeForSlug(string $text): string
    {
        // Translittération des accents
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($text === false) {
            $text = '';
        }

        // Convertir en minuscules
        $text = strtolower($text);

        // Remplacer les caractères non-alphanumériques par des tirets
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);

        // Supprimer les tirets en début/fin et les tirets multiples
        $text = trim($text, '-');
        $text = preg_replace('/-+/', '-', $text);

        return $text ?: 'item';
    }

    /**
     * Vérifie si une chaîne ressemble à un UUID.
     */
    public static function isUuid(string $str): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $str) === 1;
    }

    /**
     * Vérifie si une chaîne ressemble à un slug.
     */
    public static function isSlug(string $str): bool
    {
        // Un slug contient des lettres/chiffres et éventuellement des tirets
        return preg_match('/^[a-zA-Z0-9-]{4,50}$/', $str) === 1 && !self::isUuid($str);
    }
}
