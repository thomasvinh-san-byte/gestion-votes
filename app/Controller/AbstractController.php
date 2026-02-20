<?php
declare(strict_types=1);

namespace AgVote\Controller;

/**
 * Base controller. One job: wrap a method call in standard error handling.
 *
 * Usage from proxy file:
 *   (new SpeechController())->handle('request');
 *
 * The controller method contains pure business logic — no try/catch needed.
 */
abstract class AbstractController
{
    public function handle(string $method): void
    {
        try {
            $this->$method();
        } catch (\InvalidArgumentException $e) {
            api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        } catch (\Throwable $e) {
            error_log(static::class . '::' . $method . ': ' . $e->getMessage());
            api_fail('internal_error', 500);
        }
    }
}
