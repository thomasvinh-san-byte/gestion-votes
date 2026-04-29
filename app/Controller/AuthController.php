<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Http\ApiResponseException;
use AgVote\Core\Http\JsonResponse;
use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Security\CsrfMiddleware;
use AgVote\Core\Security\RateLimiter;
use AgVote\Core\Security\SessionHelper;
use Throwable;

/**
 * Consolidates auth endpoints: login, logout, whoami, csrf, ping.
 */
final class AuthController extends AbstractController {
    /** Dummy hash for constant-time comparison when user doesn't exist. */
    private const DUMMY_HASH = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';

    public function login(): void {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            api_fail('method_not_allowed', 405);
        }

        // ── Rate limiting with audit (AUTH-02) ──
        // F02 hardening: real client IP, not a spoofable forwarded header.
        $ip = \AgVote\Core\Http\ClientIp::get();
        $maxAttempts = (int) (getenv('APP_LOGIN_MAX_ATTEMPTS') ?: 5);
        $windowSeconds = (int) (getenv('APP_LOGIN_WINDOW') ?: 300);

        if (RateLimiter::isLimited('auth_login', $ip, $maxAttempts, $windowSeconds)) {
            try {
                audit_log('auth_rate_limited', 'security', null, [
                    'ip' => $ip,
                    'attempt_count' => $maxAttempts,
                    'window' => $windowSeconds,
                ]);
            } catch (Throwable) { /* best effort */ }
            $retryMinutes = (int) ceil($windowSeconds / 60);
            throw new ApiResponseException(
                new JsonResponse(429, [
                    'ok' => false,
                    'error' => 'rate_limit_exceeded',
                    'detail' => "Trop de tentatives. R\u{00E9}essayez dans {$retryMinutes} minutes.",
                    'retry_after' => $windowSeconds,
                ], [
                    'Retry-After' => (string) $windowSeconds,
                ]),
            );
        }

        // Increment attempt counter (non-strict — blocking handled above)
        RateLimiter::check('auth_login', $ip, $maxAttempts, $windowSeconds, false);

        $rawBody = \AgVote\Core\Http\Request::getRawBody();
        $input = json_decode($rawBody, true);
        if (!is_array($input)) {
            $input = api_request('POST');
        }

        $userRepo = $this->repo()->user();
        $user = null;
        $authMethod = 'unknown';

        // ── Auth by email/password (priority) ──
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        // Dummy hash prevents timing-based user enumeration: password_verify() always runs.

        if ($email !== '' && $password !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                api_fail('invalid_email', 400, ['detail' => 'Format d\'adresse email invalide.']);
            }
            $authMethod = 'password';

            // F13: progressive per-account lockout. Checked AFTER the email format
            // gate so we never leak account existence on a malformed email, but
            // BEFORE the DB read so we don't generate load for a locked account.
            $lockoutStatus = \AgVote\Core\Security\AccountLockout::status($email);
            if ($lockoutStatus['locked']) {
                $retryAfter = $lockoutStatus['retry_after_seconds'];
                try {
                    audit_log('auth_account_locked', 'security', null, [
                        'email_hash' => hash('sha256', strtolower($email)),
                        'ip' => $ip,
                        'retry_after_seconds' => $retryAfter,
                    ]);
                } catch (Throwable) { /* best effort */ }
                throw new ApiResponseException(
                    new JsonResponse(423, [
                        'ok' => false,
                        'error' => 'account_locked',
                        'detail' => 'Trop d\'echecs sur ce compte. Reessayez plus tard ou reinitialisez votre mot de passe.',
                        'retry_after' => $retryAfter,
                    ], [
                        'Retry-After' => (string) $retryAfter,
                    ]),
                );
            }

            $user = $userRepo->findByEmailGlobal($email);

            // Always run password_verify regardless of user existence (constant-time).
            $hashToVerify = ($user && !empty($user['password_hash']))
                ? $user['password_hash']
                : self::DUMMY_HASH;
            $passwordValid = password_verify($password, $hashToVerify);

