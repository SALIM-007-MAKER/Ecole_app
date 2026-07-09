<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class InventairePhysiqueRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_inventaires WHERE etablissement_id = :etab ORDER BY date_debut DESC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM inv_inventaires WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_inventaires (nom, description, statut, date_debut, created_by, etablissement_id)
             VALUES (:nom, :description, :statut, :date_debut, :created_by, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut): void
    {
        $sets = ['statut = :statut'];
        if ($statut === 'termine') $sets[] = 'date_fin = CURDATE()';
        $this->pdo->prepare(
            'UPDATE inv_inventaires SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute([':statut' => $statut, ':id' => $id]);
    }

    public function initLignes(int $inventaireId, int $etablissementId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO inv_inventaire_lignes (inventaire_id, article_id, emplacement_id, quantite_theorique)
             SELECT :inv_id, s.article_id, s.emplacement_id, s.quantite_disponible
               FROM inv_stocks s
               JOIN inv_articles a ON a.id = s.article_id
              WHERE a.etablissement_id = :etab AND a.deleted_at IS NULL AND a.actif = 1'
        );
        $stmt->execute([':inv_id' => $inventaireId, ':etab' => $etablissementId]);
        return (int)$stmt->rowCount();
    }

    public function lignes(int $inventaireId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT il.*, a.designation, a.reference, a.unite_mesure, e.nom AS emplacement_nom
               FROM inv_inventaire_lignes il
               JOIN inv_articles a ON a.id = il.article_id
               JOIN inv_emplacements e ON e.id = il.emplacement_id
              WHERE il.inventaire_id = :id
           ORDER BY a.designation ASC'
        );
        $stmt->execute([':id' => $inventaireId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function updateLigne(int $ligneId, float $quantiteComptee, int $countedBy): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_inventaire_lignes
                SET quantite_comptee = :qte,
                    ecart            = :qte - quantite_theorique,
                    statut           = "compte",
                    counted_by       = :uid,
                    counted_at       = NOW()
              WHERE id = :id'
        );
        $stmt->execute([':qte' => $quantiteComptee, ':uid' => $countedBy, ':id' => $ligneId]);
    }

    public function ecartsSummary(int $inventaireId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT SUM(quantite_comptee IS NOT NULL) AS nb_comptes,
                    SUM(ABS(COALESCE(ecart,0)) > 0)  AS nb_ecarts,
                    SUM(COALESCE(ecart,0))            AS ecart_total
               FROM inv_inventaire_lignes WHERE inventaire_id = :id'
        );
        $stmt->execute([':id' => $inventaireId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
