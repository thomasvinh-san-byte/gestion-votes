<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Http\Request;
use AgVote\Core\Security\RateLimiter;
use AgVote\Service\PasswordResetService;
use AgVote\View\HtmlView;

/**
 * PasswordResetController — Handles /reset-password.
 *
 * Does NOT extend AbstractController (outputs HTML, not JSON).
 *
 * Routes:
 *  GET  /reset-password               — Show request-a-reset-link form
 *  POST /reset-password               — Submit email, queue reset link
 *  GET  /reset-password?token=VALID   — Show new-password form
 *  GET  /reset-password?token=INVALID — Show request form with error
 *  POST /reset-password?token=...     — Submit new password
 */
final class PasswordResetController {
    private PasswordResetService $service;

    public function __construct(?PasswordResetService $service = null) {
        $this->service = $service ?? new PasswordResetService();
    }

    /**
     * Main entry point dispatched by the router.
     */
    public function resetPassword(): void {
        $request = new Request();
        $method = strtoupper($request->server('REQUEST_METHOD') ?? 'GET');

        // Token may come from query string (GET) or POST body (form submit)
        $token = trim((string) ($request->query('token', '') ?: $request->body('token', '')));

        if ($token !== '') {
            // Token present — either show new-password form or process it
            if ($method === 'POST') {
                $this->handleNewPasswordPost($token);
            } else {
                $this->handleTokenGet($token);
            }
        } else {
            // No token — either show request form or process email submission
            if ($method === 'POST') {
                $this->handleRequestPost();
            } else {
                $this->renderRequestForm();
            }
        }
    }

    // =========================================================================
    // PRIVATE HANDLERS
    // =========================================================================

    /**
     * GET /reset-password?token=... — Validate token, show new-password form or error.
     */
    private function handleTokenGet(string $rawToken): void {
        $row = $this->service->validateToken($rawToken);

        if ($row === null) {
            $this->renderRequestForm(
                errors: ['Ce lien de reinitialisation est invalide ou a expire.'],
            );
            return;
        }

        HtmlView::render('reset_newpassword_form', ['token' => $rawToken]);
    }

    /**
     * POST /reset-password?token=... — Validate fields and reset password.
     */
    private function handleNewPasswordPost(string $rawToken): void {
        $request = new Request();
        $password = (string) $request->body('password', '');
        $confirm  = (string) $request->body('password_confirm', '');

        $errors = [];

        if ($password === '') {
            $errors[] = 'Le mot de passe est requis.';
        } elseif (($pwErr = \AgVote\Helper\PasswordValidator::validate($password)) !== null) {
            $errors[] = $pwErr;
        } elseif ($password !== $confirm) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            HtmlView::render('reset_newpassword_form', [
                'token'  => $rawToken,
                'errors' => $errors,
            ]);
            return;
        }

        $result = $this->service->resetPassword($rawToken, $password);

        if (!$result['ok']) {
            $this->renderRequestForm(
                errors: ['Ce lien de reinitialisation est invalide ou a expire.'],
            );
            return;
        }

        HtmlView::render('reset_success');
    }

    /**
     * GET /reset-password — Show the request-a-link form.
     */
    private function renderRequestForm(array $errors = [], bool $success = false): void {
        HtmlView::render('reset_request_form', [
            'errors'  => $errors,
            'success' => $success,
        ]);
    }

    /**
     * POST /reset-password — Process email submission (rate-limited, no enumeration).
     */
    private function handleRequestPost(): void {
        // F02 hardening: real client IP, not a spoofable header.
        $ip = \AgVote\Core\Http\ClientIp::get();
        $startedAt = microtime(true);

        // F12: per-IP rate limit (5 per 5 min) — coarse-grained anti-flood.
        if (RateLimiter::isLimited('password_reset', $ip, 5, 300)) {
            $this->renderRequestForm(
                errors: ['Trop de tentatives. Veuillez patienter avant de reessayer.'],
            );
            return;
        }
        RateLimiter::check('password_reset', $ip, 5, 300, false);

        $request = new Request();
        $email = trim((string) $request->body('email', ''));

        // F12: per-email rate limit (3 per 10 min) — caps the noise generated
        // for any single account regardless of the IP rotation. Hashed
        // identifier so a user's email never appears in Redis keys.
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailKey = hash('sha256', strtolower($email));
            if (RateLimiter::isLimited('password_reset_email', $emailKey, 3, 600)) {
                // Same response shape as success — no enumeration signal.
                $this->constantTimeFinish($startedAt);
                HtmlView::render('reset_request_form', ['errors' => [], 'success' => true]);
                return;
            }
            RateLimiter::check('password_reset_email', $emailKey, 3, 600, false);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Show success anyway — no enumeration on invalid email either.
            $this->constantTimeFinish($startedAt);
            HtmlView::render('reset_request_form', [
                'errors'  => [],
                'success' => true,
            ]);
            return;
        }

        // Always succeeds silently — service handles non-existent / inactive users
        $this->service->requestReset($email);

        $this->constantTimeFinish($startedAt);
        HtmlView::render('reset_request_form', [
            'errors'  => [],
            'success' => true,
        ]);
    }

    /**
     * F12: pad request handling time so rate-limit-rejected, invalid-email,
     * and successful paths all take roughly the same wall-clock duration.
     * Without this, a fast 401-style response leaks "user does not exist"
     * vs "user exists, email queued" via a timing oracle. Floor + jitter to
     * blunt sub-millisecond comparison.
     */
    private function constantTimeFinish(float $startedAt): void {
        $elapsedMs = (microtime(true) - $startedAt) * 1000.0;
        $targetMs = 250 + random_int(0, 80); // 250-330 ms
        if ($elapsedMs < $targetMs) {
            usleep((int) (($targetMs - $elapsedMs) * 1000));
        }
    }

    /**
     * Send an HTTP redirect.
     *
     * In tests: throws PasswordResetRedirectException (same pattern as SetupController).
     * In production: emits Location header and exits.
     */
    private function redirect(string $location, int $code = 302): never {
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            throw new PasswordResetRedirectException($location, $code);
        }
        http_response_code($code);
        header('Location: ' . $location);
        exit;
    }
}
