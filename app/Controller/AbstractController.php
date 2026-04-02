<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Http\Request;
use AgVote\Core\Providers\RepositoryFactory;
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
 *
 * Repository access: use $this->repo() to get the shared RepositoryFactory.
 */
abstract class AbstractController {
    protected Request $request;

    public function __construct() {
        $this->request = new Request();
    }

    /**
     * Shared repository factory — lazy-cached instances, one per request.
     */
    protected function repo(): RepositoryFactory {
        return RepositoryFactory::getInstance();
    }

    public function handle(string $method): void {
        try {
            $this->$method();
        } catch (\AgVote\Core\Http\ApiResponseException $e) {
            throw $e; // Not an error — propagate to Router for sending
        } catch (InvalidArgumentException $e) {
            api_fail('invalid_request', 422, ['detail' => $e->getMessage()]);
        } catch (\PDOException $e) {
            error_log(static::class . '::' . $method . ' [DB]: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            api_fail('internal_error', 500, ['message' => 'Erreur interne du serveur. Veuillez réessayer.']);
        } catch (RuntimeException $e) {
            api_fail('business_error', 400, ['detail' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log(static::class . '::' . $method . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            api_fail('internal_error', 500, ['message' => 'Erreur interne du serveur. Veuillez réessayer.']);
        }
    }

    /**
     * Require that the request includes a valid confirm_password field.
     * Calls api_fail() if missing or incorrect — never returns on failure.
     */
    protected function requireConfirmation(array $in, string $tenantId): void {
        $confirmPassword = trim((string) ($in['confirm_password'] ?? ''));
        if ($confirmPassword === '') {
            api_fail('confirmation_required', 400, ['detail' => 'Veuillez confirmer votre mot de passe pour cette operation.']);
        }
        $adminUserId = api_current_user_id();
        $adminUser = $this->repo()->user()->findActiveById($adminUserId, $tenantId);
        if (!$adminUser || !password_verify($confirmPassword, $adminUser['password_hash'])) {
            audit_log('admin.confirm.failed', 'user', $adminUserId, ['action' => $in['action'] ?? '']);
            api_fail('confirmation_failed', 400, ['detail' => 'Mot de passe incorrect.']);
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
        } catch (\PDOException $e) {
            error_log('wrapApiCall [DB]: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            api_fail('internal_error', 500, ['message' => 'Erreur interne du serveur. Veuillez réessayer.']);
        } catch (Throwable $e) {
            api_fail($errorCode, $httpCode, ['detail' => $e->getMessage()]);
        }
    }
}