            if (!$user || empty($user['password_hash']) || !$passwordValid) {
                // F21: feed the security-signal escalation channel. Past
                // threshold (10 failures / 60 s from the same client),
                // SecuritySignal emits a SECURITY_ALERT log line that ops
                // dashboards filter on.
                \AgVote\Core\Security\SecuritySignal::record('auth_failure', 10, [
                    'email_hash' => hash('sha256', strtolower($email)),
                ]);
                // F13: per-account failure counter (in addition to per-IP).
                $lockoutAfterFail = \AgVote\Core\Security\AccountLockout::recordFailure($email);
                if ($lockoutAfterFail['locked']) {
                    try {
                        audit_log('auth_account_lock_engaged', 'security', null, [
                            'email_hash' => hash('sha256', strtolower($email)),
                            'failure_count' => $lockoutAfterFail['count'],
                            'lock_seconds' => $lockoutAfterFail['retry_after_seconds'],
                        ]);
                    } catch (Throwable) { /* best effort */ }
                }
                try {
                    $userRepo->logAuthFailure(
                        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                        $_SERVER['HTTP_USER_AGENT'] ?? '',
                        $email,
                        'invalid_credentials',
                    );
                } catch (Throwable) {
                    /* best effort */
                }
                api_fail('invalid_credentials', 401, ['detail' => 'Email ou mot de passe incorrect.']);
            }

            // Rehash if default algorithm changed
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                try {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $userRepo->setPasswordHash($user['tenant_id'], $user['id'], $newHash);
                } catch (Throwable) {
                    /* best effort */
                }
            }

        } else {
            // ── Fallback: API key auth (compat) ──
            $apiKey = trim((string) ($input['api_key'] ?? ''));

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
                        'invalid_key',
                    );
                } catch (Throwable) {
                    /* best effort */
                }
                api_fail('invalid_credentials', 401, ['detail' => 'Identifiants invalides.']);
            }
        }

        // ── Common checks ──
        if (empty($user['is_active'])) {
            api_fail('account_disabled', 403, ['detail' => 'Compte désactivé. Contactez un administrateur.']);
        }

        // F13: successful login → reset per-account failure counter.
        // We only reach here on a verified credential match.
        if ($authMethod === 'password' && $email !== '') {
            \AgVote\Core\Security\AccountLockout::reset($email);
        }

        // ── Create session ──
        // Restart to guarantee secure cookie params (AuthMiddleware may
        // have started a session earlier during rate-limit enforcement).
        SessionHelper::restart();
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

    public function logout(): void {
        api_request('POST');

        CsrfMiddleware::validate();

        $userId = api_current_user_id();
        if ($userId) {
            audit_log('user_logout', 'user', $userId, [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
        }

        SessionHelper::destroy();
        AuthMiddleware::reset();

        api_ok(['logged_out' => true]);
    }

    public function whoami(): void {
        $enabled = AuthMiddleware::isEnabled();
        if (!$enabled) {
            api_ok([
                'auth_enabled' => false,
                'user' => [
                    'id' => 'demo-user',
                    'email' => 'demo@ag-vote.local',
                    'name' => 'Mode Démonstration',
                    'role' => 'admin',
                ],
                'member' => null,
                'meeting_roles' => [],
            ]);
        }

        $user = AuthMiddleware::authenticate();
        if ($user === null) {
            api_fail('missing_or_invalid_api_key', 401, [
                'auth_enabled' => true,
            ]);
        }
        if (!$user['is_active']) {
            api_fail('user_inactive', 401, ['auth_enabled' => true]);
        }

        $meetingRoles = [];
        try {
            $userRepo = $this->repo()->user();
            $meetingRoles = $userRepo->listActiveMeetingRolesForUser($user['id'], $user['tenant_id']);
        } catch (Throwable) {
            // best effort
        }

        $linkedMember = null;
        try {
            $memberRepo = $this->repo()->member();
            $found = $memberRepo->findByUserId($user['id'], $user['tenant_id']);
            if ($found) {
                $linkedMember = [
                    'id' => $found['id'],
                    'full_name' => $found['full_name'],
                    'voting_power' => (float) ($found['voting_power'] ?? 1),
                ];
            }
        } catch (Throwable) {
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

    public function csrf(): void {
        api_request('GET');

        CsrfMiddleware::init();
        $token = CsrfMiddleware::getToken();

        api_ok([
            'csrf_token' => $token,
            'header_name' => CsrfMiddleware::getHeaderName(),
            'field_name' => CsrfMiddleware::getTokenName(),
        ]);
    }

    public function ping(): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (!\AgVote\Core\Security\RateLimiter::check('ping', $ip, 60, 60, false)) {
            api_fail('rate_limit_exceeded', 429);
        }
        api_ok(['ts' => date('c')]);
    }
}
