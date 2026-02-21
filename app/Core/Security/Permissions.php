<?php
declare(strict_types=1);

namespace AgVote\Core\Security;

/**
 * Permissions - RBAC permissions configuration for AG-Vote.
 *
 * Centralizes all permission rules.
 * Format: 'resource:action' => [authorized roles]
 *
 * System roles: admin, operator, auditor, viewer
 * Meeting roles: president, assessor, voter
 */
final class Permissions
{
    /**
     * Role hierarchy levels.
     */
    public const HIERARCHY = [
        'admin'     => 100,
        'operator'  => 80,
        'president' => 70,
        'assessor'  => 60,
        'auditor'   => 50,
        'voter'     => 10,
        'viewer'    => 5,
        'public'    => 3,
        'anonymous' => 0,
    ];

    /**
     * Permissions by resource.
     */
    public const PERMISSIONS = [
        // Meetings - Lifecycle
        'meeting:create'       => ['admin', 'operator'],
        'meeting:read'         => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor', 'voter'],
        'meeting:update'       => ['admin', 'operator'],
        'meeting:delete'       => ['admin'],
        'meeting:freeze'       => ['admin', 'president'],
        'meeting:unfreeze'     => ['admin'],
        'meeting:open'         => ['admin', 'president'],
        'meeting:close'        => ['admin', 'president'],
        'meeting:validate'     => ['admin', 'president'],
        'meeting:archive'      => ['admin', 'operator'],
        'meeting:assign_roles' => ['admin', 'operator'],

        // Motions - Resolutions
        'motion:create' => ['admin', 'operator'],
        'motion:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor', 'voter'],
        'motion:update' => ['admin', 'operator'],
        'motion:delete' => ['admin', 'operator'],
        'motion:open'   => ['admin', 'operator'],
        'motion:close'  => ['admin', 'operator', 'president'],

        // Votes
        'vote:cast'   => ['admin', 'operator', 'voter'],
        'vote:read'   => ['admin', 'operator', 'auditor', 'president', 'assessor'],
        'vote:manual' => ['admin', 'operator'],

        // Members
        'member:create' => ['admin', 'operator'],
        'member:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'member:update' => ['admin', 'operator'],
        'member:delete' => ['admin'],
        'member:import' => ['admin', 'operator'],

        // Attendance
        'attendance:create' => ['admin', 'operator'],
        'attendance:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'attendance:update' => ['admin', 'operator'],

        // Proxies
        'proxy:create' => ['admin', 'operator'],
        'proxy:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'proxy:delete' => ['admin', 'operator'],

        // Speech
        'speech:request' => ['admin', 'operator', 'president', 'voter'],
        'speech:grant'   => ['admin', 'operator', 'president'],
        'speech:end'     => ['admin', 'operator', 'president'],

        // Audit
        'audit:read'   => ['admin', 'auditor', 'president', 'assessor'],
        'audit:export' => ['admin', 'auditor', 'president'],

        // Admin
        'admin:users'    => ['admin'],
        'admin:policies' => ['admin'],
        'admin:system'   => ['admin'],
        'admin:roles'    => ['admin'],

        // Reports
        'report:generate' => ['admin', 'operator', 'president'],
        'report:read'     => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'report:export'   => ['admin', 'operator', 'auditor', 'president'],
    ];

    /**
     * Meeting state transitions.
     * Format: from => [to => required_role]
     */
    public const TRANSITIONS = [
        'draft' => [
            'scheduled' => 'operator',
            'frozen'    => 'president',
        ],
        'scheduled' => [
            'frozen' => 'president',
            'draft'  => 'admin',
        ],
        'frozen' => [
            'live'      => 'president',
            'scheduled' => 'admin',
        ],
        'live' => [
            'paused' => 'operator',
            'closed' => 'president',
        ],
        'paused' => [
            'live'   => 'operator',
            'closed' => 'president',
        ],
        'closed' => [
            'validated' => 'president',
        ],
        'validated' => [
            'archived' => 'admin',
        ],
        // 'archived' is terminal — no transitions allowed
    ];

    /**
     * Labels for roles and statuses (French UI).
     */
    public const LABELS = [
        'roles' => [
            'admin'     => 'Administrateur',
            'operator'  => 'Operateur',
            'auditor'   => 'Auditeur',
            'viewer'    => 'Observateur',
            'president' => 'President de seance',
            'assessor'  => 'Assesseur / Scrutateur',
            'voter'     => 'Electeur',
        ],
        'statuses' => [
            'draft'     => 'Brouillon',
            'scheduled' => 'Planifiee',
            'frozen'    => 'Verrouillee',
            'live'      => 'En cours',
            'paused'    => 'En pause',
            'closed'    => 'Cloturee',
            'validated' => 'Validee',
            'archived'  => 'Archivee',
        ],
    ];

    /**
     * Get all configuration as array (for backward compatibility).
     */
    public static function getConfig(): array
    {
        return [
            'hierarchy'   => self::HIERARCHY,
            'permissions' => self::PERMISSIONS,
            'transitions' => self::TRANSITIONS,
            'labels'      => self::LABELS,
        ];
    }

    /**
     * Get role hierarchy.
     */
    public static function getHierarchy(): array
    {
        return self::HIERARCHY;
    }

    /**
     * Get permissions.
     */
    public static function getPermissions(): array
    {
        return self::PERMISSIONS;
    }

    /**
     * Get transitions.
     */
    public static function getTransitions(): array
    {
        return self::TRANSITIONS;
    }

    /**
     * Get labels.
     */
    public static function getLabels(): array
    {
        return self::LABELS;
    }
}
