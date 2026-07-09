<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class ArticleRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, c.nom AS categorie_nom, f.nom AS fournisseur_nom,
                    e.nom AS emplacement_nom
               FROM inv_articles a
          LEFT JOIN inv_categories   c ON c.id = a.categorie_id
          LEFT JOIN inv_fournisseurs f ON f.id = a.fournisseur_id
          LEFT JOIN inv_emplacements e ON e.id = a.localisation_defaut
              WHERE a.id = :id AND a.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function search(array $filters, int $etablissementId): array
    {
        $where  = ['a.etablissement_id = :etab', 'a.deleted_at IS NULL'];
        $params = [':etab' => $etablissementId];

        if (!empty($filters['search'])) {
            $where[]           = '(a.designation LIKE :search OR a.reference LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['type'])) {
            $where[]         = 'a.type = :type';
            $params[':type'] = $filters['type'];
        }
        if (!empty($filters['categorie_id'])) {
            $where[]        = 'a.categorie_id = :cat';
            $params[':cat'] = (int)$filters['categorie_id'];
        }
        if (isset($filters['actif'])) {
            $where[]          = 'a.actif = :actif';
            $params[':actif'] = (int)$filters['actif'];
        }

        $sql = 'SELECT a.*, c.nom AS categorie_nom,
                       COALESCE(SUM(s.quantite_disponible),0) AS stock_total
                  FROM inv_articles a
             LEFT JOIN inv_categories c ON c.id = a.categorie_id
             LEFT JOIN inv_stocks     s ON s.article_id = a.id
                 WHERE ' . implode(' AND ', $where) . '
              GROUP BY a.id
              ORDER BY a.designation ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function paginate(array $filters, int $etablissementId, int $page, int $perPage): array
    {
        $all    = $this->search($filters, $etablissementId);
        $total  = count($all);
        $offset = ($page - 1) * $perPage;
        return [
            'items'       => array_slice($all, $offset, $perPage),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_articles
             (reference, designation, description, categorie_id, type, unite_mesure,
              seuil_alerte, seuil_critique, valeur_unitaire, fournisseur_id,
              barcode, numero_serie, localisation_defaut, garantie_mois, actif,
              created_by, etablissement_id)
             VALUES
             (:reference, :designation, :description, :categorie_id, :type, :unite_mesure,
              :seuil_alerte, :seuil_critique, :valeur_unitaire, :fournisseur_id,
              :barcode, :numero_serie, :localisation_defaut, :garantie_mois, :actif,
              :created_by, :etablissement_id)'
        );
        $stmt->execute($data);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_articles SET
               designation = :designation, description = :description,
               categorie_id = :categorie_id, type = :type, unite_mesure = :unite_mesure,
               seuil_alerte = :seuil_alerte, seuil_critique = :seuil_critique,
               valeur_unitaire = :valeur_unitaire, fournisseur_id = :fournisseur_id,
               barcode = :barcode, garantie_mois = :garantie_mois, actif = :actif
             WHERE id = :id AND deleted_at IS NULL'
        );
        $data[':id'] = $id;
        $stmt->execute($data);
    }

    public function softDelete(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_articles SET deleted_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function findByReference(string $ref, int $etablissementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_articles WHERE reference = :ref AND etablissement_id = :etab AND deleted_at IS NULL'
        );
        $stmt->execute([':ref' => $ref, ':etab' => $etablissementId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function findByBarcode(string $barcode, int $etablissementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_articles WHERE barcode = :bc AND etablissement_id = :etab AND deleted_at IS NULL'
        );
        $stmt->execute([':bc' => $barcode, ':etab' => $etablissementId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function articlesEnAlerte(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, COALESCE(SUM(s.quantite_disponible),0) AS stock_total
               FROM inv_articles a
          LEFT JOIN inv_stocks s ON s.article_id = a.id
              WHERE a.etablissement_id = :etab AND a.deleted_at IS NULL AND a.actif = 1
           GROUP BY a.id
             HAVING stock_total <= a.seuil_alerte
           ORDER BY stock_total ASC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function countByType(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT type, COUNT(*) AS total FROM inv_articles
              WHERE etablissement_id = :etab AND deleted_at IS NULL
           GROUP BY type'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
