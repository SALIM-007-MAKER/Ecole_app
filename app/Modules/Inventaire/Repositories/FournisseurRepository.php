<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class FournisseurRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function all(int $etablissementId, ?string $statut = null): array
    {
        $where  = ['etablissement_id = :etab', 'deleted_at IS NULL'];
        $params = [':etab' => $etablissementId];
        if ($statut) {
            $where[]          = 'statut = :statut';
            $params[':statut'] = $statut;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_fournisseurs WHERE ' . implode(' AND ', $where) . ' ORDER BY nom ASC'
        );
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_fournisseurs WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_fournisseurs
             (nom, code, email, telephone, adresse, site_web, rib,
              delai_livraison_j, conditions_paiement, statut, notes, etablissement_id)
             VALUES (:nom, :code, :email, :telephone, :adresse, :site_web, :rib,
                     :delai_livraison_j, :conditions_paiement, :statut, :notes, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_fournisseurs SET nom=:nom, code=:code, email=:email, telephone=:telephone,
             adresse=:adresse, site_web=:site_web, rib=:rib,
             delai_livraison_j=:delai_livraison_j, conditions_paiement=:conditions_paiement,
             statut=:statut, notes=:notes WHERE id=:id'
        );
        $data[':id'] = $id;
        $stmt->execute($data);
    }

    public function updateStatut(int $id, string $statut): void
    {
        $stmt = $this->pdo->prepare('UPDATE inv_fournisseurs SET statut=:s WHERE id=:id');
        $stmt->execute([':s' => $statut, ':id' => $id]);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE inv_fournisseurs SET deleted_at=NOW() WHERE id=:id');
        $stmt->execute([':id' => $id]);
    }

    public function hasCommandes(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM inv_commandes WHERE fournisseur_id=:id AND deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
