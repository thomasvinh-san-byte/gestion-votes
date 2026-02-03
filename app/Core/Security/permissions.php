<?php
/**
 * Configuration des permissions RBAC pour AG-Vote.
 *
 * Ce fichier centralise toutes les regles de permissions.
 * Format: 'resource:action' => [roles autorises]
 *
 * Roles systeme: admin, operator, auditor, viewer
 * Roles seance: president, assessor, voter
 */

return [
    // =========================================================================
    // HIERARCHIE DES ROLES
    // =========================================================================
    'hierarchy' => [
        'admin'     => 100,
        'operator'  => 80,
        'president' => 70,
        'assessor'  => 60,
        'auditor'   => 50,
        'voter'     => 10,
        'viewer'    => 5,
        'public'    => 3,
        'anonymous' => 0,
    ],

    // =========================================================================
    // PERMISSIONS PAR RESSOURCE
    // =========================================================================
    'permissions' => [
        // -----------------------------------------------------------------
        // MEETINGS - Cycle de vie
        // -----------------------------------------------------------------
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

        // -----------------------------------------------------------------
        // MOTIONS - Resolutions
        // -----------------------------------------------------------------
        'motion:create' => ['admin', 'operator'],
        'motion:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor', 'voter'],
        'motion:update' => ['admin', 'operator'],
        'motion:delete' => ['admin', 'operator'],
        'motion:open'   => ['admin', 'operator'],
        'motion:close'  => ['admin', 'operator', 'president'],

        // -----------------------------------------------------------------
        // VOTES
        // -----------------------------------------------------------------
        'vote:cast'   => ['admin', 'operator', 'voter'],
        'vote:read'   => ['admin', 'operator', 'auditor', 'president', 'assessor'],
        'vote:manual' => ['admin', 'operator'],

        // -----------------------------------------------------------------
        // MEMBERS - Gestion des membres
        // -----------------------------------------------------------------
        'member:create' => ['admin', 'operator'],
        'member:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'member:update' => ['admin', 'operator'],
        'member:delete' => ['admin'],
        'member:import' => ['admin', 'operator'],

        // -----------------------------------------------------------------
        // ATTENDANCE - Presences
        // -----------------------------------------------------------------
        'attendance:create' => ['admin', 'operator'],
        'attendance:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'attendance:update' => ['admin', 'operator'],

        // -----------------------------------------------------------------
        // PROXIES - Procurations
        // -----------------------------------------------------------------
        'proxy:create' => ['admin', 'operator'],
        'proxy:read'   => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'proxy:delete' => ['admin', 'operator'],

        // -----------------------------------------------------------------
        // SPEECH - Demandes de parole
        // -----------------------------------------------------------------
        'speech:request' => ['admin', 'operator', 'president', 'voter'],
        'speech:grant'   => ['admin', 'operator', 'president'],
        'speech:end'     => ['admin', 'operator', 'president'],

        // -----------------------------------------------------------------
        // AUDIT
        // -----------------------------------------------------------------
        'audit:read'   => ['admin', 'auditor', 'president', 'assessor'],
        'audit:export' => ['admin', 'auditor', 'president'],

        // -----------------------------------------------------------------
        // ADMIN
        // -----------------------------------------------------------------
        'admin:users'    => ['admin'],
        'admin:policies' => ['admin'],
        'admin:system'   => ['admin'],
        'admin:roles'    => ['admin'],

        // -----------------------------------------------------------------
        // REPORTS
        // -----------------------------------------------------------------
        'report:generate' => ['admin', 'operator', 'president'],
        'report:read'     => ['admin', 'operator', 'auditor', 'viewer', 'president', 'assessor'],
        'report:export'   => ['admin', 'operator', 'auditor', 'president'],
    ],

    // =========================================================================
    // TRANSITIONS D'ETAT DES SEANCES
    // Format: from => [to => required_role]
    // =========================================================================
    'transitions' => [
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
            'closed' => 'president',
        ],
        'closed' => [
            'validated' => 'president',
        ],
        'validated' => [
            'archived' => 'admin',
        ],
    ],

    // =========================================================================
    // LABELS
    // =========================================================================
    'labels' => [
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
            'closed'    => 'Cloturee',
            'validated' => 'Validee',
            'archived'  => 'Archivee',
        ],
    ],
];
