<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Http\Request;
use AgVote\Core\Security\CsrfMiddleware;
use AgVote\Repository\SetupRepository;
use AgVote\View\HtmlView;
use Throwable;

/**
 * Handles the /setup first-run endpoint.
 *
 * This controller does NOT extend AbstractController because it outputs
 * HTML (via HtmlView), not JSON (via api_ok/api_fail).
 *
 * Guard: if any active admin user already exists, return an opaque 404.
 *        We deliberately do NOT redirect to /login on a configured instance —
 *        a 302 leaks "instance is initialized" to unauthenticated probes,
 *        whereas 404 is indistinguishable from "this URL never existed".
 * GET:   render the initial setup form (with CSRF token embedded).
 * POST:  validate CSRF, validate fields, create tenant + admin user,
 *        redirect to /login?setup=ok.
 *
 * Security posture (defense-in-depth, hardened 2026-04-29):
 *   1. Opaque 404 once any admin exists — no info leak about init state.
 *   2. CSRF synchronizer token required on POST — even the very first admin
 *      creation cannot be triggered cross-site by a hosted form during the
 *      pre-init deployment window.
 *   3. hasAnyAdmin() guard remains as the idempotency lock.
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
        // Guard: if any admin already exists, serve an opaque 404.
        // No redirect, no body — do not leak that this instance is configured.
        if ($this->repo->hasAnyAdmin()) {
            $this->notFound();
        }

        // Initialise the CSRF token in the session so the form can embed it
        // and so handlePost() has a token to compare against.
        CsrfMiddleware::init();

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
        // CSRF check first, before reading any user-controlled input.
        // Non-strict mode: re-render the form with a French banner instead
        // of throwing a JSON ApiResponseException (this is an HTML endpoint).
        if (!CsrfMiddleware::validate(false)) {
            $this->renderForm([
                'Jeton de sécurité invalide. Rechargez la page et réessayez.',
            ]);
            return;
        }

        $request = new Request();
        $orgName  = trim((string) $request->body('organisation_name', ''));
        $name     = trim((string) $request->body('admin_name', ''));
        $email    = trim((string) $request->body('admin_email', ''));
        $password = (string) $request->body('admin_password', '');
        $confirm  = (string) $request->body('admin_password_confirm', '');

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
        } elseif (($pwErr = \AgVote\Helper\PasswordValidator::validate($password)) !== null) {
            $errors[] = $pwErr;
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

    /**
     * Serve an opaque 404 — no body, no redirect.
     *
     * Used when any admin already exists, so probes cannot distinguish
     * a configured instance from a non-existent route.
     *
     * In tests: throws SetupRedirectException with status 404 so the test
     * can assert the response (we reuse the existing exception type rather
     * than introducing a new one — its name is historical).
     *
     * @throws SetupRedirectException in PHPUnit context (status 404)
     */
    private function notFound(): never {
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            throw new SetupRedirectException('/404', 404);
        }
        http_response_code(404);
        exit;
    }
}
