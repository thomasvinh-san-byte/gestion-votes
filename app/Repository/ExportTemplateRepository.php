<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository pour les templates d'export personnalisables.
 *
 * Permet de gerer des modeles d'export avec selection et ordre des colonnes.
 */
class ExportTemplateRepository extends AbstractRepository {
    /**
     * Types d'export supportes.
     */
    public const TYPES = ['attendance', 'votes', 'members', 'motions', 'audit', 'proxies'];

    /**
     * Colonnes disponibles par type d'export.
     */
    public const AVAILABLE_COLUMNS = [
        'attendance' => [
            ['field' => 'full_name', 'label' => 'Nom', 'default' => true],
            ['field' => 'voting_power', 'label' => 'Pouvoir de vote', 'default' => true],
            ['field' => 'mode', 'label' => 'Mode de présence', 'default' => true],
            ['field' => 'checked_in_at', 'label' => 'Arrivée', 'default' => true],
            ['field' => 'checked_out_at', 'label' => 'Départ', 'default' => true],
            ['field' => 'proxy_to_name', 'label' => 'Représenté par', 'default' => true],
            ['field' => 'proxies_received', 'label' => 'Procurations détenues', 'default' => true],
            ['field' => 'email', 'label' => 'Email', 'default' => false],
            ['field' => 'notes', 'label' => 'Notes', 'default' => false],
        ],
        'votes' => [
            ['field' => 'motion_title', 'label' => 'Résolution', 'default' => true],
            ['field' => 'motion_position', 'label' => 'N°', 'default' => true],
            ['field' => 'member_name', 'label' => 'Votant', 'default' => true],
            ['field' => 'choice', 'label' => 'Vote', 'default' => true],
            ['field' => 'weight', 'label' => 'Poids', 'default' => true],
            ['field' => 'is_proxy', 'label' => 'Par procuration', 'default' => true],
            ['field' => 'on_behalf_of', 'label' => 'Au nom de', 'default' => true],
            ['field' => 'cast_at', 'label' => 'Date/Heure', 'default' => true],
            ['field' => 'source', 'label' => 'Mode', 'default' => true],
        ],
        'members' => [
            ['field' => 'full_name', 'label' => 'Nom', 'default' => true],
            ['field' => 'email', 'label' => 'Email', 'default' => true],
            ['field' => 'voting_power', 'label' => 'Pouvoir de vote', 'default' => true],
            ['field' => 'role', 'label' => 'Rôle', 'default' => true],
            ['field' => 'is_active', 'label' => 'Actif', 'default' => true],
            ['field' => 'created_at', 'label' => 'Créé le', 'default' => false],
            ['field' => 'phone', 'label' => 'Téléphone', 'default' => false],
            ['field' => 'notes', 'label' => 'Notes', 'default' => false],
        ],
        'motions' => [
            ['field' => 'position', 'label' => 'N°', 'default' => true],
            ['field' => 'title', 'label' => 'Résolution', 'default' => true],
            ['field' => 'opened_at', 'label' => 'Ouverture', 'default' => true],
            ['field' => 'closed_at', 'label' => 'Clôture', 'default' => true],
            ['field' => 'for_count', 'label' => 'Pour', 'default' => true],
            ['field' => 'against_count', 'label' => 'Contre', 'default' => true],
            ['field' => 'abstain_count', 'label' => 'Abstention', 'default' => true],
            ['field' => 'nspp_count', 'label' => 'NSPP', 'default' => true],
            ['field' => 'total_expressed', 'label' => 'Total exprimés', 'default' => true],
            ['field' => 'voter_count', 'label' => 'Nb votants', 'default' => true],
            ['field' => 'decision', 'label' => 'Décision', 'default' => true],
            ['field' => 'description', 'label' => 'Description', 'default' => false],
        ],
        'audit' => [
            ['field' => 'timestamp', 'label' => 'Horodatage', 'default' => true],
            ['field' => 'actor', 'label' => 'Acteur', 'default' => true],
            ['field' => 'action', 'label' => 'Action', 'default' => true],
            ['field' => 'entity_type', 'label' => 'Entité', 'default' => true],
            ['field' => 'entity_id', 'label' => 'ID Entité', 'default' => true],
            ['field' => 'details', 'label' => 'Détails', 'default' => true],
            ['field' => 'ip_address', 'label' => 'Adresse IP', 'default' => false],
        ],
        'proxies' => [
            ['field' => 'giver_name', 'label' => 'Mandant', 'default' => true],
            ['field' => 'receiver_name', 'label' => 'Mandataire', 'default' => true],
            ['field' => 'scope', 'label' => 'Portée', 'default' => true],
            ['field' => 'created_at', 'label' => 'Créée le', 'default' => true],
            ['field' => 'revoked_at', 'label' => 'Révoquée le', 'default' => true],
            ['field' => 'giver_email', 'label' => 'Email mandant', 'default' => false],
            ['field' => 'receiver_email', 'label' => 'Email mandataire', 'default' => false],
        ],
    ];

