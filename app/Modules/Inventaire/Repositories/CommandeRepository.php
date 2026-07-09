<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class CommandeRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId, ?string $statut = null): array
    {
        $where  = ['c.etablissement_id = :etab', 'c.deleted_at IS NULL'];
        $params = [':etab' => $etablissementId];
        if ($statut) {
            $where[]          = 'c.statut = :statut';
            $params[':statut'] = $statut;
        }
        $stmt = $this->pdo->prepare(
            'SELECT c.*, f.nom AS fournisseur_nom
               FROM inv_commandes c
               JOIN inv_fournisseurs f ON f.id = c.fournisseur_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY c.date_commande DESC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
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

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_commandes
             (numero, fournisseur_id, date_commande, date_livraison_prevue,
              total_ht, total_ttc, tva_taux, notes, created_by, etablissement_id)
             VALUES (:numero, :fournisseur_id, :date_commande, :date_livraison_prevue,
                     :total_ht, :total_ttc, :tva_taux, :notes, :created_by, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function updateStatut(int $id, string $statut, array $extra = []): void
    {
        $sets   = ['statut = :statut'];
        $params = [':statut' => $statut, ':id' => $id];
        foreach ($extra as $col => $val) {
            $sets[]           = "$col = :$col";
            $params[":$col"]  = $val;
        }
        $sql  = 'UPDATE inv_commandes SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
    }

    public function updateTotaux(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_commandes c
                SET c.total_ht  = (SELECT COALESCE(SUM(total_ht),0)    FROM inv_commande_lignes WHERE commande_id = :id),
                    c.total_ttc = (SELECT COALESCE(SUM(total_ht * (1 + tva_taux/100)),0) FROM inv_commande_lignes WHERE commande_id = :id2)
              WHERE c.id = :id3'
        );
        $stmt->execute([':id' => $id, ':id2' => $id, ':id3' => $id]);
    }

    public function generateNumero(int $etablissementId): string
    {
        $year = date('Y');
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inv_commandes WHERE etablissement_id = :etab AND YEAR(date_commande) = :y'
        );
        $stmt->execute([':etab' => $etablissementId, ':y' => $year]);
        $seq = (int)$stmt->fetchColumn() + 1;
        return sprintf('CMD-%s-%04d', $year, $seq);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_commandes SET deleted_at=NOW() WHERE id=:id AND statut="brouillon"'
        );
        $stmt->execute([':id' => $id]);
    }
}
