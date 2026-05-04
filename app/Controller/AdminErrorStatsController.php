<?php

declare(strict_types=1);

namespace AgVote\Controller;

use AgVote\Core\Security\AuthMiddleware;
use AgVote\Core\Security\SessionHelper;
use AgVote\Service\ErrorDictionary;
use AgVote\View\HtmlView;
use PDO;
use Throwable;

/**
 * AdminErrorStatsController — page admin /admin/error-stats.
 *
 * Source: ERR-V24-03 / D-10 — Plan 02.3 (Phase 2 v2.4).
 *
 * Controleur HTML standalone (n'etend PAS AbstractController, conforme a CLAUDE.md
 * "Controllers HTML : NE PAS etendre AbstractController, utiliser HtmlView::render()").
 *
 * RBAC: route declaree avec middleware role:admin dans app/routes.php.
 *
 * Donnees: agregation des audit_events filtres sur les actions erreur-flavored
 * (admin.confirm.failed, auth_account_locked, device_blocked, auth_rate_limited).
 * Limitations documentees inline et dans 02.3-AUDIT.md — la majorite des codes
 * ErrorDictionary ne sont pas trackes a ce stade (instrumentation v2.5+).
 *
 * Periodes supportees: 7d, 30d, 90d (defaut: 7d).
 */
final class AdminErrorStatsController {
    /** Periodes supportees: jours. */
    private const PERIODS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    /**
     * Actions audit_events considerees comme "error-flavored" pour aggregation.
     * Liste explicite — derivee de l'audit (02.3-AUDIT.md). Tout ajout futur doit
     * etre justifie dans le plan ou un follow-up audit.
     */
    private const ERROR_FLAVORED_ACTIONS = [
        'admin.confirm.failed',
        'auth_account_locked',
        'auth_account_lock_engaged',
        'auth_rate_limited',
        'device_blocked',
        'device_kicked',
    ];

    private ?PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db;
    }

    /**
     * Point d'entree dispatche par le router.
     *
     * @throws AdminErrorStatsForbiddenException in PHPUnit context if role != admin.
     */
    public function show(): void {
        SessionHelper::start();

        // Re-verification defensive (la route applique deja role:admin via RoleMiddleware).
        $role = AuthMiddleware::getCurrentRole();
        if ($role !== 'admin') {
            $this->forbid('Accès refusé. Cette page est réservée aux administrateurs.');
        }

        $period = $this->resolvePeriod($_GET['period'] ?? '7d');
        $tenantId = AuthMiddleware::getCurrentTenantId();

        $rows = [];
        $statsAvailable = false;
        try {
            $rows = $this->fetchStats($tenantId, self::PERIODS[$period]);
            $statsAvailable = true;
        } catch (Throwable $e) {
            // En cas d'echec DB, on rend la page avec un placeholder + log technique.
            error_log('AdminErrorStatsController::show fetchStats failed: ' . $e->getMessage());
            $rows = [];
            $statsAvailable = false;
        }

        HtmlView::render('admin/error_stats', [
            'period' => $period,
            'periods' => array_keys(self::PERIODS),
            'rows' => $rows,
            'statsAvailable' => $statsAvailable,
        ]);
    }

    /**
     * Valide et normalise la periode demandee. Defaut: 7d.
     */
    private function resolvePeriod(string $raw): string {
        $raw = strtolower(trim($raw));
        return array_key_exists($raw, self::PERIODS) ? $raw : '7d';
    }

    /**
     * Charge les agregats depuis audit_events.
     *
     * @return array<int, array{code: string, count: int, label: string}>
     *         Tableau de lignes triees par count desc.
     */
    private function fetchStats(string $tenantId, int $days): array {
        $pdo = $this->db ?? $this->resolveDb();
        if ($pdo === null) {
            // Pas de connexion DB disponible (ex: test sans injection) — placeholder.
            return [];
        }

        $placeholders = [];
        $params = [':tid' => $tenantId];
        foreach (self::ERROR_FLAVORED_ACTIONS as $i => $action) {
            $key = ":a{$i}";
            $placeholders[] = $key;
            $params[$key] = $action;
        }
        $params[':days'] = $days;

        $sql = sprintf(
            'SELECT action, COUNT(*) AS cnt
             FROM audit_events
             WHERE tenant_id = :tid
               AND action IN (%s)
               AND created_at >= NOW() - (:days || \' days\')::interval
             GROUP BY action
             ORDER BY cnt DESC
             LIMIT 50',
            implode(', ', $placeholders),
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = [];
        foreach ($raw as $r) {
            $code = (string) ($r['action'] ?? '');
            $rows[] = [
                'code' => $code,
                'count' => (int) ($r['cnt'] ?? 0),
                'label' => $this->labelFor($code),
            ];
        }
        return $rows;
    }

    /**
     * Retourne le libelle francais associe a un code (via ErrorDictionary si dispo).
     */
    private function labelFor(string $code): string {
        try {
            if (ErrorDictionary::hasMessage($code)) {
                return ErrorDictionary::getMessage($code);
            }
        } catch (Throwable $e) {
            // No-op: dictionary may not know the code.
        }
        return $code;
    }

    /**
     * Refuse l'acces avec une reponse 403.
     *
     * En tests (PHPUNIT_RUNNING): leve une exception capturable, pas d'exit().
     * En production: rend un message HTML simple via HtmlView::text() qui exit.
     */
    private function forbid(string $message): never {
        http_response_code(403);
        if (defined('PHPUNIT_RUNNING') && PHPUNIT_RUNNING === true) {
            echo $message;
            throw new AdminErrorStatsForbiddenException($message);
        }
        HtmlView::text($message, 403);
    }

    /**
     * Resout la connexion PDO via le helper global db() si disponible. null sinon.
     */
    private function resolveDb(): ?PDO {
        if (function_exists('db')) {
            try {
                $pdo = db();
                return $pdo instanceof PDO ? $pdo : null;
            } catch (Throwable $e) {
                // Tests stub db() to throw — that's OK, return null.
            }
        }
        return null;
    }
}