    /**
     * Liste tous les templates d'un tenant.
     */
    public function listForTenant(string $tenantId, ?string $type = null): array {
        $sql = 'SELECT id, tenant_id, name, export_type, columns,
                       is_default, created_by, created_at, updated_at
                FROM export_templates
                WHERE tenant_id = :tenant_id';
        $params = [':tenant_id' => $tenantId];

        if ($type !== null) {
            $sql .= ' AND export_type = :type';
            $params[':type'] = $type;
        }

        $sql .= ' ORDER BY is_default DESC, name ASC';

        $rows = $this->selectAll($sql, $params);

        // Decode JSON columns
        return array_map(function ($row) {
            $row['columns'] = json_decode($row['columns'] ?? '[]', true);
            return $row;
        }, $rows);
    }

    /**
     * Trouve un template par ID.
     */
    public function findById(string $id, string $tenantId): ?array {
        $row = $this->selectOne(
            'SELECT id, tenant_id, name, export_type, columns,
                    is_default, created_by, created_at, updated_at
             FROM export_templates
             WHERE id = :id AND tenant_id = :tenant_id',
            [':id' => $id, ':tenant_id' => $tenantId],
        );

        if ($row) {
            $row['columns'] = json_decode($row['columns'] ?? '[]', true);
        }

        return $row;
    }

    /**
     * Trouve le template par defaut pour un type donne.
     */
    public function findDefault(string $tenantId, string $type): ?array {
        $row = $this->selectOne(
            'SELECT id, tenant_id, name, export_type, columns,
                    is_default, created_by, created_at, updated_at
             FROM export_templates
             WHERE tenant_id = :tenant_id AND export_type = :type AND is_default = true
             LIMIT 1',
            [':tenant_id' => $tenantId, ':type' => $type],
        );

        if ($row) {
            $row['columns'] = json_decode($row['columns'] ?? '[]', true);
        }

        return $row;
    }

    /**
     * Cree un nouveau template.
     */
    public function create(
        string $tenantId,
        string $name,
        string $type,
        array $columns,
        bool $isDefault = false,
        ?string $createdBy = null,
    ): ?array {
        // Valider le type
        if (!in_array($type, self::TYPES, true)) {
            return null;
        }

        // Valider les colonnes
        $columns = $this->validateColumns($type, $columns);

        $row = $this->insertReturning(
            'INSERT INTO export_templates (tenant_id, name, export_type, columns, is_default, created_by)
             VALUES (:tenant_id, :name, :type, :columns, :is_default, :created_by)
             RETURNING id, tenant_id, name, export_type, columns, is_default, created_by, created_at, updated_at',
            [
                ':tenant_id' => $tenantId,
                ':name' => $name,
                ':type' => $type,
                ':columns' => json_encode($columns, JSON_UNESCAPED_UNICODE),
                ':is_default' => $isDefault ? 'true' : 'false',
                ':created_by' => $createdBy,
            ],
        );

        if ($row) {
            $row['columns'] = json_decode($row['columns'] ?? '[]', true);
        }

        return $row;
    }

