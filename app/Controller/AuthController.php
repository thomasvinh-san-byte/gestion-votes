<?php
declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Repository\UserRepository;
use AgVote\Repository\MemberRepository;
use AgVote\Core\Security\CsrfMiddleware;

/**
 * Consolidates auth endpoints: login, logout, whoami, csrf, ping.
 */
final class AuthController extends AbstractController
{
    public function login(): void
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            api_fail('method_not_allowed', 405);
        }

        $rawBody = \AgVote\Core\Http\Request::getRawBody();
        $input = json_decode($rawBody, true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $userRepo = new UserRepository();
        $user = null;
        $authMethod = 'unknown';

        // ── Auth by email/password (priority) ──
        $email    = trim((string)($input['email'] ?? ''));
        $password = (string)($input['password'] ?? '');

        if ($email !== '' && $password !== '') {
            $authMethod = 'password';

            $user = $userRepo->findByEmailGlobal($email);

            if (!$user || empty($user['password_hash'])) {
                try {
                    $userRepo->logAuthFailure(
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        $email,
                        'invalid_credentials'
                    );
                } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) { /* best effort */ }
                api_fail('invalid_credentials', 401, ['detail' => 'Email ou mot de passe incorrect.']);
            }

            if (!password_verify($password, $user['password_hash'])) {
                try {
                    $userRepo->logAuthFailure(
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        $email,
                        'wrong_password'
                    );
                } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) { /* best effort */ }
                api_fail('invalid_credentials', 401, ['detail' => 'Email ou mot de passe incorrect.']);
            }

            // Rehash if default algorithm changed
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                try {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $userRepo->setPasswordHash($user['tenant_id'], $user['id'], $newHash);
                } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) { /* best effort */ }
            }

        } else {
            // ── Fallback: API key auth (compat) ──
            $apiKey = trim((string)($input['api_key'] ?? ''));

            if ($apiKey === '') {
                api_fail('missing_credentials', 400, ['detail' => 'Email et mot de passe requis.']);
            }

            $authMethod = 'api_key';
            $hash = hash_hmac('sha256', $apiKey, APP_SECRET);
            $user = $userRepo->findByApiKeyHashGlobal($hash);

            if (!$user) {
                try {
                    $userRepo->logAuthFailure(
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        substr($apiKey, 0, 8) . '...',
                        'invalid_key'
                    );
                } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) { /* best effort */ }
                api_fail('invalid_credentials', 401, ['detail' => 'Identifiants invalides.']);
            }
        }

        // ── Common checks ──
        if (empty($user['is_active'])) {
            api_fail('account_disabled', 403, ['detail' => 'Compte désactivé. Contactez un administrateur.']);
        }

        // ── Create session ──
        if (session_status() === PHP_SESSION_NONE) {
            $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['auth_user'] = [
            'id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'is_active' => $user['is_active'],
            'logged_in_at' => date('c'),
        ];

        $_SESSION['auth_last_activity'] = time();

        audit_log('user_login', 'user', $user['id'], [
            'method' => $authMethod,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        api_ok([
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
            ],
            'session' => true,
        ]);
    }

    public function logout(): void
    {
        api_request('POST');

        CsrfMiddleware::validate();

        $userId = api_current_user_id();
        if ($userId) {
            audit_log('user_logout', 'user', $userId, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        session_destroy();

        \AuthMiddleware::reset();

        api_ok(['logged_out' => true]);
    }

    public function whoami(): void
    {
        $enabled = \AuthMiddleware::isEnabled();
        if (!$enabled) {
            api_ok([
                'auth_enabled' => false,
                'user' => null,
            ]);
        }

        $user = \AuthMiddleware::authenticate();
        if ($user === null) {
            api_fail('missing_or_invalid_api_key', 401, ['auth_enabled' => true]);
        }
        if (!$user['is_active']) {
            api_fail('user_inactive', 401, ['auth_enabled' => true]);
        }

        $meetingRoles = [];
        try {
            $userRepo = new UserRepository();
            $meetingRoles = $userRepo->listActiveMeetingRolesForUser($user['id'], $user['tenant_id']);
        } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) {
            // best effort
        }

        $linkedMember = null;
        try {
            $memberRepo = new MemberRepository();
            $found = $memberRepo->findByUserId($user['id'], $user['tenant_id']);
            if ($found) {
                $linkedMember = [
                    'id' => $found['id'],
                    'full_name' => $found['full_name'],
                    'voting_power' => (float)($found['voting_power'] ?? 1),
                ];
            }
        } catch (\AgVote\Core\Http\ApiResponseException $__apiResp) { throw $__apiResp;
        } catch (\Throwable $e) {
            // best effort
        }

        api_ok([
            'auth_enabled' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'role' => $user['role'],
            ],
            'member' => $linkedMember,
            'meeting_roles' => $meetingRoles,
        ]);
    }

    public function csrf(): void
    {
        api_request('GET');

        CsrfMiddleware::init();
        $token = CsrfMiddleware::getToken();

        api_ok([
            'csrf_token'  => $token,
            'header_name' => CsrfMiddleware::getHeaderName(),
            'field_name'  => CsrfMiddleware::getTokenName(),
        ]);
    }

    public function ping(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!\AgVote\Core\Security\RateLimiter::check('ping', $ip, 60, 60, false)) {
            api_fail('rate_limit_exceeded', 429);
        }
        api_ok(['ts' => date('c')]);
    }
}
