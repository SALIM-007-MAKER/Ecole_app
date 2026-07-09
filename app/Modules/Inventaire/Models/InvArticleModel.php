<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Models;

use Core\Database;

class InvArticleModel
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*, c.nom AS categorie_nom, f.nom AS fournisseur_nom
               FROM inv_articles a
          LEFT JOIN inv_categories  c ON c.id = a.categorie_id
          LEFT JOIN inv_fournisseurs f ON f.id = a.fournisseur_id
              WHERE a.id = :id AND a.deleted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function stockTotal(int $articleId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantite_disponible), 0) FROM inv_stocks WHERE article_id = :id'
        );
        $stmt->execute([':id' => $articleId]);
        return (float)$stmt->fetchColumn();
    }

    public function isEnAlerte(int $articleId): bool
    {
        $article = $this->find($articleId);
        if (!$article) return false;
        $stock = $this->stockTotal($articleId);
        return $stock <= (float)$article['seuil_alerte'];
    }
}
