<?php

declare(strict_types=1);

namespace AgVote\Core\Http;

/**
 * HTTP cache primitive : ETag + Cache-Control + 304 Not Modified short-circuit.
 *
 * Usage in a GET HTMX hot endpoint :
 *
 *     $payload = [...computed data...];
 *     HttpCache::sendOk($payload);   // throws ApiResponseException — never returns
 *
 * Behavior :
 *  - Computes a deterministic ETag from $payload (md5 of JSON serialization).
 *  - If `If-None-Match` request header matches the computed ETag, throws a
 *    304 Not Modified response (empty body, ETag + Cache-Control headers).
 *  - Otherwise, throws a 200 OK response with the standard `['ok' => true,
 *    'data' => $payload]` shape and the same ETag + Cache-Control headers.
 *
 * Notes :
 *  - md5 is used as a cache key, NOT as a security signature — collision risk
 *    on a per-payload basis is negligible for HTTP caching.
 *  - PHP arrays preserve insertion order, so json_encode produces a stable
 *    serialization for the same input.
 *  - For pagination/search query params, the ETag automatically varies because
 *    the payload itself includes those parameters (e.g. limit, offset, total).
 *  - Cache-Control: private — never share between users (multi-tenant).
 *  - Cache-Control: must-revalidate — client must send If-None-Match each time.
 *
 * RFC : 7232 §2.3 (ETag), 7232 §4.1 (304), 7234 §5.2 (Cache-Control).
 */
final class HttpCache {
    /**
     * Compute a deterministic ETag for the given payload.
     *
     * Returned value is double-quoted per RFC 7232 §2.3 ("strong" validator).
     * Format: `"<32 lowercase hex chars>"`.
     */
    public static function etagFor(array $payload): string {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            // Should not happen for normal controller payloads, but be defensive.
            $json = serialize($payload);
        }

        return '"' . md5($json) . '"';
    }

    /**
     * Send a 200 OK response with ETag — or short-circuit to 304 Not Modified
     * if the client supplied a matching `If-None-Match` header.
     *
     * Throws ApiResponseException either way (mirrors api_ok()'s contract).
     * The router's exception handler catches it and calls JsonResponse::send().
     */
    public static function sendOk(array $payload): never {
        $etag = self::etagFor($payload);
        $clientEtag = trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));

        $headers = [
            'ETag' => $etag,
            'Cache-Control' => 'private, must-revalidate',
        ];

        if ($clientEtag !== '' && $clientEtag === $etag) {
            // 304 Not Modified — empty body per RFC 7232 §4.1.
            // JsonResponse::send() skips body emission for status 304.
            throw new ApiResponseException(new JsonResponse(304, [], $headers));
        }

        // 200 OK with the standard api_ok($data) shape.
        throw new ApiResponseException(new JsonResponse(
            200,
            ['ok' => true, 'data' => $payload],
            $headers,
        ));
    }
}
