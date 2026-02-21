<?php

declare(strict_types=1);

namespace AgVote\Repository;

/**
 * Repository pour les templates email.
 */
class EmailTemplateRepository extends AbstractRepository {
    /**
     * Liste tous les templates d'un tenant.
     */
    public function listForTenant(string $tenantId, ?string $type = null): array {
        $sql = 'SELECT id, tenant_id, name, template_type, subject, body_html, body_text,
                       is_default, created_by, created_at, updated_at
                FROM email_templates
                WHERE tenant_id = :tenant_id';
        $params = [':tenant_id' => $tenantId];

        if ($type !== null) {
            $sql .= ' AND template_type = :type';
            $params[':type'] = $type;
        }

        $sql .= ' ORDER BY is_default DESC, name ASC';

        return $this->selectAll($sql, $params);
    }

    /**
     * Trouve un template par ID.
     */
    public function findById(string $id, string $tenantId): ?array {
        return $this->selectOne(
            'SELECT id, tenant_id, name, template_type, subject, body_html, body_text,
                    is_default, created_by, created_at, updated_at
             FROM email_templates
             WHERE id = :id AND tenant_id = :tenant_id',
            [':id' => $id, ':tenant_id' => $tenantId],
        );
    }

    /**
     * Trouve le template par defaut pour un type donne.
     */
    public function findDefault(string $tenantId, string $type = 'invitation'): ?array {
        return $this->selectOne(
            'SELECT id, tenant_id, name, template_type, subject, body_html, body_text,
                    is_default, created_by, created_at, updated_at
             FROM email_templates
             WHERE tenant_id = :tenant_id AND template_type = :type AND is_default = true
             LIMIT 1',
            [':tenant_id' => $tenantId, ':type' => $type],
        );
    }

    /**
     * Cree un nouveau template.
     */
    public function create(
        string $tenantId,
        string $name,
        string $type,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        bool $isDefault = false,
        ?string $createdBy = null,
    ): ?array {
        // Si is_default, retirer le flag des autres templates du meme type
        if ($isDefault) {
            $this->execute(
                'UPDATE email_templates SET is_default = false, updated_at = now()
                 WHERE tenant_id = :tenant_id AND template_type = :type AND is_default = true',
                [':tenant_id' => $tenantId, ':type' => $type],
            );
        }

        return $this->insertReturning(
            'INSERT INTO email_templates (tenant_id, name, template_type, subject, body_html, body_text, is_default, created_by)
             VALUES (:tenant_id, :name, :type, :subject, :body_html, :body_text, :is_default, :created_by)
             RETURNING id, tenant_id, name, template_type, subject, body_html, body_text, is_default, created_by, created_at, updated_at',
            [
                ':tenant_id' => $tenantId,
                ':name' => $name,
                ':type' => $type,
                ':subject' => $subject,
                ':body_html' => $bodyHtml,
                ':body_text' => $bodyText,
                ':is_default' => $isDefault ? 'true' : 'false',
                ':created_by' => $createdBy,
            ],
        );
    }

    /**
     * Met a jour un template existant.
     */
    public function update(
        string $id,
        string $tenantId,
        string $name,
        string $subject,
        string $bodyHtml,
        ?string $bodyText = null,
        ?bool $isDefault = null,
    ): ?array {
        // Si is_default devient true, retirer le flag des autres
        if ($isDefault === true) {
            $current = $this->findById($id, $tenantId);
            if ($current) {
                $this->execute(
                    'UPDATE email_templates SET is_default = false, updated_at = now()
                     WHERE tenant_id = :tenant_id AND template_type = :type AND is_default = true AND id != :id',
                    [':tenant_id' => $tenantId, ':type' => $current['template_type'], ':id' => $id],
                );
            }
        }

        $setClause = 'name = :name, subject = :subject, body_html = :body_html, body_text = :body_text, updated_at = now()';
        $params = [
            ':id' => $id,
            ':tenant_id' => $tenantId,
            ':name' => $name,
            ':subject' => $subject,
            ':body_html' => $bodyHtml,
            ':body_text' => $bodyText,
        ];

        if ($isDefault !== null) {
            $setClause .= ', is_default = :is_default';
            $params[':is_default'] = $isDefault ? 'true' : 'false';
        }

        return $this->insertReturning(
            "UPDATE email_templates SET {$setClause}
             WHERE id = :id AND tenant_id = :tenant_id
             RETURNING id, tenant_id, name, template_type, subject, body_html, body_text, is_default, created_by, created_at, updated_at",
            $params,
        );
    }

    /**
     * Supprime un template.
     */
    public function delete(string $id, string $tenantId): bool {
        $rows = $this->execute(
            'DELETE FROM email_templates WHERE id = :id AND tenant_id = :tenant_id AND is_default = false',
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
            $original['template_type'],
            $original['subject'],
            $original['body_html'],
            $original['body_text'],
            false,
            null,
        );
    }

    /**
     * Verifie si un nom de template existe deja.
     */
    public function nameExists(string $tenantId, string $name, ?string $excludeId = null): bool {
        $sql = 'SELECT 1 FROM email_templates WHERE tenant_id = :tenant_id AND name = :name';
        $params = [':tenant_id' => $tenantId, ':name' => $name];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        return $this->scalar($sql, $params) !== false;
    }
}
