<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Http;

use AgVote\Core\Http\ApiResponseException;
use AgVote\Core\Http\HttpCache;
use AgVote\Core\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the HttpCache primitive.
 *
 * Covers:
 *  - etagFor() determinism + format
 *  - etagFor() variation when payload changes
 *  - sendOk() throwing 304 on If-None-Match match
 *  - sendOk() throwing 200 + ETag header otherwise
 *  - JsonResponse::send() skipping body for 304/204
 */
class HttpCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_IF_NONE_MATCH']);
        parent::tearDown();
    }

    public function testEtagForSamePayloadIsDeterministic(): void
    {
        $payload = ['a' => 1, 'b' => 2, 'nested' => ['x' => 'y']];
        $first = HttpCache::etagFor($payload);
        $second = HttpCache::etagFor($payload);

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^"[0-9a-f]{32}"$/', $first);
    }

    public function testEtagForDifferentPayloadDiffers(): void
    {
        $a = HttpCache::etagFor(['x' => 1]);
        $b = HttpCache::etagFor(['x' => 2]);

        $this->assertNotSame($a, $b);
    }

    public function testSendOkThrows304WhenIfNoneMatchMatches(): void
    {
        $payload = ['k' => 'v'];
        $expectedEtag = HttpCache::etagFor($payload);
        $_SERVER['HTTP_IF_NONE_MATCH'] = $expectedEtag;

        try {
            HttpCache::sendOk($payload);
            $this->fail('Expected ApiResponseException with 304');
        } catch (ApiResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(304, $resp->getStatusCode());

            $headers = $resp->getHeaders();
            $this->assertArrayHasKey('ETag', $headers);
            $this->assertSame($expectedEtag, $headers['ETag']);
            $this->assertArrayHasKey('Cache-Control', $headers);
            $this->assertSame('private, must-revalidate', $headers['Cache-Control']);
        }
    }

    public function testSendOkThrows200WhenIfNoneMatchAbsent(): void
    {
        $payload = ['k' => 'v'];
        unset($_SERVER['HTTP_IF_NONE_MATCH']);

        try {
            HttpCache::sendOk($payload);
            $this->fail('Expected ApiResponseException with 200');
        } catch (ApiResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(200, $resp->getStatusCode());

            $body = $resp->getBody();
            $this->assertSame(true, $body['ok']);
            $this->assertSame($payload, $body['data']);

            $headers = $resp->getHeaders();
            $this->assertArrayHasKey('ETag', $headers);
            $this->assertSame(HttpCache::etagFor($payload), $headers['ETag']);
            $this->assertSame('private, must-revalidate', $headers['Cache-Control']);
        }
    }

    public function testSendOkThrows200WhenIfNoneMatchDiffers(): void
    {
        $payload = ['k' => 'v'];
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"stale-etag-from-prior-version"';

        try {
            HttpCache::sendOk($payload);
            $this->fail('Expected ApiResponseException with 200');
        } catch (ApiResponseException $e) {
            $resp = $e->getResponse();
            $this->assertSame(200, $resp->getStatusCode());
            $headers = $resp->getHeaders();
            $this->assertSame(HttpCache::etagFor($payload), $headers['ETag']);
        }
    }

    public function testJsonResponseSendSkipsBodyFor304(): void
    {
        $resp = new JsonResponse(304, [], ['ETag' => '"x"']);

        ob_start();
        $resp->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    public function testJsonResponseSendSkipsBodyFor204(): void
    {
        $resp = new JsonResponse(204, [], []);

        ob_start();
        $resp->send();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}
