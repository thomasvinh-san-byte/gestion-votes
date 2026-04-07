<?php

declare(strict_types=1);

namespace AgVote\Repository;

use PDO;

/**
 * Base class for all repositories.
 *
 * Encapsulates PDO access and provides common helpers.
 * Each repository inherits from this class and exposes
 * typed business methods (findById, listByTenant, etc.).
 *
 * Rule: a repository contains NO business logic.
 */
abstract class AbstractRepository {
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null) {
        $this->pdo = $pdo ?? db();
    }

    /**
     * Expose PDO for cross-table queries (e.g., notification reads).
     */
    public function getPdo(): PDO {
        return $this->pdo;
    }

    /**
     * Executes a query and returns a single row (or null).
     */
    protected function selectOne(string $sql, array $params = []): ?array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Executes a query and returns all rows.
     */
    protected function selectAll(string $sql, array $params = []): array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executes a SELECT and yields rows one at a time via PDO cursor.
     * Use for streaming exports to avoid loading full result set in memory.
     *
     * @return \Generator<int, array>
     */
    protected function selectGenerator(string $sql, array $params = []): \Generator {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
        $st->closeCursor();
    }

    /**
     * Executes a modification query and returns the number of affected rows.
     */
    protected function execute(string $sql, array $params = []): int {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /**
     * Executes a query and returns the first column of the first row.
     */
    protected function scalar(string $sql, array $params = []): mixed {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    /**
     * Executes an INSERT...RETURNING and returns the inserted row.
     */
    protected function insertReturning(string $sql, array $params = []): ?array {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Builds a safe IN clause with named placeholders.
     *
     * @param string $prefix  Placeholder prefix (e.g. 'id')
     * @param array  $values  Values to bind
     * @param array  &$params Reference to params array — placeholders are merged in
     * @return string SQL fragment like ":id0, :id1, :id2"
     */
    protected function buildInClause(string $prefix, array $values, array &$params): string {
        $placeholders = [];
        foreach (array_values($values) as $i => $val) {
            $key = ":{$prefix}{$i}";
            $placeholders[] = $key;
            $params[$key] = $val;
        }
        return implode(', ', $placeholders);
    }

    /**
     * Generates a UUID v4 via PostgreSQL.
     */
    public function generateUuid(): string {
        return (string) $this->scalar('SELECT gen_random_uuid()');
    }
}
