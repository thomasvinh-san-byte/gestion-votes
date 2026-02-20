<?php
declare(strict_types=1);

namespace AgVote\Core\Http;

/**
 * Exception that carries a ready-to-send JSON response.
 *
 * Thrown by api_ok() / api_fail() instead of calling exit(), enabling:
 *  - proper exception flow through middlewares and AbstractController
 *  - testability (no more exit() in controller code)
 *  - post-controller middleware logic (logging, response transforms)
 *
 * Caught at the Router level and sent via JsonResponse::send().
 */
final class ApiResponseException extends \Exception
{
    public function __construct(
        private readonly JsonResponse $response,
    ) {
        parent::__construct('API response', $response->getStatusCode());
    }

    public function getResponse(): JsonResponse
    {
        return $this->response;
    }
}
