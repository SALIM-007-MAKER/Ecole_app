<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class ReceptionRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inv_receptions WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function byCommande(int $commandeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_receptions WHERE commande_id = :id ORDER BY date_reception DESC'
        );
        $stmt->execute([':id' => $commandeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_receptions (commande_id, date_reception, bon_livraison, notes, created_by, etablissement_id)
             VALUES (:commande_id, :date_reception, :bon_livraison, :notes, :created_by, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function addLigne(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_reception_lignes
             (reception_id, commande_ligne_id, article_id, quantite_recue, emplacement_id, notes)
             VALUES (:reception_id, :commande_ligne_id, :article_id, :quantite_recue, :emplacement_id, :notes)'
        );
        $stmt->execute($data);
    }

    public function lignes(int $receptionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rl.*, a.designation, a.reference, e.nom AS emplacement_nom
               FROM inv_reception_lignes rl
               JOIN inv_articles a ON a.id = rl.article_id
               JOIN inv_emplacements e ON e.id = rl.emplacement_id
              WHERE rl.reception_id = :id'
        );
        $stmt->execute([':id' => $receptionId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
