<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Providers\RepositoryFactory;
use AgVote\Core\Security\SessionHelper;
use AgVote\Repository\UserRepository;
use AgVote\View\HtmlView;

/**
 * AccountController — Handles /account (Mon Compte).
 *
 * Does NOT extend AbstractController (outputs HTML, not JSON).
 * Session authentication is handled by the controller directly.
 *
 * Routes:
 *  GET  /account  — Show profile (name, email, role) and password change form
 *  POST /account  — Submit new password change
 */
final class AccountController {
    private UserRepository $userRepo;

    public function __construct(?UserRepository $userRepo = null) {
        $this->userRepo = $userRepo ?? RepositoryFactory::getInstance()->user();
    }

    /**
     * Main entry point dispatched by the router.
     */
    public function account(): void {
        SessionHelper::start();

        // Require authentication
        if (empty($_SESSION['auth_user'])) {
            $this->redirect('/login?redirect=/account');
        }

        /** @var array $user */
        $user   = $_SESSION['auth_user'];
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $this->handlePasswordChange($user);
        } else {
            HtmlView::render('account_form', [
                'user'    => $user,
                'errors'  => [],
                'success' => false,
            ]);
        }
    }

    // =========================================================================
    // PRIVATE HANDLERS
    // =========================================================================

    /**
     * Handle POST — validate and apply password change.
     */
    private function handlePasswordChange(array $sessionUser): void {
        $currentPassword    = (string) ($_POST['current_password'] ?? '');
        $newPassword        = (string) ($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string) ($_POST['new_password_confirm'] ?? '');

        $errors = [];

        if ($currentPassword === '') {
            $errors[] = 'Le mot de passe actuel est requis.';
        }

        if ($newPassword === '') {
            $errors[] = 'Le nouveau mot de passe est requis.';
        } elseif (($pwErr = \AgVote\Helper\PasswordValidator::validate($newPassword)) !== null) {
            $errors[] = $pwErr;
        } elseif ($newPassword !== $newPasswordConfirm) {
            $errors[] = 'Les mots de passe ne correspondent pas.';
        }

        if ($errors !== []) {
            HtmlView::render('account_form', [
                'user'    => $sessionUser,
                'errors'  => $errors,
                'success' => false,
            ]);
            return;
        }

        // Load fresh user from DB
        $dbUser = $this->userRepo->findByEmailGlobal($sessionUser['email']);

        if ($dbUser === null || !($dbUser['is_active'] ?? false)) {
            $this->redirect('/login');
        }

        // Verify current password
        if (!password_verify($currentPassword, (string) ($dbUser['password_hash'] ?? ''))) {
            HtmlView::render('account_form', [
                'user'    => $sessionUser,
                'errors'  => ['Le mot de passe actuel est incorrect.'],
                'success' => false,
            ]);
            return;
        }

        // Hash new password and persist
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $this->userRepo->setPasswordHash(
            (string) $dbUser['tenant_id'],
            (string) $dbUser['id'],
            $newHash,
        );

        // Audit log
        audit_log(
            'password_changed',
            'user',
            (string) $dbUser['id'],
            ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'],
        );

        HtmlView::render('account_form', [
            'user'    => $sessionUser,
            'errors'  => [],
            'success' => true,
        ]);
    }

    /**
     * Send an HTTP redirect.
     *
     * In tests: throws AccountRedirectException (same pattern as SetupController/PasswordResetController).
     * In production: emits Location header and exits.
     */
    private function redirect(string $location, int $code = 302): never {
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            throw new AccountRedirectException($location, $code);
        }
        http_response_code($code);
        header('Location: ' . $location);
        exit;
    }
}
