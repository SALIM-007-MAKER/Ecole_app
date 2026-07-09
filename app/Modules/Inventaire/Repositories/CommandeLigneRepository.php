<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class CommandeLigneRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function byCommande(int $commandeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cl.*, a.designation, a.reference, a.unite_mesure
               FROM inv_commande_lignes cl
               JOIN inv_articles a ON a.id = cl.article_id
              WHERE cl.commande_id = :id'
        );
        $stmt->execute([':id' => $commandeId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $totalHt = ($data[':quantite_commandee'] ?? 0) * ($data[':prix_unitaire_ht'] ?? 0);
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_commande_lignes
             (commande_id, article_id, quantite_commandee, prix_unitaire_ht, tva_taux, total_ht, notes)
             VALUES (:commande_id, :article_id, :quantite_commandee, :prix_unitaire_ht, :tva_taux, :total_ht, :notes)'
        );
        $data[':total_ht'] = $totalHt;
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateQuantiteRecue(int $ligneId, float $qteSupplementaire): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_commande_lignes
                SET quantite_recue = quantite_recue + :qte
              WHERE id = :id'
        );
        $stmt->execute([':qte' => $qteSupplementaire, ':id' => $ligneId]);
    }

    public function deleteByCommande(int $commandeId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM inv_commande_lignes WHERE commande_id = :id');
        $stmt->execute([':id' => $commandeId]);
    }
}
