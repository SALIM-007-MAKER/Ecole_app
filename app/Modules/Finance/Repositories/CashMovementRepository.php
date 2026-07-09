<?php

namespace App\Modules\Finance\Repositories;

use Core\Database;

class CashMovementRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ----------------------------------------------------------------
    // Lecture
    // ----------------------------------------------------------------
    public function getBySession(int $sessionId, bool $includeSysteme = true): array
    {
        $typeFilter = $includeSysteme ? '' : "AND m.type NOT IN ('ouverture','fermeture')";
        $stmt = $this->pdo->prepare(
            "SELECT m.*,
                    CONCAT(u.prenom,' ',u.nom) AS created_by_nom,
                    CONCAT(ua.prenom,' ',ua.nom) AS annule_par_nom
             FROM `finance_mouvements_caisse` m
             LEFT JOIN `users` u  ON u.id  = m.created_by
             LEFT JOIN `users` ua ON ua.id = m.annule_par
             WHERE m.session_id = ? {$typeFilter}
             ORDER BY m.created_at ASC, m.id ASC"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function find(int $id): ?object
    {
        $stmt = $this->pdo->prepare(
            "SELECT m.*,
                    CONCAT(u.prenom,' ',u.nom) AS created_by_nom
             FROM `finance_mouvements_caisse` m
             LEFT JOIN `users` u ON u.id = m.created_by
             WHERE m.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function findByPaiement(int $paiementId): ?object
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM `finance_mouvements_caisse` WHERE `paiement_id` = ? AND `statut` = ? LIMIT 1'
        );
        $stmt->execute([$paiementId, 'actif']);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }

    public function statsParType(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT type, sens,
                    COUNT(*) AS nb,
                    COALESCE(SUM(montant), 0) AS total
             FROM `finance_mouvements_caisse`
             WHERE session_id = ? AND statut = 'actif'
             GROUP BY type, sens
             ORDER BY type"
        );
        $stmt->execute([$sessionId]);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    // ----------------------------------------------------------------
    // Écriture
    // ----------------------------------------------------------------
    public function insert(array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`{$c}`", array_keys($data)));
        $phs  = implode(',', array_fill(0, count($data), '?'));
        $stmt = $this->pdo->prepare("INSERT INTO `finance_mouvements_caisse` ({$cols}) VALUES ({$phs})");
        $stmt->execute(array_values($data));
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $sets = implode(',', array_map(fn($c) => "`{$c}` = ?", array_keys($data)));
        $stmt = $this->pdo->prepare("UPDATE `finance_mouvements_caisse` SET {$sets} WHERE `id` = ?");
        $stmt->execute([...array_values($data), $id]);
    }

    // ----------------------------------------------------------------
    // PDO accessor
    // ----------------------------------------------------------------
    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
