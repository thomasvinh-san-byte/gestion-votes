<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\SetupRepository;
use AgVote\View\HtmlView;
use Throwable;

/**
 * Handles the /setup first-run endpoint.
 *
 * This controller does NOT extend AbstractController because it outputs
 * HTML (via HtmlView), not JSON (via api_ok/api_fail).
 *
 * Guard: if any active admin user already exists, redirect to /login.
 * GET:   render the initial setup form.
 * POST:  validate fields, create tenant + admin user, redirect to /login?setup=ok.
 *
 * Security note: no CSRF protection needed — this endpoint is only accessible
 * before any admin account exists, so there is no authenticated session to hijack.
 * The hasAnyAdmin() guard provides sufficient idempotency protection.
 */
final class SetupController {
    private SetupRepository $repo;

    /**
     * @param SetupRepository|null $repo Optional repo injection (for testing).
     */
    public function __construct(?SetupRepository $repo = null) {
        $this->repo = $repo ?? new SetupRepository();
    }

    /**
     * Main entry point dispatched by the router.
     */
    public function setup(): void {
        // Guard: if any admin already exists, redirect to Location: /login
        if ($this->repo->hasAnyAdmin()) {
            $this->redirect('/login');
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $this->handlePost();
        } else {
            $this->renderForm();
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Validate POST fields, create tenant + admin user, and redirect on success.
     * Re-renders the form with errors on validation failure.
     */
    private function handlePost(): void {
        $orgName  = trim((string) ($_POST['organisation_name'] ?? ''));
        $name     = trim((string) ($_POST['admin_name'] ?? ''));
        $email    = trim((string) ($_POST['admin_email'] ?? ''));
        $password = (string) ($_POST['admin_password'] ?? '');
        $confirm  = (string) ($_POST['admin_password_confirm'] ?? '');

        $errors = [];

        // Validate organisation name
        if ($orgName === '') {
            $errors[] = "Le nom de l'organisation est requis.";
        } elseif (strlen($orgName) < 2 || strlen($orgName) > 100) {
            $errors[] = "Le nom de l'organisation doit contenir entre 2 et 100 caracteres.";
        }

        // Validate admin name
        if ($name === '') {
            $errors[] = "Le nom de l'administrateur est requis.";
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = "Le nom de l'administrateur doit contenir entre 2 et 100 caracteres.";
        }

        // Validate email
        if ($email === '') {
            $errors[] = "L'adresse email est requise.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "L'adresse email n'est pas valide.";
        }

        // Validate password
        if ($password === '') {
            $errors[] = "Le mot de passe est requis.";
        } elseif (strlen($password) < 8) {
            $errors[] = "Le mot de passe doit contenir au moins 8 caracteres.";
        } elseif ($password !== $confirm) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        }

        if ($errors !== []) {
            $this->renderForm($errors, [
                'organisation_name' => $orgName,
                'admin_name'        => $name,
                'admin_email'       => $email,
                // Never echo passwords back
            ]);
            return;
        }

        // Hash password and create tenant + admin
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $this->repo->createTenantAndAdmin($orgName, $email, $name, $hash);
        } catch (\PDOException $e) {
            error_log('SetupController::handlePost [DB]: ' . $e->getMessage());
            $this->renderForm(["Erreur lors de la creation du compte. Veuillez reessayer."], [
                'organisation_name' => $orgName,
                'admin_name'        => $name,
                'admin_email'       => $email,
            ]);
            return;
        } catch (Throwable $e) {
            error_log('SetupController::handlePost: ' . $e->getMessage());
            $this->renderForm(["Une erreur inattendue s'est produite. Veuillez reessayer."], [
                'organisation_name' => $orgName,
                'admin_name'        => $name,
                'admin_email'       => $email,
            ]);
            return;
        }

        $this->redirect('/login?setup=ok');
    }

    /**
     * Render the setup form template.
     *
     * @param list<string> $errors Validation errors to display
     * @param array        $old    Previously submitted field values
     */
    private function renderForm(array $errors = [], array $old = []): void {
        HtmlView::render('setup_form', ['errors' => $errors, 'old' => $old]);
    }

    /**
     * Send an HTTP redirect.
     *
     * In production: emits Location header and exits.
     * In tests: throws SetupRedirectException so the test can assert the target.
     *
     * @param string $location Target URL
     * @param int    $code     HTTP status code (default 302)
     * @throws SetupRedirectException always (to be caught by tests or die in production)
     */
    private function redirect(string $location, int $code = 302): never {
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            throw new SetupRedirectException($location, $code);
        }
        http_response_code($code);
        header('Location: ' . $location);
        exit;
    }
}
