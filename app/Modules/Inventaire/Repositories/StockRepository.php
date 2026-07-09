<?php

declare(strict_types=1);

namespace App\Modules\Inventaire\Repositories;

use Core\Database;

class StockRepository
{
    private \PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance()->getConnection();
    }

    public function getByArticle(int $articleId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, e.nom AS emplacement_nom
               FROM inv_stocks s
               JOIN inv_emplacements e ON e.id = s.emplacement_id
              WHERE s.article_id = :id'
        );
        $stmt->execute([':id' => $articleId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByEmplacement(int $emplacementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, a.designation, a.reference, a.unite_mesure
               FROM inv_stocks s
               JOIN inv_articles a ON a.id = s.article_id
              WHERE s.emplacement_id = :id'
        );
        $stmt->execute([':id' => $emplacementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function totalDisponible(int $articleId): float
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(quantite_disponible),0) FROM inv_stocks WHERE article_id = :id'
        );
        $stmt->execute([':id' => $articleId]);
        return (float)$stmt->fetchColumn();
    }

    public function getLine(int $articleId, int $emplacementId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inv_stocks WHERE article_id = :a AND emplacement_id = :e'
        );
        $stmt->execute([':a' => $articleId, ':e' => $emplacementId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function upsert(int $articleId, int $emplacementId, float $delta, int $etablissementId): float
    {
        $qtyInsert = max(0.0, $delta);
        $stmt = $this->pdo->prepare(
            'INSERT INTO inv_stocks (article_id, emplacement_id, quantite_disponible, etablissement_id)
             VALUES (:a, :e, :qi, :etab)
             ON DUPLICATE KEY UPDATE quantite_disponible = GREATEST(0, quantite_disponible + :delta)'
        );
        $stmt->execute([
            ':a'     => $articleId,
            ':e'     => $emplacementId,
            ':qi'    => $qtyInsert,
            ':etab'  => $etablissementId,
            ':delta' => $delta,
        ]);
        $line = $this->getLine($articleId, $emplacementId);
        return (float)($line['quantite_disponible'] ?? 0.0);
    }

    public function reserverStock(int $articleId, int $emplacementId, float $qte): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_stocks SET quantite_disponible = quantite_disponible - :q,
                                   quantite_reservee   = quantite_reservee   + :q
              WHERE article_id=:a AND emplacement_id=:e AND quantite_disponible >= :q'
        );
        $stmt->execute([':q' => $qte, ':a' => $articleId, ':e' => $emplacementId]);
    }

    public function libererReservation(int $articleId, int $emplacementId, float $qte): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE inv_stocks SET quantite_disponible = quantite_disponible + :q,
                                   quantite_reservee   = GREATEST(0, quantite_reservee - :q)
              WHERE article_id=:a AND emplacement_id=:e'
        );
        $stmt->execute([':q' => $qte, ':a' => $articleId, ':e' => $emplacementId]);
    }

    public function globalStock(int $etablissementId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.reference, a.designation, a.unite_mesure, a.seuil_alerte,
                    COALESCE(SUM(s.quantite_disponible),0) AS stock_total,
                    COALESCE(SUM(s.quantite_reservee),0)   AS stock_reserve
               FROM inv_articles a
          LEFT JOIN inv_stocks s ON s.article_id = a.id
              WHERE a.etablissement_id = :etab AND a.deleted_at IS NULL AND a.actif = 1
           GROUP BY a.id
           ORDER BY a.designation ASC'
        );
        $stmt->execute([':etab' => $etablissementId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
