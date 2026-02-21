<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Base controller. One job: wrap a method call in standard error handling.
 *
 * Usage from proxy file:
 *   (new SpeechController())->handle('request');
 *
 * The controller method contains pure business logic — no try/catch needed.
 * The $request property is available for controllers that adopt the new
 * Request object pattern (opt-in, not mandatory).
 */
abstract class AbstractController {
    protected Request $request;

    public function __construct() {
        $this->request = new Request();
    }

    public function handle(string $method): void {
        try {
            $this->$method();
        } catch (\AgVote\Core\Http\ApiResponseException $e) {
            throw $e; // Not an error — propagate to Router for sending
        } catch (InvalidArgumentException $e) {
            api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log(static::class . '::' . $method . ': ' . $e->getMessage());
            api_fail('internal_error', 500);
        }
    }

    /**
     * Wraps a block that may call api_ok/api_fail internally.
     * Catches only non-API exceptions, converting them to api_fail.
     * Eliminates the need for manual catch(ApiResponseException){throw} pattern.
     */
    protected static function wrapApiCall(callable $fn, string $errorCode = 'internal_error', int $httpCode = 500): void {
        try {
            $fn();
        } catch (\AgVote\Core\Http\ApiResponseException $e) {
            throw $e;
        } catch (Throwable $e) {
            api_fail($errorCode, $httpCode, ['detail' => $e->getMessage()]);
        }
    }
}
