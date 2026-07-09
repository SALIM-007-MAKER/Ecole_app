<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Models;

use Core\Database;

class InvCommandeModel
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.*, f.nom AS fournisseur_nom
               FROM inv_commandes c
               JOIN inv_fournisseurs f ON f.id = c.fournisseur_id
              WHERE c.id = :id AND c.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function lignes(int $commandeId): array
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

    public function peutEtreValidee(int $commandeId): bool
    {
        $commande = $this->find($commandeId);
        return $commande && $commande['statut'] === 'brouillon';
    }
}
