<?php
declare(strict_types=1);

namespace AgVote\Repository;

use PDO;

/**
 * Classe de base pour tous les repositories.
 *
 * Encapsule l'acces PDO et fournit des helpers communs.
 * Chaque repository herite de cette classe et expose
 * des methodes metier typees (findById, listByTenant, etc.).
 *
 * Regle : un repository ne contient AUCUNE logique metier.
 */
abstract class AbstractRepository
{
    protected PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? db();
    }

    /**
     * Execute une requete et retourne une seule ligne (ou null).
     */
    protected function selectOne(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Execute une requete et retourne toutes les lignes.
     */
    protected function selectAll(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute une requete de modification et retourne le nombre de lignes affectees.
     */
    protected function execute(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /**
     * Execute une requete et retourne la premiere colonne de la premiere ligne.
     */
    protected function scalar(string $sql, array $params = []): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    /**
     * Execute un INSERT...RETURNING et retourne la ligne inseree.
     */
    protected function insertReturning(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Genere un UUID v4 via PostgreSQL.
     */
    protected function generateUuid(): string
    {
        return (string)$this->scalar("SELECT gen_random_uuid()");
    }
}