    /**
     * Met a jour un template existant.
     */
    public function update(
        string $id,
        string $tenantId,
        string $name,
        array $columns,
        ?bool $isDefault = null,
    ): ?array {
        $current = $this->findById($id, $tenantId);
        if (!$current) {
            return null;
        }

        // Valider les colonnes
        $columns = $this->validateColumns($current['export_type'], $columns);

        $setClause = 'name = :name, columns = :columns, updated_at = now()';
        $params = [
            ':id' => $id,
            ':tenant_id' => $tenantId,
            ':name' => $name,
            ':columns' => json_encode($columns, JSON_UNESCAPED_UNICODE),
        ];

        if ($isDefault !== null) {
            $setClause .= ', is_default = :is_default';
            $params[':is_default'] = $isDefault ? 'true' : 'false';
        }

        $row = $this->insertReturning(
            "UPDATE export_templates SET {$setClause}
             WHERE id = :id AND tenant_id = :tenant_id
             RETURNING id, tenant_id, name, export_type, columns, is_default, created_by, created_at, updated_at",
            $params,
        );

        if ($row) {
            $row['columns'] = json_decode($row['columns'] ?? '[]', true);
        }

        return $row;
    }

    /**
     * Supprime un template.
     */
    public function delete(string $id, string $tenantId): bool {
        $rows = $this->execute(
            'DELETE FROM export_templates WHERE id = :id AND tenant_id = :tenant_id',
            [':id' => $id, ':tenant_id' => $tenantId],
        );
        return $rows > 0;
    }

    /**
     * Duplique un template.
     */
    public function duplicate(string $id, string $tenantId, string $newName): ?array {
        $original = $this->findById($id, $tenantId);
        if (!$original) {
            return null;
        }

        return $this->create(
            $tenantId,
            $newName,
            $original['export_type'],
            $original['columns'],
            false,
            null,
        );
    }

    /**
     * Verifie si un nom de template existe deja pour ce type.
     */
    public function nameExists(string $tenantId, string $name, string $type, ?string $excludeId = null): bool {
        $sql = 'SELECT 1 FROM export_templates WHERE tenant_id = :tenant_id AND name = :name AND export_type = :type';
        $params = [':tenant_id' => $tenantId, ':name' => $name, ':type' => $type];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        return $this->scalar($sql, $params) !== false;
    }

    /**
     * Retourne les colonnes disponibles pour un type d'export.
     */
    public function getAvailableColumns(string $type): array {
        return self::AVAILABLE_COLUMNS[$type] ?? [];
    }

    /**
     * Retourne les colonnes par defaut pour un type d'export.
     */
    public function getDefaultColumns(string $type): array {
        $available = self::AVAILABLE_COLUMNS[$type] ?? [];
        $defaults = [];
        $order = 1;

        foreach ($available as $col) {
            if ($col['default'] ?? false) {
                $defaults[] = [
                    'field' => $col['field'],
                    'label' => $col['label'],
                    'order' => $order++,
                ];
            }
        }

        return $defaults;
    }

    /**
     * Valide et nettoie les colonnes.
     */
    private function validateColumns(string $type, array $columns): array {
        $available = self::AVAILABLE_COLUMNS[$type] ?? [];
        $validFields = array_column($available, 'field');
        $fieldLabels = array_column($available, 'label', 'field');

        $validated = [];
        $order = 1;

        foreach ($columns as $col) {
            $field = $col['field'] ?? null;
            if (!$field || !in_array($field, $validFields, true)) {
                continue;
            }

            $validated[] = [
                'field' => $field,
                'label' => $col['label'] ?? $fieldLabels[$field] ?? $field,
                'order' => $col['order'] ?? $order,
            ];
            $order++;
        }

        // Trier par ordre
        usort($validated, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        // Renumeroter
        foreach ($validated as $i => &$col) {
            $col['order'] = $i + 1;
        }

        return $validated;
    }
}
