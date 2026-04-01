<?php

declare(strict_types=1);

namespace AgVote\Controller;

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
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Token may come from query string (GET) or POST body (form submit)
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

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
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];

        if ($password === '') {
            $errors[] = 'Le mot de passe est requis.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caracteres.';
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
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Rate limit: max 5 requests per 5 minutes per IP
        if (RateLimiter::isLimited('password_reset', $ip, 5, 300)) {
            $this->renderRequestForm(
                errors: ['Trop de tentatives. Veuillez patienter avant de reessayer.'],
            );
            return;
        }
        RateLimiter::check('password_reset', $ip, 5, 300, false);

        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // Show success anyway — no enumeration on invalid email either
            HtmlView::render('reset_request_form', [
                'errors'  => [],
                'success' => true,
            ]);
            return;
        }

        // Always succeeds silently — service handles non-existent / inactive users
        $this->service->requestReset($email);

        HtmlView::render('reset_request_form', [
            'errors'  => [],
            'success' => true,
        ]);
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
